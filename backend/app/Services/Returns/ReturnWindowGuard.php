<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Approvals\ManagerPinVerifier;
use App\Services\Settings\TenantSettings;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;

/**
 * SET-02: the sales-return window (`returns_max_days`, tenant setting; empty = no limit).
 *
 * A sales return against an invoice is accepted while
 *   (today's business date - the invoice's business date) <= returns_max_days
 * both on the tenant clock with `business_day_cutoff` (TenantClock), so "day N" ends at the
 * cutoff, not at the server's midnight. "Today" is the SERVER's business date, never the
 * client's `return_date` (a back-dated return_date cannot reopen the window).
 *
 * Past the window the return is refused (422 `returns.window_expired`) unless:
 *  - the acting user holds `returns.override_window` (admin holds it by default), or
 *  - the request carries a manager `override` {user_id, pin} whose user holds it, checked by the
 *    shared ManagerPinVerifier (same limiter buckets as every other manager-PIN flow).
 *
 * Out of scope on purpose: purchase returns (supplier side) and sales returns without an invoice
 * (no source date to measure; that path is already gated by `returns.without_invoice`, admin only).
 */
final class ReturnWindowGuard
{
    public const PERMISSION = 'returns.override_window';

    public function __construct(
        private readonly TenantSettings $settings,
        private readonly TenantClock $clock,
        private readonly ManagerPinVerifier $pinVerifier,
    ) {}

    /**
     * @param  array{user_id?: mixed, pin?: mixed}|null  $override
     * @return array{overridden: bool, approved_by: int|null, max_days: int, age_days: int, invoice_date: string}|null null = no window set
     *
     * @throws BusinessRuleException 422 returns.window_expired
     * @throws ValidationException wrong manager PIN (override.pin)
     * @throws ThrottleRequestsException too many wrong manager PINs
     */
    public function check(Invoice $invoice, ?User $actor, ?array $override): ?array
    {
        $maxDays = $this->settings->returnsMaxDays();
        if ($maxDays === null) {
            return null;
        }

        $invoiceDate = $this->invoiceBusinessDate($invoice);
        $today = $this->clock->businessDate();
        $age = (int) CarbonImmutable::parse($invoiceDate)->diffInDays(CarbonImmutable::parse($today), false);

        $decision = ['overridden' => false, 'approved_by' => null, 'max_days' => $maxDays, 'age_days' => $age, 'invoice_date' => $invoiceDate];

        if ($age <= $maxDays) {
            return $decision;
        }

        $decision['overridden'] = true;

        if ($actor instanceof User && $actor->can(self::PERMISSION)) {
            $decision['approved_by'] = (int) $actor->id;

            return $decision;
        }

        if ($override === null || ! isset($override['user_id'], $override['pin'])) {
            throw BusinessRuleException::make('returns.window_expired', ['days' => $maxDays]);
        }

        $approver = $this->pinVerifier->verify(
            (int) $override['user_id'],
            (string) $override['pin'],
            $actor,
            $invoice->store_id !== null ? (int) $invoice->store_id : null,
            static fn (User $candidate): bool => $candidate->can(self::PERMISSION),
        );
        $decision['approved_by'] = (int) $approver->id;

        return $decision;
    }

    /** The invoice's business date (Y-m-d); legacy rows without one use the business date of created_at. */
    private function invoiceBusinessDate(Invoice $invoice): string
    {
        if ($invoice->invoice_date !== null) {
            return $invoice->invoice_date->toDateString();
        }

        return $this->clock->businessDate($invoice->created_at ?? $this->clock->now());
    }
}
