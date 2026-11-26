<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\FounderPriceStep;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Services\Billing\TemporaryAccessService;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A SaaS subscription invoice (central `billing_invoices`) for the shop and the console.
 *
 * `lines` is the frozen snapshot; the display `description` is built here in the request
 * locale (plan lines) or taken from the stored text (service lines). `paid_amount`,
 * `remaining` (never below zero) and `payments` need the `payments` relation loaded.
 * `overpaid_amount` is what was paid above a verify-time re-priced total (policy open). `tenant` and `issued_by_name`
 * appear only when loaded (console).
 *
 * @mixin BillingInvoice
 */
final class BillingInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'number' => $this->number,
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant === null ? null : [
                'id' => (string) $this->tenant->getKey(),
                'name' => $this->tenant->name,
            ]),
            'type' => ['value' => $this->type->value, 'label' => $this->type->label()],
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'billing_cycle' => $this->billing_cycle === null ? null : [
                'value' => $this->billing_cycle->value,
                'label' => $this->billing_cycle->label(),
            ],
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'lines' => $this->presentLines(),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax_rate' => $this->tax_rate,
            'tax' => $this->tax,
            'total' => $this->total,
            'currency' => $this->currency,
            'paid_amount' => $this->whenLoaded('payments', fn () => $this->paidAmount()),
            // Code review R2: never negative. A verify-time reprice can leave the verified payments
            // above the final total; that excess is `overpaid_amount` (refund / credit policy open).
            'remaining' => $this->whenLoaded('payments', fn () => $this->remaining()),
            'overpaid_amount' => Decimal::normalize($this->overpaid_amount),
            'has_pending_payment' => $this->whenLoaded('payments', fn () => $this->payments->contains(
                static fn (BillingPayment $payment): bool => $payment->status === BillingPaymentStatus::Pending,
            )),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            // Internal operator notes: console only (the issuer relation is loaded there), never the shop.
            'notes' => $this->when($this->resource->relationLoaded('issuer'), fn () => $this->notes),
            'issued_by_name' => $this->whenLoaded('issuer', fn () => $this->issuer?->name),
            'payments' => BillingPaymentResource::collection($this->whenLoaded('payments')),
            // #232 spec D: the invoice's one 48-hour temporary-access window (state none | active |
            // expired | verified | rejected | voided), and the UI §2 status card (payments loaded only).
            'temporary_access' => $this->temporaryAccess()->invoiceWindow($this->resource, CarbonImmutable::now()),
            'review_state' => $this->whenLoaded('payments', fn () => $this->temporaryAccess()->reviewState($this->resource, CarbonImmutable::now())->value),
        ];
    }

    private function temporaryAccess(): TemporaryAccessService
    {
        return app(TemporaryAccessService::class);
    }

    /** Total minus the verified payments, clamped at zero (bcmath, scale 3). */
    private function remaining(): string
    {
        $remaining = bcsub((string) $this->total, $this->paidAmount(), 3);

        return bccomp($remaining, '0', 3) < 0 ? '0.000' : $remaining;
    }

    private function paidAmount(): string
    {
        $paid = '0.000';

        foreach ($this->payments as $payment) {
            if ($payment->status === BillingPaymentStatus::Verified) {
                $paid = bcadd($paid, $payment->amount, 3);
            }
        }

        return $paid;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentLines(): array
    {
        $lines = [];

        foreach ($this->lines as $line) {
            // A support-decided difference line carries its own stored description.
            $isPlan = ($line['type'] ?? null) === 'plan' && ! ($line['difference'] ?? false);
            $cycle = $isPlan ? BillingCycle::tryFrom((string) ($line['billing_cycle'] ?? '')) : null;

            $lines[] = [
                'type' => (string) ($line['type'] ?? 'service'),
                'description' => $isPlan
                    ? (string) __('billing.invoice.plan_line', ['plan' => (string) ($line['plan_name'] ?? ''), 'cycle' => $cycle?->label() ?? ''])
                    : (string) ($line['description'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 1),
                'unit_price' => (string) ($line['unit_price'] ?? '0.000'),
                'amount' => (string) ($line['amount'] ?? '0.000'),
                'founder' => (bool) ($line['founder'] ?? false),
                // PRICE-2026-11: founder | transition | list; null on non-plan lines and on plan
                // lines issued before the price schedule (their `founder` flag still tells).
                'price_step' => $this->priceStep($line, $isPlan),
                // #232 spec C: billed at the owner-agreed price typed when a founder hold was released.
                'agreed_price' => ($line['agreed_price'] ?? false) === true,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{value: string, label: string}|null
     */
    private function priceStep(array $line, bool $isPlan): ?array
    {
        $step = $isPlan ? FounderPriceStep::tryFrom((string) ($line['price_step'] ?? '')) : null;

        return $step === null ? null : ['value' => $step->value, 'label' => $step->label()];
    }
}
