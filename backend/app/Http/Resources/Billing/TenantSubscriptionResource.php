<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GET /api/v1/billing/subscription: the shop's plan, status, usage against limits, add-ons,
 * open invoices and how to pay. Built by GetTenantSubscriptionAction; this only fixes the shape.
 *
 * @property array<string, mixed> $resource
 */
final class TenantSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
