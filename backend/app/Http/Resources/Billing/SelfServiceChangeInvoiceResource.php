<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\DTOs\Billing\SelfServiceChangeInvoiceResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POST /api/v1/billing/change-invoice result: `invoice` (the BillingInvoiceResource shape the shop
 * uses everywhere, payments loaded) or null when `held_for_price_review` (agreed founder price,
 * #232 spec C), plus the filed `request`.
 *
 * @property SelfServiceChangeInvoiceResult $resource
 */
final class SelfServiceChangeInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'held_for_price_review' => $this->resource->heldForPriceReview,
            'invoice' => $this->resource->invoice === null ? null : new BillingInvoiceResource($this->resource->invoice),
            'request' => new PlanChangeRequestResource($this->resource->request),
        ];
    }
}
