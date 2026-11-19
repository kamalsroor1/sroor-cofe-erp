<?php

declare(strict_types=1);

namespace App\Http\Resources\Receipts;

use App\DTOs\Receipts\ReceiptLinkResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PRD-22: the signed public receipt link and when it stops working.
 *
 * @mixin ReceiptLinkResult
 */
final class ReceiptLinkResource extends JsonResource
{
    /**
     * @return array{url: string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        /** @var ReceiptLinkResult $result */
        $result = $this->resource;

        return $result->toArray();
    }
}
