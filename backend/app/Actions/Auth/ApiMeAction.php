<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Http\Resources\UserResource;
use App\Models\CashShift;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStore;
use Illuminate\Http\Request;

final class ApiMeAction
{
    /**
     * Retrieve authenticated user data, stores, active shift, and preferences
     */
    public function execute(User $user, Request $request): array
    {
        $user->loadMissing('stores');

        $activeStore = null;
        // /auth/me ignores a stale X-Store-Id in ApiTokenAuth (so the SPA can recover), hence the
        // header is honoured here only for a store the user may access; else the user's own store.
        $storeHeader = ActiveStore::parseHeader($request->header('X-Store-Id'));
        if (is_int($storeHeader) && ActiveStore::canAccess($user, $storeHeader)) {
            $activeStore = Store::where('id', $storeHeader)->where('is_active', true)->first();
        }

        if (! $activeStore) {
            $activeStore = $user->getCurrentStore();
        }

        $userStores = $user->hasRole('admin')
            ? Store::where('is_active', true)->orderBy('is_main', 'desc')->get(['id', 'name', 'code', 'type', 'is_main'])
            : $user->stores()->where('is_active', true)->get(['stores.id', 'name', 'code', 'type', 'is_main']);

        $activeShift = null;
        if ($activeStore) {
            $activeShift = CashShift::where('store_id', $activeStore->id)
                ->where('status', 'open')
                ->latest('id')
                ->first();
        }

        return [
            'user' => (new UserResource($user))->resolve(),
            'store' => $activeStore ? [
                'id' => $activeStore->id,
                'name' => $activeStore->name,
                'code' => $activeStore->code,
                'type' => $activeStore->type,
                'is_main' => (bool) $activeStore->is_main,
            ] : null,
            'stores' => $userStores,
            'active_shift' => $activeShift ? [
                'id' => $activeShift->id,
                'shift_number' => $activeShift->shift_number ?? $activeShift->id,
                'opened_at' => $activeShift->opened_at,
                'opening_cash_balance' => (float) $activeShift->opening_cash_balance,
            ] : null,
            'system' => [
                'company_name' => Setting::get('company_name') ?: (function_exists('tenant') && tenant('name') ? tenant('name') : __('auth.default_company_name')),
                'company_subtitle' => Setting::get('company_subtitle') ?: '',
                'system_theme' => Setting::get('system_theme_color', 'emerald'),
                'server_time' => now()->toDateTimeString(),
            ],
        ];
    }
}
