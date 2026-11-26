<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\DTOs\Billing\VerificationQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GET .../billing/payments/{payment}/verify-preview: the amounts Verify would settle (#232).
 *
 * @property VerificationQuote $resource
 */
final class VerificationQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
