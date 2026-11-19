<?php

declare(strict_types=1);

namespace App\Http\Resources\AccountDeletion;

use App\Models\AccountDeletionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MOB-05: an account-deletion request as the platform console sees it (no IP / user agent).
 *
 * @mixin AccountDeletionRequest
 */
final class AccountDeletionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccountDeletionRequest $row */
        $row = $this->resource;

        return [
            'id' => (int) $row->getKey(),
            'contact_name' => $row->contact_name,
            'phone' => $row->phone,
            'email' => $row->email,
            'shop_code' => $row->shop_code,
            'reason' => $row->reason,
            'locale' => $row->locale,
            'status' => $row->status->value,
            'handled_at' => $row->handled_at?->toIso8601String(),
            'handled_note' => $row->handled_note,
            'handled_by_name' => $row->relationLoaded('handler') ? $row->handler?->name : null,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
