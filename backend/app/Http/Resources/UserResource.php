<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Auth\AccountLockout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'theme_preference' => $this->theme_preference ?? 'dark',
            'locale' => $this->resource instanceof User ? $this->resource->locale : null,
            'show_print_subtitle' => (bool) $this->show_print_subtitle,
            'default_store_id' => $this->default_store_id,
            'is_active' => (bool) $this->is_active,
            // SELL-01: true until the user picks their own password (EnsurePasswordChanged).
            'must_change_password' => $this->resource instanceof User && $this->resource->must_change_password === true,
            // BE-08: whether a manager approval PIN is set (the hash itself is never exposed).
            'has_pos_pin' => $this->resource instanceof User && $this->resource->pos_pin !== null,
            ...AccountLockout::payload($this->loginLockedUntil()),
            'last_login_at' => $this->last_login_at,
            'stores' => $this->whenLoaded('stores', function () {
                return $this->stores->map(fn ($store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'code' => $store->code,
                    'is_main' => (bool) $store->is_main,
                ]);
            }),
        ];
    }

    private function loginLockedUntil(): ?int
    {
        return $this->resource instanceof User
            ? AccountLockout::lockedUntilForIdentifiers($this->resource->phone, $this->resource->email)
            : null;
    }
}
