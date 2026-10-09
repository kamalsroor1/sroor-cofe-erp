<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Exceptions\Billing\BillingSequenceException;
use App\Models\BillingSequence;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Illuminate\Support\Carbon;

/**
 * Gap-free numbering for central SaaS billing documents (ENTI-1.6).
 *
 * The counter row is locked (lockForUpdate) and incremented inside the CALLER's
 * transaction on the central connection: the number is consumed only if the document
 * that uses it commits. A rollback gives it back, so the series never has gaps, and
 * the row lock serialises concurrent issuers, so it never has duplicates.
 *
 * Usage (e.g. IssueSubscriptionInvoiceAction, ENTI-3.2):
 *
 *     DB::connection(central)->transaction(function () {
 *         $number = $this->sequences->nextInvoiceNumber();
 *         BillingInvoice::query()->create([... 'number' => $number ...]);
 *     });
 *
 * Keep the transaction short: other issuers wait on the same row until it commits.
 */
final class BillingSequenceService
{
    /** Sequence key of SaaS subscription invoices. */
    public const INVOICE_SEQUENCE = 'billing_invoice';

    public const PERIOD_YEARLY = 'yearly';

    public const PERIOD_NONE = 'none';

    private const KEY_PATTERN = '/^[a-z0-9_.]{1,50}$/';

    private const PERIOD_PATTERN = '/^[A-Za-z0-9-]{0,10}$/';

    private const PREFIX_PATTERN = '/^[A-Za-z0-9]{0,12}$/';

    private const MAX_PAD = 12;

    /**
     * Allocate the next value of ($key, $period). Must run inside an open transaction on
     * the central connection; the row is created on first use.
     *
     * @throws BillingSequenceException when called outside a transaction or with an invalid key
     */
    public function next(string $key, string $period = ''): int
    {
        $this->assertValidKey($key, $period);

        $connection = (new BillingSequence)->getConnection();
        if ($connection->transactionLevel() < 1) {
            throw BillingSequenceException::transactionRequired();
        }

        // Only insert when the row is missing: an INSERT IGNORE on an existing, locked row
        // would take a shared lock and could deadlock with other issuers on the upgrade.
        $exists = BillingSequence::query()->where('key', $key)->where('period', $period)->exists();
        if (! $exists) {
            $now = Carbon::now();
            $connection->table('billing_sequences')->insertOrIgnore([
                'key' => $key,
                'period' => $period,
                'last_value' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        /** @var BillingSequence $sequence */
        $sequence = BillingSequence::query()
            ->where('key', $key)
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();

        $next = $sequence->last_value + 1;
        $sequence->last_value = $next;
        $sequence->save();

        return $next;
    }

    /**
     * Last value handed out for ($key, $period), 0 when none. A plain read for display and
     * tests; never use it to build a number (use next()).
     */
    public function current(string $key, string $period = ''): int
    {
        $this->assertValidKey($key, $period);

        return (int) BillingSequence::query()->where('key', $key)->where('period', $period)->value('last_value');
    }

    /**
     * Allocate the next SaaS invoice number, e.g. SUB-2026-00001 (format from
     * config('billing.invoice_number')). Same transaction rule as next().
     *
     * @throws BillingSequenceException on invalid configuration, before any number is consumed
     */
    public function nextInvoiceNumber(?DateTimeInterface $at = null): string
    {
        $prefix = $this->prefix();
        $pad = $this->pad();
        $period = $this->invoicePeriod($at ?? Carbon::now());

        $value = $this->next(self::INVOICE_SEQUENCE, $period);

        $parts = array_filter(
            [$prefix, $period, str_pad((string) $value, $pad, '0', STR_PAD_LEFT)],
            static fn (string $part): bool => $part !== '',
        );

        return implode('-', $parts);
    }

    /**
     * Period an invoice issued at $at belongs to: the year in the billing timezone, or ''
     * for one continuous counter.
     */
    public function invoicePeriod(DateTimeInterface $at): string
    {
        $period = config('billing.invoice_number.period');

        return match ($period) {
            self::PERIOD_YEARLY => Carbon::instance($at)->setTimezone($this->timezone())->format('Y'),
            self::PERIOD_NONE => '',
            default => throw BillingSequenceException::invalidConfiguration('billing.invoice_number.period'),
        };
    }

    private function assertValidKey(string $key, string $period): void
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1 || preg_match(self::PERIOD_PATTERN, $period) !== 1) {
            throw BillingSequenceException::invalidKey();
        }
    }

    private function prefix(): string
    {
        $prefix = config('billing.invoice_number.prefix');

        if ($prefix === null) {
            return '';
        }

        if (! is_string($prefix) || preg_match(self::PREFIX_PATTERN, $prefix) !== 1) {
            throw BillingSequenceException::invalidConfiguration('billing.invoice_number.prefix');
        }

        return $prefix;
    }

    private function pad(): int
    {
        $pad = config('billing.invoice_number.pad');

        if (! is_int($pad) || $pad < 1 || $pad > self::MAX_PAD) {
            throw BillingSequenceException::invalidConfiguration('billing.invoice_number.pad');
        }

        return $pad;
    }

    private function timezone(): DateTimeZone
    {
        $timezone = config('billing.timezone');

        try {
            if (! is_string($timezone) || $timezone === '') {
                throw new Exception('missing timezone');
            }

            return new DateTimeZone($timezone);
        } catch (Exception) {
            throw BillingSequenceException::invalidConfiguration('billing.timezone');
        }
    }
}
