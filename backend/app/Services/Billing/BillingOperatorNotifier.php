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
 *    the recipients and queues one PlatformMailable per address).
 */
final class BillingOperatorNotifier
{
    public function receiptUploaded(Tenant $tenant, BillingInvoice $invoice, BillingPayment $payment, ?CarbonImmutable $temporaryAccessEndsAt): PlatformNotification
    {
        return $this->notify(PlatformNotificationType::BillingReceiptUploaded, 'receipt:'.$payment->getKey(), $tenant, $invoice, $payment, [
            'temporary_access' => $temporaryAccessEndsAt !== null,
            'temporary_access_ends_at' => $temporaryAccessEndsAt?->toIso8601String(),
            'uploaded_at' => $payment->created_at?->toIso8601String(),
        ]);
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
     */
    private function notify(PlatformNotificationType $type, string $dedupeKey, Tenant $tenant, BillingInvoice $invoice, ?BillingPayment $payment, array $extra): PlatformNotification
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

        if ($notification->wasRecentlyCreated) {
            NotifyOperatorsOfBillingEventJob::dispatch((int) $notification->getKey())->afterCommit();
        }

        return $notification;
    }
}
