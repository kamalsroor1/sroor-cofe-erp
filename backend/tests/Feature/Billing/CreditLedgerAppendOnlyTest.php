<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Exceptions\Billing\CreditLedgerException;
use App\Models\TenantCreditLedgerEntry;
use App\Services\Billing\CreditBalanceService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-1.10: `tenant_credit_ledger` is append-only. No Eloquent path can change or remove a
 * movement (model events + query builder); a correction is a new compensating row. The DB
 * grants (no UPDATE/DELETE for the app user) are the production backstop.
 */
#[Group('billing')]
#[Group('mysql')]
final class CreditLedgerAppendOnlyTest extends TenantTestCase
{
    private const KEY = 'credits.messages';

    public function test_a_row_cannot_be_updated_through_the_model(): void
    {
        $entry = $this->entry('10');
        $entry->delta = '999.000';

        $this->assertImmutable(fn () => $entry->save());
        $this->assertSame('10.000', TenantCreditLedgerEntry::query()->findOrFail($entry->id)->delta);
    }

    public function test_a_row_cannot_be_deleted_through_the_model(): void
    {
        $entry = $this->entry('10');

        $this->assertImmutable(fn () => $entry->delete());
        $this->assertImmutable(fn () => TenantCreditLedgerEntry::destroy($entry->id));
        $this->assertTrue(TenantCreditLedgerEntry::query()->whereKey($entry->id)->exists());
    }

    public function test_rows_cannot_be_rewritten_through_the_builder(): void
    {
        $entry = $this->entry('10');
        $query = fn () => TenantCreditLedgerEntry::query()->whereKey($entry->id);

        $attempts = [
            'update' => fn () => $query()->update(['delta' => '0']),
            'upsert' => fn () => TenantCreditLedgerEntry::query()->upsert([['id' => $entry->id, 'delta' => '0']], ['id'], ['delta']),
            'increment' => fn () => $query()->increment('delta', 5),
            'decrement' => fn () => $query()->decrement('delta', 5),
            'touch' => fn () => $query()->touch(),
            'delete' => fn () => $query()->delete(),
            'forceDelete' => fn () => $query()->forceDelete(),
            'truncate' => fn () => TenantCreditLedgerEntry::query()->truncate(),
        ];

        foreach ($attempts as $name => $attempt) {
            $this->assertImmutable($attempt, $name);
        }

        $this->assertSame('10.000', app(CreditBalanceService::class)->balance($entry->tenant_id, self::KEY));
    }

    public function test_inserts_and_reads_still_work(): void
    {
        $tenantId = $this->createTenant()->id;
        $service = app(CreditBalanceService::class);

        $service->credit($tenantId, self::KEY, '3');
        $service->debit($tenantId, self::KEY, '1');

        $this->assertSame(2, TenantCreditLedgerEntry::query()->where('tenant_id', $tenantId)->count());
        $this->assertSame('2.000', $service->balance($tenantId, self::KEY));
    }

    public function test_the_immutable_error_is_a_stable_reason(): void
    {
        $exception = CreditLedgerException::immutable();

        $this->assertSame('immutable', $exception->reason());
    }

    private function entry(string $amount): TenantCreditLedgerEntry
    {
        return app(CreditBalanceService::class)->credit($this->createTenant()->id, self::KEY, $amount);
    }

    private function assertImmutable(callable $attempt, string $name = 'attempt'): void
    {
        try {
            $attempt();
            $this->fail("{$name} on the credits ledger must throw.");
        } catch (CreditLedgerException $e) {
            $this->assertSame('immutable', $e->reason(), $name);
        }
    }
}
