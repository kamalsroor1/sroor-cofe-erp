<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Enums\PlatformNotificationType;
use App\Models\PlatformNotification;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #232 UI §4.2: one super-admin panel notification item (the bell). `title` / `body` are built in
 * the request locale from `billing.console.notifications.*`; `facts` keeps the raw facts (not `data`: that key would disable the response wrapper); `link`
 * names the console page to open (the payment in the review queue). `is_read` is for the caller.
 *
 * @mixin PlatformNotification
 */
final class PlatformNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];
        $tenant = (string) ($data['tenant_name'] ?? '');

        return [
            'id' => (int) $this->id,
            'type' => $this->type->value,
            'severity' => $this->type === PlatformNotificationType::BillingTemporaryAccessReminder ? 'warning' : 'info',
            'title' => match ($this->type) {
                PlatformNotificationType::BillingReceiptUploaded => (string) __('billing.console.notifications.receipt_title', ['tenant' => $tenant]),
                PlatformNotificationType::BillingTemporaryAccessReminder => (string) __('billing.console.notifications.reminder_title', ['tenant' => $tenant]),
            },
            'body' => match ($this->type) {
                PlatformNotificationType::BillingReceiptUploaded => (string) __('billing.console.notifications.receipt_body', [
                    'amount' => trim(($data['amount'] ?? '').' '.($data['currency'] ?? '')),
                    'method' => isset($data['method']) && is_string($data['method']) ? (string) __('billing.billing_payment_method.'.$data['method']) : '',
                    'access' => (bool) ($data['temporary_access'] ?? false)
                        ? (string) __('billing.console.notifications.access_grace')
                        : (string) __('billing.console.notifications.access_manual'),
                    'ago' => $this->created_at?->locale(app()->getLocale())->diffForHumans(['parts' => 1, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]) ?? '',
                ]),
                PlatformNotificationType::BillingTemporaryAccessReminder => (string) __('billing.console.notifications.reminder_body', [
                    'time' => self::clock($data['temporary_access_ends_at'] ?? null),
                ]),
            },
            'tenant' => ['id' => $this->tenant_id, 'name' => $tenant],
            'billing_invoice_id' => $this->billing_invoice_id,
            'billing_payment_id' => $this->billing_payment_id,
            'facts' => [
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_type' => $data['invoice_type'] ?? null,
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? null,
                'method' => $data['method'] ?? null,
                'temporary_access' => (bool) ($data['temporary_access'] ?? false),
                'temporary_access_ends_at' => $data['temporary_access_ends_at'] ?? null,
            ],
            'link' => ['route' => 'super_admin.billing', 'query' => ['payment' => $this->billing_payment_id]],
            'is_read' => (bool) ($this->resource->getAttribute('is_read') ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** An ISO instant on the billing clock (config billing.timezone), e.g. 2026-11-26 14:30. */
    private static function clock(mixed $iso): string
    {
        if (! is_string($iso) || $iso === '') {
            return '';
        }

        return CarbonImmutable::parse($iso)->setTimezone((string) config('billing.timezone', 'Africa/Cairo'))->format('Y-m-d H:i');
    }
}
