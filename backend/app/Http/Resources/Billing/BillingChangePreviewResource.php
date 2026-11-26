<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\DTOs\Billing\BillingChangePreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GET /api/v1/billing/change-preview (#232 UI spec 1.3). Built by PreviewBillingChangeAction.
 *
 * @property BillingChangePreview $resource
 */
final class BillingChangePreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
