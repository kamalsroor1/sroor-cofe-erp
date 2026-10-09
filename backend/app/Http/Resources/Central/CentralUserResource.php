<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Enums\CentralPermission;
use App\Models\CentralUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A platform operator as seen by the central console. Never exposes the password hash,
 * 2FA secret / recovery codes, remember token or last login IP.
 *
 * @mixin CentralUser
 */
final class CentralUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CentralUser $user */
        $user = $this->resource;

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'roles' => $user->roles->where('guard_name', CentralPermission::GUARD)->pluck('name')->values()->all(),
            'permissions' => $user->getAllPermissions()
                ->where('guard_name', CentralPermission::GUARD)
                ->pluck('name')
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ];
    }
}
