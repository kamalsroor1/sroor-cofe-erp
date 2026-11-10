<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingPaymentStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A SaaS subscription invoice (central `billing_invoices`) for the shop and the console.
 *
 * `lines` is the frozen snapshot; the display `description` is built here in the request
 * locale (plan lines) or taken from the stored text (service lines). `paid_amount`,
 * `remaining` and `payments` need the `payments` relation loaded. `tenant` and `issued_by_name`
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
            'remaining' => $this->whenLoaded('payments', fn () => bcsub($this->total, $this->paidAmount(), 3)),
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
        ];
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
            ];
        }

        return $lines;
    }
}
