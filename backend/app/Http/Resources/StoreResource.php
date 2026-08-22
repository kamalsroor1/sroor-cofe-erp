<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'code'              => $this->code,
            'type'              => $this->type,
            'is_main'           => (bool)$this->is_main,
            'is_active'         => (bool)$this->is_active,
            'phone'             => $this->phone,
            'address'           => $this->address,
            'stocks_count'      => $this->whenCounted('stocks', $this->stocks_count),
            'invoices_count'    => $this->whenCounted('invoices', $this->invoices_count),
            'purchases_count'   => $this->whenCounted('purchases', $this->purchases_count),
            'assigned_user_ids' => $this->whenLoaded('users', fn() => $this->users->pluck('id')->toArray()),
            'assigned_users'    => $this->whenLoaded('users', fn() => $this->users->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
            ])),
            'can_be_deleted'    => method_exists($this->resource, 'canBeDeleted') ? $this->canBeDeleted() : true,
            'deletion_blockers' => method_exists($this->resource, 'getDeletionBlockers') ? $this->getDeletionBlockers() : [],
            'created_at'        => $this->created_at?->toDateTimeString(),
        ];
    }
}
