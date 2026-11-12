<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Store
 */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // SEC-21: the management fields (who works in the branch and their e-mails, document
        // counters, why it cannot be deleted) are for store managers only. A cashier listing the
        // branch switcher (GET /stores) gets the identity of the branch, nothing else.
        $manages = self::managesStores($request->user());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'is_main' => (bool) $this->is_main,
            'is_active' => (bool) $this->is_active,
            'phone' => $this->phone,
            'address' => $this->address,
            'stocks_count' => $this->when($manages, fn () => $this->whenCounted('stocks', $this->stocks_count)),
            'invoices_count' => $this->when($manages, fn () => $this->whenCounted('invoices', $this->invoices_count)),
            'purchases_count' => $this->when($manages, fn () => $this->whenCounted('purchases', $this->purchases_count)),
            'assigned_user_ids' => $this->when($manages, fn () => $this->whenLoaded('users', fn () => $this->users->pluck('id')->toArray())),
            'assigned_users' => $this->when($manages, fn () => $this->whenLoaded('users', fn () => $this->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]))),
            'can_be_deleted' => $this->when($manages, fn () => method_exists($this->resource, 'canBeDeleted') ? $this->canBeDeleted() : true),
            'deletion_blockers' => $this->when($manages, fn () => method_exists($this->resource, 'getDeletionBlockers') ? $this->getDeletionBlockers() : []),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    private static function managesStores(mixed $user): bool
    {
        return $user instanceof User && ($user->hasRole('admin') || $user->can('stores.manage'));
    }
}
