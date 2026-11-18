<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{limit: int|null, used: int, remaining: int|null, percent: int, reached: bool, resets_at: string|null} $resource
 */
final class PosInvoiceLimitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'limit' => $this->resource['limit'],
            'used' => $this->resource['used'],
            'remaining' => $this->resource['remaining'],
            'percent' => $this->resource['percent'],
            'reached' => $this->resource['reached'],
            'resets_at' => $this->resource['resets_at'],
        ];
    }
}
