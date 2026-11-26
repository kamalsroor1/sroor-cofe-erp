<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Models\BillingPayment;
use App\Services\Billing\TemporaryAccessService;
use Carbon\CarbonImmutable;
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
            // #232: paid above the final (re-priced) total when the invoice settled; refund policy open.
            'invoice_overpaid_amount' => $this->whenLoaded('invoice', fn () => $this->invoice->overpaid_amount),
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
            // #232 spec A: the actual transfer moment the operator entered at Verify.
            'transferred_at' => $this->transferred_at?->toIso8601String(),
            // #232 review: the verified transfer bought a term that had already ended (verified late).
            'term_already_ended' => ($raw['term_already_ended'] ?? false) === true,
            'submitted_at' => $this->created_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'reviewed_by' => $this->whenLoaded('verifier', fn () => $this->verifier === null ? null : [
                'id' => (int) $this->verifier->getKey(),
                'name' => $this->verifier->name,
            ]),
            'has_receipt' => $this->proof_path !== null && $this->proof_path !== '',
            // #232 spec D: the receipt classifier verdict (null for operator-recorded payments).
            'receipt_classification' => $this->receipt_classification === null ? null : [
                'value' => $this->receipt_classification->value,
                'label' => $this->receipt_classification->label(),
                'reason' => $this->receipt_classification_reason,
            ],
            // #232 UI §4.1: the invoice's 48h window, for the console queue chip (invoice loaded only).
            'invoice_temporary_access' => $this->whenLoaded('invoice', fn () => app(TemporaryAccessService::class)->invoiceWindow($this->invoice, CarbonImmutable::now())),
        ];
    }
}
