<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;
use Throwable;

/**
 * ENTI-1.7 on a real MySQL server: the founder-slot counter row lock and a parallel race
 * of first payments for the last free slots.
 *
 * Group `mysql-only`: phpunit.xml (sqlite) excludes it, the CI `mysql` job runs it through
 * phpunit.mysql.xml (group `mysql`). sqlite has no row locks and serialises writers on a
 * file lock, so these proofs mean nothing there: setUp() fails loudly instead of skipping
 * when the central connection is not MySQL/MariaDB. The driver-agnostic founder tests
 * stay in FounderPricingServiceTest.
 */
#[Group('billing')]
#[Group('mysql')]
#[Group('mysql-only')]
final class FounderPricingMysqlConcurrencyTest extends TenantTestCase
{
    private const LOCK_WAIT_TIMEOUT = 1205;

    /** Parallel first payments racing for the last two founder slots. */
    private const RACE_WORKERS = 6;

    /** Seconds a worker may take to boot Laravel and connect before the race starts. */
    private const WORKER_READY_TIMEOUT = 60;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("FounderPricingMysqlConcurrencyTest needs MySQL/MariaDB (central driver: {$driver}). Run it with phpunit.mysql.xml.");
        }

        config(['billing.founder.slots' => 50, 'billing.founder.months' => 12]);
    }

    public function test_claim_waits_for_the_counter_lock_on_mysql(): void
    {
        $central = $this->centralConnectionName();

        [, $subscription, $payment] = $this->paidTenant();
        $holder = $this->centralSideConnection('qa_founder_slot_holder');

        // Normally a no-op (the migration created the row); only a row this test inserted
        // is removed afterwards, never the migrated counter (that one is reset to 0).
        $inserted = $holder->table('billing_sequences')->insertOrIgnore([
            'key' => FounderPricingService::SLOT_SEQUENCE, 'period' => '', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->beforeApplicationDestroyed(function () use ($inserted): void {
            $counter = $this->centralSideConnection('qa_founder_slot_cleanup')->table('billing_sequences')
                ->where('key', FounderPricingService::SLOT_SEQUENCE)->where('period', '');
            $inserted > 0 ? $counter->delete() : $counter->update(['last_value' => 0]);
            DB::purge('qa_founder_slot_cleanup');
        });

        $connection = DB::connection($central);
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $holder->beginTransaction();

        $errorCode = null;
        try {
            $holder->selectOne('select id from billing_sequences where `key` = ? and period = ? for update', [FounderPricingService::SLOT_SEQUENCE, '']);

            try {
                $connection->transaction(fn (): bool => $this->service()->claim($subscription, $payment));
            } catch (Throwable $e) {
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
            DB::purge('qa_founder_slot_holder');
        }

        try {
            $this->assertSame(self::LOCK_WAIT_TIMEOUT, $errorCode, 'claim() did not wait for the counter lock: lockForUpdate() is missing.');
            $this->assertTrue($connection->transaction(fn (): bool => $this->service()->claim($subscription, $payment)));
        } finally {
            $connection->statement('SET SESSION innodb_lock_wait_timeout = 50');
        }
    }

    public function test_parallel_first_payments_never_exceed_the_founder_slots_on_mysql(): void
    {
        // Committed fixtures on a separate session: the worker processes cannot see rows
        // inside this test's RefreshDatabase transaction.
        $side = $this->centralSideConnection('qa_founder_race');
        $counter = fn () => $side->table('billing_sequences')->where('key', FounderPricingService::SLOT_SEQUENCE)->where('period', '');
        $previous = (int) $counter()->value('last_value');
        $tenantIds = [];
        $planId = null;

        try {
            $now = Carbon::now()->startOfSecond();
            $slug = 'race-'.Str::lower(Str::random(10));
            $planId = (int) $side->table('plans')->insertGetId([
                'name' => $slug, 'slug' => $slug, 'price_monthly' => '449.000', 'price_yearly' => '4490.000',
                'founder_price_monthly' => '299.000', 'features' => '[]', 'is_active' => true, 'sort_order' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $payloads = [];
            for ($i = 0; $i < self::RACE_WORKERS; $i++) {
                $tenantId = 'qarace'.Str::lower(Str::random(10));
                $tenantIds[] = $tenantId;
                $side->table('tenants')->insert([
                    'id' => $tenantId, 'name' => $tenantId, 'slug' => $tenantId, 'email' => $tenantId.'@race.harness.test',
                    'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $subscriptionId = (int) $side->table('subscriptions')->insertGetId([
                    'tenant_id' => $tenantId, 'plan_id' => $planId, 'billing_cycle' => BillingCycle::Monthly->value,
                    'status' => SubscriptionStatus::Active->value, 'amount' => '449.000', 'currency' => 'EGP',
                    'price_locked' => false, 'is_founder' => false,
                    'starts_at' => $now, 'ends_at' => $now->copy()->addMonth(), 'created_at' => $now, 'updated_at' => $now,
                ]);
                $invoiceId = (int) $side->table('billing_invoices')->insertGetId([
                    'number' => 'RACE-'.Str::lower(Str::random(12)), 'tenant_id' => $tenantId, 'subscription_id' => $subscriptionId,
                    'type' => BillingInvoiceType::Plan->value, 'lines' => '[]', 'subtotal' => '449.000', 'total' => '449.000',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $paymentId = (int) $side->table('billing_payments')->insertGetId([
                    'billing_invoice_id' => $invoiceId, 'tenant_id' => $tenantId, 'amount' => '449.000',
                    'method' => BillingPaymentMethod::Instapay->value, 'status' => BillingPaymentStatus::Verified->value,
                    'paid_at' => $now, 'verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);

                $payloads[] = ['subscription_id' => $subscriptionId, 'payment_id' => $paymentId];
            }

            // Two slots left out of the configured 50 for six simultaneous first payments.
            $counter()->update(['last_value' => 48]);

            $results = $this->runFounderWorkers($payloads);

            foreach ($results as $result) {
                $this->assertTrue($result['ok'], 'No claim may crash or deadlock: '.json_encode($result, JSON_UNESCAPED_UNICODE));
            }
            $claimed = array_values(array_filter($results, fn (array $r): bool => $r['claimed'] === true));
            $this->assertCount(2, $claimed, 'Exactly the two free slots may be handed out: '.json_encode($results, JSON_UNESCAPED_UNICODE));
            $this->assertSame(50, (int) $counter()->value('last_value'), 'The counter must stop at the configured 50 slots.');

            $founderIds = $side->table('subscriptions')->whereIn('tenant_id', $tenantIds)->where('is_founder', true)
                ->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $claimedIds = array_map(fn (array $r): int => (int) $r['subscription_id'], $claimed);
            sort($claimedIds);
            $this->assertSame($claimedIds, $founderIds, 'Only the winning subscriptions are marked as founders.');
        } finally {
            $side->table('billing_payments')->whereIn('tenant_id', $tenantIds)->delete();
            $side->table('billing_invoices')->whereIn('tenant_id', $tenantIds)->delete();
            $side->table('tenants')->whereIn('id', $tenantIds)->delete();
            if ($planId !== null) {
                $side->table('plans')->where('id', $planId)->delete();
            }
            $counter()->update(['last_value' => $previous]);
            DB::purge('qa_founder_race');
        }
    }

    /**
     * Start one PHP process per payload (tests/Support/founder-slot-worker.php), release
     * them at the same moment and collect their JSON results.
     *
     * @param  list<array{subscription_id: int, payment_id: int}>  $payloads
     * @return list<array<string, mixed>>
     */
    private function runFounderWorkers(array $payloads): array
    {
        $script = base_path('tests/Support/founder-slot-worker.php');
        $workers = [];

        try {
            foreach ($payloads as $payload) {
                $stderr = (string) tempnam(sys_get_temp_dir(), 'qa-founder-');
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, $script, base64_encode((string) json_encode($payload))],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderr, 'w']],
                    $pipes,
                    base_path(),
                );

                if (! is_resource($process)) {
                    throw new RuntimeException('Could not start a founder-slot worker.');
                }

                $workers[] = ['process' => $process, 'pipes' => $pipes, 'stderr' => $stderr];
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipes'][1], self::WORKER_READY_TIMEOUT);
                $line = trim((string) fgets($worker['pipes'][1]));
                if ($line !== 'READY') {
                    throw new RuntimeException('Worker did not become ready: '.$line.' '.(string) file_get_contents($worker['stderr']));
                }
            }

            // Barrier released: all workers claim at (almost) the same moment.
            foreach ($workers as $worker) {
                fwrite($worker['pipes'][0], "GO\n");
                fflush($worker['pipes'][0]);
            }

            $results = [];
            foreach ($workers as $worker) {
                $output = trim((string) stream_get_contents($worker['pipes'][1]));
                $lines = preg_split('/\r?\n/', $output) ?: [];
                $decoded = json_decode((string) end($lines), true);

                if (! is_array($decoded) || ! array_key_exists('ok', $decoded)) {
                    throw new RuntimeException('Worker returned no result: '.$output.' '.(string) file_get_contents($worker['stderr']));
                }

                $results[] = $decoded;
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                foreach ($worker['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($worker['process']);
                @unlink($worker['stderr']);
            }
        }
    }

    private function centralSideConnection(string $name): Connection
    {
        config(["database.connections.{$name}" => (array) config('database.connections.'.$this->centralConnectionName())]);

        return DB::connection($name);
    }

    private function service(): FounderPricingService
    {
        return app(FounderPricingService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): Plan
    {
        $slug = 'plan-'.Str::lower(Str::random(10));

        return Plan::query()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'founder_price_monthly' => '299.000',
            'founder_price_yearly' => null,
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    /**
     * A tenant with one active subscription and its first verified payment.
     *
     * @param  array<string, mixed>  $planAttributes
     * @return array{0: Tenant, 1: Subscription, 2: BillingPayment}
     */
    private function paidTenant(BillingCycle $cycle = BillingCycle::Monthly, ?Carbon $verifiedAt = null, array $planAttributes = []): array
    {
        $tenant = $this->createTenant();
        $plan = $this->plan($planAttributes);

        $subscription = $this->subscription($tenant, $plan->id, $cycle);
        $payment = $this->payment($this->invoice($subscription), [
            'status' => BillingPaymentStatus::Verified,
            'verified_at' => $verifiedAt ?? Carbon::now()->startOfSecond(),
            'paid_at' => $verifiedAt ?? Carbon::now()->startOfSecond(),
        ]);

        return [$tenant, $subscription, $payment];
    }

    private function subscription(Tenant $tenant, int $planId, BillingCycle $cycle): Subscription
    {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'status' => SubscriptionStatus::Active,
            'amount' => $cycle === BillingCycle::Yearly ? '4490.000' : '449.000',
            'starts_at' => now(),
            'ends_at' => $cycle === BillingCycle::Yearly ? now()->addYear() : now()->addMonth(),
        ]);
    }

    private function invoice(Subscription $subscription): BillingInvoice
    {
        return BillingInvoice::query()->create([
            'number' => 'TST-'.Str::lower(Str::random(12)),
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'type' => BillingInvoiceType::Plan,
            'lines' => [['kind' => 'plan', 'key' => 'basic', 'quantity' => 1, 'unit_price' => '449.000', 'total' => '449.000']],
            'subtotal' => '449.000',
            'total' => '449.000',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payment(BillingInvoice $invoice, array $attributes = []): BillingPayment
    {
        return BillingPayment::query()->create(array_merge([
            'billing_invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->total,
            'method' => BillingPaymentMethod::Instapay,
        ], $attributes));
    }
}
