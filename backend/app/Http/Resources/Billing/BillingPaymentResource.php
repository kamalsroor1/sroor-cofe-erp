<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Models\BillingPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A manual payment against a subscription invoice, for the shop (own payments) and the
 * super-admin review queue. The receipt path is never exposed: central staff open the file
 * through a signed link (GET .../receipt), the shop never re-downloads it.
 *
 * `tenant` / `invoice` / `reviewed_by` appear only when the relation is loaded (the central queue).
 *
 * @mixin BillingPayment
 */
final class BillingPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $raw = is_array($this->raw_payload) ? $this->raw_payload : [];

        return [
            'id' => (int) $this->id,
            'billing_invoice_id' => (int) $this->billing_invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice->number),
            'invoice_type' => $this->whenLoaded('invoice', fn () => $this->invoice->type->value),
            'invoice_total' => $this->whenLoaded('invoice', fn () => $this->invoice->total),
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant === null ? null : [
                'id' => (string) $this->tenant->getKey(),
                'name' => $this->tenant->name,
            ]),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => ['value' => $this->method->value, 'label' => $this->method->label()],
            'gateway' => $this->gateway->value,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'payer_reference' => isset($raw['payer_reference']) ? (string) $raw['payer_reference'] : null,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'submitted_at' => $this->created_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'reviewed_by' => $this->whenLoaded('verifier', fn () => $this->verifier === null ? null : [
                'id' => (int) $this->verifier->getKey(),
                'name' => $this->verifier->name,
            ]),
            'has_receipt' => $this->proof_path !== null && $this->proof_path !== '',
        ];
    }
}
