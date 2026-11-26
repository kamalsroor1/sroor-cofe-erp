<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\InvoiceReviewState;
use App\Enums\Billing\ReceiptClassification;
use App\Enums\Billing\TemporaryAccessOutcome;
use App\Enums\Billing\TemporaryAccessState;
use App\Enums\CentralAuditEvent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * #232 spec D: the 48-hour temporary activation, shared by the payment flows (submit, verify,
 * reject, void, the reminder) and the read side (shop API).
 *
 * State machine of ONE invoice's window (billing_invoices.temporary_access_*):
 *
 *   none ──open()──▶ active ──(time passes ends_at)──▶ expired
 *                      │                                  │
 *                      └──────────settle(outcome)─────────┴──▶ verified | rejected | voided
 *
 *  - open(): only for a proof classified `transfer_receipt_image`, on a plan / renewal / upgrade /
 *    add-on invoice (a `service` invoice activates nothing), when the invoice never had a window
 *    (one per invoice, never reset by a re-upload or a withdraw) and the tenant holds no OTHER
 *    unsettled window (active or expired). The end is copied onto tenants.temporary_access_ends_at,
 *    which the lifecycle policy reads: full access on the CURRENT plan until then (the plan and
 *    the paid term are not touched, so upgrade features wait for Verify).
 *  - settle(): a super-admin verify that settles the invoice (verified), a super-admin reject of
 *    any receipt of the invoice (rejected), or a void (voided). Clears the tenant copy, so access
 *    is back to the lifecycle state at once; data and a running paid term were never changed.
 *    A shop withdrawing its own receipt settles nothing. Expiry is not a settlement: an expired
 *    window still blocks a new one until the review decides.
 *
 * Callers hold the locks (billing order tenant -> payment -> invoice) inside a central transaction.
 */
final class TemporaryAccessService
{
    /** Settled windows stay in the shop's subscription payload this long (confirmed / rejected banner). */
    private const SETTLED_VISIBLE_DAYS = 7;

    public const NOT_OPENED_NOT_RECEIPT = 'not_receipt';

    public const NOT_OPENED_INVOICE_TYPE = 'invoice_type';

    public const NOT_OPENED_INVOICE_WINDOW_USED = 'invoice_window_used';

    public const NOT_OPENED_TENANT_WINDOW_UNSETTLED = 'tenant_window_unsettled';

    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function hours(): int
    {
        return max(1, (int) config('billing.temporary_access.hours', 48));
    }

    public function reminderSeconds(): int
    {
        return max(0, (int) config('billing.temporary_access.reminder_hours_before', 6)) * 3600;
    }

    /**
     * Open the invoice's window when every rule allows it. Returns null when it was opened, else
     * the reason it was not (one of the NOT_OPENED_* codes).
     */
    public function open(Tenant $tenant, BillingInvoice $invoice, BillingPayment $payment, CarbonImmutable $now): ?string
    {
        $this->assertInTransaction();

        if ($payment->receipt_classification !== ReceiptClassification::TransferReceiptImage) {
            return self::NOT_OPENED_NOT_RECEIPT;
        }

        if ($invoice->type === BillingInvoiceType::Service) {
            return self::NOT_OPENED_INVOICE_TYPE;
        }

        if ($invoice->temporary_access_started_at !== null) {
            return self::NOT_OPENED_INVOICE_WINDOW_USED;
        }

        $otherUnsettled = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKeyNot($invoice->getKey())
            ->whereNotNull('temporary_access_started_at')
            ->whereNull('temporary_access_settled_at')
            ->exists();

        if ($otherUnsettled) {
            return self::NOT_OPENED_TENANT_WINDOW_UNSETTLED;
        }

        $endsAt = $now->addHours($this->hours());

        $invoice->temporary_access_started_at = Carbon::instance($now);
        $invoice->temporary_access_ends_at = Carbon::instance($endsAt);
        $invoice->temporary_access_payment_id = (int) $payment->getKey();
        $invoice->save();

        $tenant->temporary_access_ends_at = Carbon::instance($endsAt);
        $tenant->save();

        return null;
    }

    /**
     * Settle the invoice's window with $outcome when it is open or expired-unsettled. Returns
     * whether something was settled (false: no window, or already settled).
     */
    public function settle(Tenant $tenant, BillingInvoice $invoice, TemporaryAccessOutcome $outcome, ?CentralUser $operator, CarbonImmutable $now): bool
    {
        $this->assertInTransaction();

        if (! $invoice->hasUnsettledTemporaryAccess()) {
            return false;
        }

        $invoice->temporary_access_settled_at = Carbon::instance($now);
        $invoice->temporary_access_outcome = $outcome;
        $invoice->save();

        // At most one unsettled window per tenant: the tenant copy belongs to this one.
        if ($tenant->temporary_access_ends_at !== null) {
            $tenant->temporary_access_ends_at = null;
            $tenant->save();
        }

        $this->audit->record(
            CentralAuditEvent::BillingTemporaryAccessSettled,
            [
                'invoice_id' => (int) $invoice->getKey(),
                'invoice_number' => $invoice->number,
                'outcome' => $outcome->value,
                'started_at' => $invoice->temporary_access_started_at?->toIso8601String(),
                'ends_at' => $invoice->temporary_access_ends_at?->toIso8601String(),
                'expired_before_settlement' => $invoice->temporary_access_ends_at !== null && ! $now->lessThan($invoice->temporary_access_ends_at),
            ],
            $operator,
            $invoice,
            (string) $invoice->tenant_id,
        );

        return true;
    }

    /**
     * The invoice's window for the API (shop invoice screen, console queue).
     *
     * @return array{state: string, started_at: string|null, ends_at: string|null, seconds_left: int, last_hours: bool, payment_id: int|null, settled_at: string|null}
     */
    public function invoiceWindow(BillingInvoice $invoice, CarbonImmutable $now): array
    {
        $state = $invoice->temporaryAccessState($now);
        $secondsLeft = $state === TemporaryAccessState::Active && $invoice->temporary_access_ends_at !== null
            ? max(0, $invoice->temporary_access_ends_at->getTimestamp() - $now->getTimestamp())
            : 0;

        return [
            'state' => $state->value,
            'started_at' => $invoice->temporary_access_started_at?->toIso8601String(),
            'ends_at' => $invoice->temporary_access_ends_at?->toIso8601String(),
            'seconds_left' => $secondsLeft,
            'last_hours' => $state === TemporaryAccessState::Active && $secondsLeft <= $this->reminderSeconds(),
            'payment_id' => $invoice->temporary_access_payment_id,
            'settled_at' => $invoice->temporary_access_settled_at?->toIso8601String(),
        ];
    }

    /**
     * UI §2: the status card of an invoice. Needs the `payments` relation loaded.
     */
    public function reviewState(BillingInvoice $invoice, CarbonImmutable $now): InvoiceReviewState
    {
        if ($invoice->status === BillingInvoiceStatus::Paid) {
            return InvoiceReviewState::Paid;
        }
        if ($invoice->status === BillingInvoiceStatus::Void) {
            return InvoiceReviewState::Void;
        }

        /** @var BillingPayment|null $latest */
        $latest = $invoice->payments->sortByDesc('id')->first();

        if (! $latest instanceof BillingPayment) {
            return InvoiceReviewState::AwaitingPayment;
        }

        if ($latest->status === BillingPaymentStatus::Pending) {
            if ($invoice->temporaryAccessState($now) === TemporaryAccessState::Active) {
                return (int) $invoice->temporary_access_payment_id === (int) $latest->getKey()
                    ? InvoiceReviewState::UnderReviewTemporary
                    : InvoiceReviewState::UnderReviewGraceUsed;
            }

            return $latest->receipt_classification === ReceiptClassification::NotReceipt
                ? InvoiceReviewState::UnderReviewManual
                : InvoiceReviewState::UnderReview;
        }

        // A receipt the shop withdrew itself (no operator) is not a rejection.
        if ($latest->status === BillingPaymentStatus::Rejected && $latest->verified_by !== null) {
            return InvoiceReviewState::Rejected;
        }

        return InvoiceReviewState::AwaitingPayment;
    }

    /**
     * UI §3 banner data for GET /billing/subscription: the tenant's unsettled window, else the most
     * recently settled one (within SETTLED_VISIBLE_DAYS), else null.
     *
     * @return array<string, mixed>|null
     */
    public function tenantWindow(Tenant $tenant, CarbonImmutable $now): ?array
    {
        $invoice = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereNotNull('temporary_access_started_at')
            ->whereNull('temporary_access_settled_at')
            ->orderByDesc('id')
            ->first()
            ?? BillingInvoice::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereNotNull('temporary_access_settled_at')
                ->where('temporary_access_settled_at', '>=', $now->subDays(self::SETTLED_VISIBLE_DAYS))
                ->orderByDesc('temporary_access_settled_at')
                ->orderByDesc('id')
                ->first();

        if (! $invoice instanceof BillingInvoice) {
            return null;
        }

        $window = $this->invoiceWindow($invoice, $now);
        $rejection = null;

        if ($window['state'] === TemporaryAccessState::Rejected->value) {
            $rejection = BillingPayment::query()
                ->where('billing_invoice_id', $invoice->getKey())
                ->where('status', BillingPaymentStatus::Rejected->value)
                ->whereNotNull('verified_by')
                ->orderByDesc('id')
                ->value('rejection_reason');
        }

        return $window + [
            'invoice_id' => (int) $invoice->getKey(),
            'invoice_number' => $invoice->number,
            'invoice_type' => $invoice->type->value,
            'invoice_has_pending_payment' => BillingPayment::query()
                ->where('billing_invoice_id', $invoice->getKey())
                ->where('status', BillingPaymentStatus::Pending->value)
                ->exists(),
            'rejection_reason' => is_string($rejection) ? $rejection : null,
        ];
    }

    private function assertInTransaction(): void
    {
        if (DB::connection((string) (new BillingInvoice)->getConnectionName())->transactionLevel() < 1) {
            throw new LogicException('TemporaryAccessService must run inside a central transaction.');
        }
    }
}
