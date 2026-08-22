<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'company_name'      => $this->company_name,
            'phone'             => $this->phone,
            'address'           => $this->address,
            'current_balance'   => (float)$this->current_balance,
            'is_active'         => (bool)$this->is_active,
            'notes'             => $this->notes,
            'purchases_count'   => $this->whenCounted('purchases', $this->purchases_count),
            'payments_count'    => $this->whenCounted('payments', $this->payments_count),
            'can_be_deleted'    => method_exists($this->resource, 'canBeDeleted') ? $this->canBeDeleted() : true,
            'deletion_blockers' => method_exists($this->resource, 'getDeletionBlockers') ? $this->getDeletionBlockers() : [],
            'created_at'        => $this->created_at?->toDateTimeString(),
        ];
    }
}
