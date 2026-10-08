<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Exceptions\Billing\BillingSequenceException;
use App\Models\BillingSequence;
use App\Services\Billing\BillingSequenceService;
use App\Services\Billing\FounderPricingService;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;
use Throwable;

/**
 * ENTI-1.6: gap-free SaaS invoice numbering (central `billing_sequences`).
 *
 * - next() must run inside the caller's transaction on the central connection: the
 *   increment commits or rolls back together with the invoice that uses it, so a
 *   rolled-back invoice never burns a number;
 * - the number prefix comes from config('billing.invoice_number.prefix'), never from
 *   the brand / app name;
 * - the counter is per (key, period); with the yearly period it restarts every year
 *   (year taken in config('billing.timezone')).
 */
#[Group('billing')]
#[Group('mysql')]
final class BillingSequenceServiceTest extends TenantTestCase
{
    /** MySQL error code for "Lock wait timeout exceeded". */
    private const LOCK_WAIT_TIMEOUT = 1205;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.timezone' => 'Africa/Cairo',
            'billing.invoice_number.prefix' => 'INV',
            'billing.invoice_number.period' => 'yearly',
            'billing.invoice_number.pad' => 6,
        ]);
    }

    public function test_next_starts_at_one_and_increments_per_key_and_period(): void
    {
        $service = $this->service();

        $values = DB::connection($this->centralConnectionName())->transaction(fn (): array => [
            $service->next('billing_invoice', '2026'),
            $service->next('billing_invoice', '2026'),
            $service->next('billing_invoice', '2026'),
            $service->next('billing_invoice', '2027'),
            $service->next('credit_note', '2026'),
            $service->next('billing_invoice', '2026'),
        ]);

        $this->assertSame([1, 2, 3, 1, 1, 4], $values);
        $this->assertSame(4, $service->current('billing_invoice', '2026'));
        $this->assertSame(1, $service->current('billing_invoice', '2027'));
        $this->assertSame(0, $service->current('never_used', '2026'));
        $this->assertSame(3, $this->issuedSequenceRows(), 'One row per (key, period).');
    }

    public function test_a_rolled_back_transaction_does_not_consume_a_number(): void
    {
        $service = $this->service();
        $central = DB::connection($this->centralConnectionName());

        $first = $central->transaction(fn (): int => $service->next('billing_invoice', '2026'));
        $this->assertSame(1, $first);

        $caught = null;
        try {
            $central->transaction(function () use ($service): void {
                $this->assertSame(2, $service->next('billing_invoice', '2026'));
                $this->assertSame(3, $service->next('billing_invoice', '2026'));

                throw new RuntimeException('invoice insert failed');
            });
        } catch (RuntimeException $e) {
            $caught = $e->getMessage();
        }
        $this->assertSame('invoice insert failed', $caught, 'The failing invoice must roll the transaction back.');

        $this->assertSame(1, $service->current('billing_invoice', '2026'), 'The rollback must give the numbers back.');
        $this->assertSame(2, $central->transaction(fn (): int => $service->next('billing_invoice', '2026')), 'No gap after a rollback.');
    }

    public function test_a_rolled_back_first_use_does_not_leave_a_consumed_row(): void
    {
        $service = $this->service();
        $central = DB::connection($this->centralConnectionName());

        try {
            $central->transaction(function () use ($service): void {
                $service->next('billing_invoice', '2030');

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, $service->current('billing_invoice', '2030'));
        $this->assertSame(1, $central->transaction(fn (): int => $service->next('billing_invoice', '2030')));
    }

    public function test_next_refuses_to_run_outside_a_transaction(): void
    {
        $central = DB::connection($this->centralConnectionName());
        $levels = $central->transactionLevel();

        // RefreshDatabase wraps the test in a transaction: leave it to reproduce a real
        // caller that forgot DB::transaction(), then restore it for the teardown rollback.
        for ($i = 0; $i < $levels; $i++) {
            $central->rollBack();
        }

        try {
            $this->assertSame(0, $central->transactionLevel());
            $this->service()->next('billing_invoice', '2026');
            $this->fail('next() must refuse to run without an open transaction.');
        } catch (BillingSequenceException $e) {
            $this->assertSame('transaction_required', $e->reason());
            $this->assertSame(__('billing.sequence.transaction_required'), $e->getMessage());
        } finally {
            for ($i = 0; $i < $levels; $i++) {
                $central->beginTransaction();
            }
        }

        $this->assertSame(0, $this->issuedSequenceRows(), 'Nothing may be written outside a transaction.');
    }

    public function test_invoice_number_uses_the_configured_prefix_year_and_padding(): void
    {
        $service = $this->service();
        $at = Carbon::parse('2026-10-08 12:00:00', 'UTC');

        $numbers = $this->inCentralTransaction(fn (): array => [
            $service->nextInvoiceNumber($at),
            $service->nextInvoiceNumber($at),
        ]);

        $this->assertSame(['INV-2026-000001', 'INV-2026-000002'], $numbers);
    }

    public function test_invoice_prefix_comes_from_config_not_from_the_brand(): void
    {
        config([
            'app.name' => 'Some Brand Name',
            'billing.invoice_number.prefix' => 'SUB',
            'billing.invoice_number.pad' => 4,
        ]);

        $number = $this->inCentralTransaction(fn (): string => $this->service()->nextInvoiceNumber(Carbon::parse('2026-03-01', 'UTC')));

        $this->assertSame('SUB-2026-0001', $number);
        $this->assertStringNotContainsStringIgnoringCase('brand', $number);
    }

    public function test_an_empty_prefix_is_allowed(): void
    {
        config(['billing.invoice_number.prefix' => '']);

        $number = $this->inCentralTransaction(fn (): string => $this->service()->nextInvoiceNumber(Carbon::parse('2026-03-01', 'UTC')));

        $this->assertSame('2026-000001', $number);
    }

    public function test_yearly_numbering_restarts_each_year_in_the_billing_timezone(): void
    {
        $service = $this->service();

        $numbers = $this->inCentralTransaction(fn (): array => [
            $service->nextInvoiceNumber(Carbon::parse('2026-12-31 20:00:00', 'UTC')),
            // 22:30 UTC on 31 Dec is already 1 Jan 2027 in Cairo (UTC+2).
            $service->nextInvoiceNumber(Carbon::parse('2026-12-31 22:30:00', 'UTC')),
            $service->nextInvoiceNumber(Carbon::parse('2027-06-01 10:00:00', 'UTC')),
        ]);

        $this->assertSame(['INV-2026-000001', 'INV-2027-000001', 'INV-2027-000002'], $numbers);
    }

    public function test_continuous_numbering_without_a_period(): void
    {
        config(['billing.invoice_number.period' => 'none']);
        $service = $this->service();

        $numbers = $this->inCentralTransaction(fn (): array => [
            $service->nextInvoiceNumber(Carbon::parse('2026-12-31 10:00:00', 'UTC')),
            $service->nextInvoiceNumber(Carbon::parse('2027-01-02 10:00:00', 'UTC')),
        ]);

        $this->assertSame(['INV-000001', 'INV-000002'], $numbers);
    }

    public function test_invoice_number_defaults_to_now(): void
    {
        $this->travelTo(Carbon::parse('2026-05-05 09:00:00', 'UTC'));

        $number = $this->inCentralTransaction(fn (): string => $this->service()->nextInvoiceNumber());

        $this->assertSame('INV-2026-000001', $number);
    }

    public function test_numbers_grow_past_the_padding_without_truncation(): void
    {
        config(['billing.invoice_number.pad' => 2]);
        $service = $this->service();

        $last = $this->inCentralTransaction(function () use ($service): string {
            $number = '';
            for ($i = 0; $i < 100; $i++) {
                $number = $service->nextInvoiceNumber(Carbon::parse('2026-01-01 12:00:00', 'UTC'));
            }

            return $number;
        });

        $this->assertSame('INV-2026-100', $last);
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function invalidConfiguration(): iterable
    {
        yield 'prefix with a dash' => ['billing.invoice_number.prefix', 'IN-V'];
        yield 'prefix with spaces' => ['billing.invoice_number.prefix', 'IN V'];
        yield 'prefix too long' => ['billing.invoice_number.prefix', 'ABCDEFGHIJKLM'];
        yield 'prefix not a string' => ['billing.invoice_number.prefix', ['INV']];
        yield 'unknown period' => ['billing.invoice_number.period', 'monthly'];
        yield 'pad zero' => ['billing.invoice_number.pad', 0];
        yield 'pad too large' => ['billing.invoice_number.pad', 13];
        yield 'unknown timezone' => ['billing.timezone', 'Mars/Olympus'];
    }

    #[DataProvider('invalidConfiguration')]
    public function test_invalid_configuration_is_rejected_before_a_number_is_consumed(string $key, mixed $value): void
    {
        config([$key => $value]);
        $service = $this->service();

        try {
            $this->inCentralTransaction(fn (): string => $service->nextInvoiceNumber(Carbon::parse('2026-01-01', 'UTC')));
            $this->fail("{$key} must be validated.");
        } catch (BillingSequenceException $e) {
            $this->assertSame('invalid_configuration', $e->reason());
            $this->assertSame(__('billing.sequence.invalid_configuration', ['key' => $key]), $e->getMessage());
        }

        $this->assertSame(0, $this->issuedSequenceRows());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty key' => ['', '2026'];
        yield 'key with spaces' => ['billing invoice', '2026'];
        yield 'key too long' => [str_repeat('k', 51), '2026'];
        yield 'period with spaces' => ['billing_invoice', '20 26'];
        yield 'period too long' => ['billing_invoice', '20262026202'];
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_sequence_keys_are_rejected(string $key, string $period): void
    {
        try {
            $this->inCentralTransaction(fn (): int => $this->service()->next($key, $period));
            $this->fail('An invalid sequence key must be rejected.');
        } catch (BillingSequenceException $e) {
            $this->assertSame('invalid_key', $e->reason());
            $this->assertSame(__('billing.sequence.invalid_key'), $e->getMessage());
        }
    }

    public function test_sequence_lives_in_the_central_db_even_inside_a_tenant(): void
    {
        $tenant = $this->createTenant();
        $central = $this->centralConnectionName();

        $seen = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'connection' => (new BillingSequence)->getConnectionName(),
            'number' => DB::connection($central)->transaction(
                fn (): string => app(BillingSequenceService::class)->nextInvoiceNumber(Carbon::parse('2026-02-02', 'UTC')),
            ),
        ]);

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame($central, $seen['connection']);
        $this->assertSame('INV-2026-000001', $seen['number']);
        $this->assertSame(1, BillingSequence::query()->where('key', BillingSequenceService::INVOICE_SEQUENCE)->where('period', '2026')->value('last_value'));
    }

    public function test_next_waits_for_the_sequence_row_lock_on_mysql(): void
    {
        $central = $this->centralConnectionName();
        $driver = DB::connection($central)->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('MySQL-only: sqlite has no row locks. Runs in the CI `mysql` job (phpunit.mysql.xml).');
        }

        $key = 'qa_lock_'.Str::lower(Str::random(8));
        $holder = $this->centralSideConnection('qa_billing_seq_holder');

        // Committed by the side session (autocommit), so both sessions see the row.
        $holder->table('billing_sequences')->insert([
            'key' => $key, 'period' => '', 'last_value' => 41, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Runs after the RefreshDatabase rollback has released the test session's lock.
        $this->beforeApplicationDestroyed(function () use ($key): void {
            $this->centralSideConnection('qa_billing_seq_cleanup')->table('billing_sequences')->where('key', $key)->delete();
            DB::purge('qa_billing_seq_cleanup');
        });

        $connection = DB::connection($central);
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $holder->beginTransaction();

        try {
            $holder->selectOne('select id from billing_sequences where `key` = ? and period = ? for update', [$key, '']);

            $errorCode = null;
            try {
                $connection->transaction(fn (): int => $this->service()->next($key));
            } catch (Throwable $e) {
                // Nested inside the RefreshDatabase transaction, Laravel wraps the lock wait
                // timeout in a DeadlockException; the QueryException is in the chain.
                for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
                    if ($cause instanceof QueryException) {
                        $errorCode = (int) ($cause->errorInfo[1] ?? 0);
                        break;
                    }
                }

                if ($errorCode === null) {
                    throw $e;
                }
            }
        } finally {
            $holder->rollBack();
            DB::purge('qa_billing_seq_holder');
        }

        try {
            $this->assertSame(self::LOCK_WAIT_TIMEOUT, $errorCode, 'next() did not wait for the row lock: lockForUpdate() is missing.');

            // Lock released: the same call goes through and continues the sequence without a gap.
            $this->assertSame(42, $connection->transaction(fn (): int => $this->service()->next($key)));
        } finally {
            $connection->statement('SET SESSION innodb_lock_wait_timeout = 50');
        }
    }

    /**
     * Counter rows written by the numbering under test. The founder-slot counter row is
     * created by its own migration (2026_10_10_200530) and is not a numbering sequence.
     */
    private function issuedSequenceRows(): int
    {
        return BillingSequence::query()->where('key', '!=', FounderPricingService::SLOT_SEQUENCE)->count();
    }

    private function service(): BillingSequenceService
    {
        return app(BillingSequenceService::class);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function inCentralTransaction(Closure $callback): mixed
    {
        return DB::connection($this->centralConnectionName())->transaction($callback);
    }

    /** A second session on the central schema (a different transaction than the test's). */
    private function centralSideConnection(string $name): Connection
    {
        config(["database.connections.{$name}" => (array) config('database.connections.'.$this->centralConnectionName())]);

        return DB::connection($name);
    }
}
