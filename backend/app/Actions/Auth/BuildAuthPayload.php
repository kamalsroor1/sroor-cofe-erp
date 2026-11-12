<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Http\Resources\UserResource;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\Branding\TenantBranding;

/**
 * Shared login response payload (token + user + store context + system branding)
 * used by ApiLoginAction and QuickLoginAction so both return the same shape.
 */
final class BuildAuthPayload
{
    /**
     * @return array{token: string, must_change_password: bool, user: array<string, mixed>, store: array<string, mixed>|null, stores: mixed, system: array<string, mixed>}
     */
    public function execute(User $user, string $plainTextToken): array
    {
        $currentStore = null;
        $userStores = [];
        $tenant = function_exists('tenant') ? tenant() : null;

        // Store context is only applicable in tenant context.
        if ($tenant) {
            $currentStore = $user->getCurrentStore();
            $userStores = $user->hasRole('admin')
                ? Store::where('is_active', true)->orderBy('is_main', 'desc')->get(['id', 'name', 'code', 'type', 'is_main'])
                : $user->stores()->where('is_active', true)->get(['stores.id', 'name', 'code', 'type', 'is_main']);
        }

        $tenantName = $tenant ? tenant('name') : null;

        return [
            'token' => $plainTextToken,
            // SELL-01: the SPA must route straight to the change-password screen when true.
            'must_change_password' => $user->must_change_password === true,
            'user' => (new UserResource($user))->resolve(),
            'store' => $currentStore ? [
                'id' => $currentStore->id,
                'name' => $currentStore->name,
                'code' => $currentStore->code,
                'type' => $currentStore->type,
                'is_main' => (bool) $currentStore->is_main,
            ] : null,
            'stores' => $userStores,
            'system' => [
                'company_name' => Setting::get('company_name') ?: ($tenantName ?: __('auth.default_company_name')),
                'company_subtitle' => Setting::get('company_subtitle') ?: '',
                'system_theme' => Setting::get('system_theme_color', TenantBranding::DEFAULT_THEME_COLOR),
                'server_time' => now()->toDateTimeString(),
            ],
        ];
    }
}
