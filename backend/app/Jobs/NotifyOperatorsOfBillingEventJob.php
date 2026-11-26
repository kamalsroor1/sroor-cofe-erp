<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PlatformNotificationType;
use App\Mail\BillingReceiptUploadedMail;
use App\Mail\BillingTemporaryAccessReminderMail;
use App\Mail\PlatformMailable;
use App\Models\PlatformNotification;
use App\Services\Billing\BillingOperatorRecipients;
use App\Support\PlatformHosts;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * #232 spec E: e-mails the platform operators about one panel notification item (receipt uploaded,
 * 6-hour temporary-access reminder). Dispatched after commit by BillingOperatorNotifier; carries
 * only the notification id. Runs in CENTRAL context (a job queued from a shop request arrives with
 * the tenant initialised: it is ended for the work and restored after). One queued
 * PlatformMailable per address, so operators never see each other's address. No receipt is
 * attached. Best effort: a failure is logged (never the addresses) and does not throw.
 */
final class NotifyOperatorsOfBillingEventJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $notificationId) {}

    public function handle(BillingOperatorRecipients $recipients): void
    {
        $tenancy = tenancy();
        $previous = $tenancy->initialized ? $tenancy->tenant : null;

        if ($previous !== null) {
            $tenancy->end();
        }

        try {
            $this->send($recipients);
        } catch (Throwable $e) {
            Log::warning('Billing operator e-mail could not be queued', ['notification_id' => $this->notificationId, 'exception' => $e::class]);
        } finally {
            if ($previous !== null) {
                $tenancy->initialize($previous);
            }
        }
    }

    private function send(BillingOperatorRecipients $recipients): void
    {
        $notification = PlatformNotification::query()->find($this->notificationId);
        if (! $notification instanceof PlatformNotification) {
            return;
        }

        $emails = $recipients->emails();
        if ($emails === []) {
            Log::warning('Billing operator e-mail skipped: no recipient configured', ['notification_id' => $this->notificationId]);

            return;
        }

        $locale = (string) config('billing.admin_notifications.locale', 'ar');

        foreach ($emails as $email) {
            Mail::to($email)->queue($this->mailable($notification, $locale));
        }
    }

    private function mailable(PlatformNotification $notification, string $locale): PlatformMailable
    {
        $data = $notification->data ?? [];
        $url = PlatformHosts::consoleUrl('/super-admin/billing?payment='.(int) $notification->billing_payment_id);
        $method = isset($data['method']) && is_string($data['method']) ? $data['method'] : null;

        return match ($notification->type) {
            PlatformNotificationType::BillingReceiptUploaded => new BillingReceiptUploadedMail(
                tenantName: (string) ($data['tenant_name'] ?? ''),
                invoiceNumber: (string) ($data['invoice_number'] ?? ''),
                invoiceType: (string) ($data['invoice_type'] ?? ''),
                amount: (string) ($data['amount'] ?? '0.000'),
                currency: (string) ($data['currency'] ?? ''),
                method: (string) $method,
                uploadedAt: self::display($data['uploaded_at'] ?? null) ?? self::display($notification->created_at?->toIso8601String()) ?? '',
                temporaryAccessEndsAt: self::display($data['temporary_access_ends_at'] ?? null),
                reviewUrl: $url,
                locale: $locale,
            ),
            PlatformNotificationType::BillingTemporaryAccessReminder => new BillingTemporaryAccessReminderMail(
                tenantName: (string) ($data['tenant_name'] ?? ''),
                invoiceNumber: (string) ($data['invoice_number'] ?? ''),
                amount: (string) ($data['amount'] ?? '0.000'),
                currency: (string) ($data['currency'] ?? ''),
                method: $method,
                endsAt: self::display($data['temporary_access_ends_at'] ?? null) ?? '',
                reviewUrl: $url,
                locale: $locale,
            ),
        };
    }

    /** An ISO 8601 instant shown on the billing clock (config billing.timezone), e.g. 2026-11-26 14:30. */
    private static function display(mixed $iso): ?string
    {
        if (! is_string($iso) || $iso === '') {
            return null;
        }

        return CarbonImmutable::parse($iso)->setTimezone((string) config('billing.timezone', 'Africa/Cairo'))->format('Y-m-d H:i');
    }
}
