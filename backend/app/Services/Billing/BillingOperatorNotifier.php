<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\PlatformNotificationType;
use App\Jobs\NotifyOperatorsOfBillingEventJob;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\PlatformNotification;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * #232 spec E: tells the platform operators about a billing event on BOTH channels:
 *  - the panel (bell): a central `platform_notifications` row written in the caller's central
 *    transaction (it exists exactly when the event does), idempotent through `dedupe_key`;
 *  - e-mail: NotifyOperatorsOfBillingEventJob, dispatched AFTER COMMIT and queued (it resolves
 *    the recipients and queues one PlatformMailable per address). Receipt e-mails are
 *    deduplicated per invoice and 15-minute slot (security review S2).
 */
final class BillingOperatorNotifier
{
    /** Security review S2: at most one receipt e-mail per invoice in this many seconds. */
    public const RECEIPT_MAIL_SLOT_SECONDS = 900;

    /**
     * Every upload gets its own panel item. The e-mail is deduplicated per invoice and 15-minute
     * slot (security review S2): an upload -> withdraw -> upload loop on one invoice sends at most
     * one e-mail per slot, while a new proof after a quiet period (e.g. after a rejection) still
     * reaches the operators by e-mail. The check reads the panel items of the same invoice, in the
     * caller's transaction under the invoice row lock, so two concurrent uploads cannot both mail.
     */
    public function receiptUploaded(Tenant $tenant, BillingInvoice $invoice, BillingPayment $payment, ?CarbonImmutable $temporaryAccessEndsAt): PlatformNotification
    {
        $now = CarbonImmutable::now();
        $slotStart = CarbonImmutable::createFromTimestamp(
            intdiv($now->getTimestamp(), self::RECEIPT_MAIL_SLOT_SECONDS) * self::RECEIPT_MAIL_SLOT_SECONDS,
            $now->getTimezone(),
        );

        $mailedThisSlot = PlatformNotification::query()
            ->where('type', PlatformNotificationType::BillingReceiptUploaded->value)
            ->where('billing_invoice_id', (int) $invoice->getKey())
            ->where('created_at', '>=', $slotStart)
            ->exists();

        return $this->notify(PlatformNotificationType::BillingReceiptUploaded, 'receipt:'.$payment->getKey(), $tenant, $invoice, $payment, [
            'temporary_access' => $temporaryAccessEndsAt !== null,
            'temporary_access_ends_at' => $temporaryAccessEndsAt?->toIso8601String(),
            'uploaded_at' => $payment->created_at?->toIso8601String(),
        ], mail: ! $mailedThisSlot);
    }

    public function temporaryAccessReminder(Tenant $tenant, BillingInvoice $invoice, ?BillingPayment $payment): PlatformNotification
    {
        return $this->notify(PlatformNotificationType::BillingTemporaryAccessReminder, 'reminder:'.$invoice->getKey().':'.$invoice->temporary_access_started_at?->getTimestamp(), $tenant, $invoice, $payment, [
            'temporary_access' => true,
            'temporary_access_ends_at' => $invoice->temporary_access_ends_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  bool  $mail  false: panel item only (no operator e-mail)
     */
    private function notify(PlatformNotificationType $type, string $dedupeKey, Tenant $tenant, BillingInvoice $invoice, ?BillingPayment $payment, array $extra, bool $mail = true): PlatformNotification
    {
        $notification = PlatformNotification::query()->firstOrCreate(['dedupe_key' => $dedupeKey], [
            'type' => $type,
            'tenant_id' => (string) $tenant->getKey(),
            'billing_invoice_id' => (int) $invoice->getKey(),
            'billing_payment_id' => $payment === null ? null : (int) $payment->getKey(),
            'data' => [
                'tenant_name' => $tenant->name,
                'invoice_number' => $invoice->number,
                'invoice_type' => $invoice->type->value,
                'amount' => $payment === null ? $invoice->total : $payment->amount,
                'currency' => $payment === null ? $invoice->currency : $payment->currency,
                'method' => $payment?->method->value,
            ] + $extra,
        ]);

        if ($mail && $notification->wasRecentlyCreated) {
            NotifyOperatorsOfBillingEventJob::dispatch((int) $notification->getKey())->afterCommit();
        }

        return $notification;
    }
}
