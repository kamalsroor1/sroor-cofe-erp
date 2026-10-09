<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Exceptions\Billing\CreditLedgerException;
use App\Models\TenantCreditLedgerEntry;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Balance and movements of a tenant's credits for one credits add-on (ENTI-1.10,
 * CTO 2026-10-09 Q12). Central DB only.
 *
 *  - The balance is never stored: it is Σ `tenant_credit_ledger.delta` for
 *    (tenant_id, addon_key), added with bcmath at scale 3 (no floats, no SQL SUM, which
 *    sqlite would compute as a float).
 *  - Every movement runs in a central transaction that first takes SELECT … FOR UPDATE on
 *    the (tenant_id, addon_key) sentinel row of `tenant_credit_accounts`, then sums, checks
 *    and appends. Two concurrent debits therefore serialise on the sentinel and the second
 *    sees the first one's row: the balance can never go below zero.
 *  - The sentinel is created by INSERT IGNORE before the transaction when none is open
 *    (so the transaction itself only ever takes the one exclusive row lock, see the
 *    founder-slot deadlock note in migration 2026_10_10_200530); inside a caller's open
 *    transaction it is created there.
 *
 * Phase 1 sells and consumes nothing: this is the engine the Phase 3 paid-messages
 * channels and IssueSubscriptionInvoiceAction (top_up lines) will call.
 */
final class CreditBalanceService
{
    /** A positive decimal that fits DECIMAL(12,3). */
    private const AMOUNT_PATTERN = '/^\d{1,9}(\.\d{1,3})?$/';

    private const LEDGER = 'tenant_credit_ledger';

    private const ACCOUNTS = 'tenant_credit_accounts';

    /** Current balance (scale 3 string). A plain read: use it for display, not to decide a debit. */
    public function balance(string $tenantId, string $addonKey): string
    {
        $this->assertKeys($tenantId, $addonKey);

        return $this->sum($this->connection(), $tenantId, $addonKey);
    }

    /**
     * Add credits: the cycle allowance of a credits add-on, or a paid top-up.
     *
     * @throws CreditLedgerException invalid amount or a debit reason
     */
    public function credit(
        string $tenantId,
        string $addonKey,
        string $amount,
        string $reason = TenantCreditLedgerEntry::REASON_ALLOWANCE,
        ?int $billingInvoiceId = null,
    ): TenantCreditLedgerEntry {
        if (! in_array($reason, TenantCreditLedgerEntry::CREDIT_REASONS, true)) {
            throw CreditLedgerException::invalidReason();
        }

        return $this->append($tenantId, $addonKey, $this->positive($amount), $reason, $billingInvoiceId);
    }

    /**
     * Remove credits (usage, or the expiry of an unused allowance). Refused, with nothing
     * written, when the balance is smaller than the amount.
     *
     * @throws CreditLedgerException insufficient balance, invalid amount or a credit reason
     */
    public function debit(
        string $tenantId,
        string $addonKey,
        string $amount,
        string $reason = TenantCreditLedgerEntry::REASON_USAGE,
    ): TenantCreditLedgerEntry {
        if (! in_array($reason, TenantCreditLedgerEntry::DEBIT_REASONS, true)) {
            throw CreditLedgerException::invalidReason();
        }

        return $this->append($tenantId, $addonKey, bcsub('0', $this->positive($amount), 3), $reason, null);
    }

    private function append(string $tenantId, string $addonKey, string $delta, string $reason, ?int $billingInvoiceId): TenantCreditLedgerEntry
    {
        $this->assertKeys($tenantId, $addonKey);

        $connection = $this->connection();

        if ($connection->transactionLevel() === 0) {
            $this->ensureAccount($connection, $tenantId, $addonKey);
        }

        return $connection->transaction(function () use ($connection, $tenantId, $addonKey, $delta, $reason, $billingInvoiceId): TenantCreditLedgerEntry {
            $this->lockAccount($connection, $tenantId, $addonKey);

            // Locking read: under REPEATABLE READ a plain SELECT inside a caller's older
            // transaction would read its snapshot and could miss a debit committed meanwhile.
            $balance = $this->sum($connection, $tenantId, $addonKey, locking: true);

            if (bccomp(bcadd($balance, $delta, 3), '0', 3) < 0) {
                throw CreditLedgerException::insufficientBalance();
            }

            return TenantCreditLedgerEntry::query()->create([
                'tenant_id' => $tenantId,
                'addon_key' => $addonKey,
                'delta' => $delta,
                'reason' => $reason,
                'billing_invoice_id' => $billingInvoiceId,
            ]);
        });
    }

    /** SELECT … FOR UPDATE on the sentinel; creates it first when it does not exist yet. */
    private function lockAccount(Connection $connection, string $tenantId, string $addonKey): void
    {
        $locked = $connection->table(self::ACCOUNTS)
            ->where('tenant_id', $tenantId)
            ->where('addon_key', $addonKey)
            ->lockForUpdate()
            ->first(['id']);

        if ($locked !== null) {
            return;
        }

        $this->ensureAccount($connection, $tenantId, $addonKey);

        $connection->table(self::ACCOUNTS)
            ->where('tenant_id', $tenantId)
            ->where('addon_key', $addonKey)
            ->lockForUpdate()
            ->first(['id']);
    }

    private function ensureAccount(Connection $connection, string $tenantId, string $addonKey): void
    {
        $connection->table(self::ACCOUNTS)->insertOrIgnore([
            'tenant_id' => $tenantId,
            'addon_key' => $addonKey,
            'created_at' => now(),
        ]);
    }

    /**
     * Σ delta. With $locking the rows are read with LOCK IN SHARE MODE / FOR SHARE, a
     * "current read" that always sees the latest committed rows, whatever the snapshot of
     * the surrounding transaction (sqlite ignores the clause; it serialises writers anyway).
     */
    private function sum(Connection $connection, string $tenantId, string $addonKey, bool $locking = false): string
    {
        $balance = '0.000';

        $query = $connection->table(self::LEDGER)
            ->select(['id', 'delta'])
            ->where('tenant_id', $tenantId)
            ->where('addon_key', $addonKey);

        if ($locking) {
            $query->sharedLock();
        }

        $rows = $query->lazyById(1000, 'id');

        foreach ($rows as $row) {
            $balance = bcadd($balance, $this->decimal($row->delta), 3);
        }

        return $balance;
    }

    /**
     * A stored DECIMAL as a numeric string. MySQL returns DECIMAL as a string already;
     * sqlite (tests) may return an int/float of a value that was stored with at most three
     * decimals, whose shortest string form is exact.
     */
    private function decimal(mixed $value): string
    {
        $string = is_string($value) ? $value : (is_int($value) || is_float($value) ? (string) $value : '0');

        if (! is_numeric($string)) {
            throw new InvalidArgumentException('Non-numeric delta in tenant_credit_ledger.');
        }

        return bcadd($string, '0', 3);
    }

    /**
     * @throws CreditLedgerException
     */
    private function positive(string $amount): string
    {
        $amount = trim($amount);

        if (preg_match(self::AMOUNT_PATTERN, $amount) !== 1) {
            throw CreditLedgerException::invalidAmount();
        }

        $normalized = bcadd($amount, '0', 3);

        if (bccomp($normalized, '0', 3) <= 0) {
            throw CreditLedgerException::invalidAmount();
        }

        return $normalized;
    }

    private function assertKeys(string $tenantId, string $addonKey): void
    {
        if (trim($tenantId) === '' || trim($addonKey) === '' || mb_strlen($addonKey) > 100) {
            throw new InvalidArgumentException('CreditBalanceService needs a tenant id and an add-on key of at most 100 characters.');
        }
    }

    private function connection(): Connection
    {
        return DB::connection((string) (new TenantCreditLedgerEntry)->getConnectionName());
    }
}
