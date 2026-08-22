<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Store;
use App\Models\Setting;
use App\Models\CashShift;
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
        $storeHeader = $request->header('X-Store-Id');
        if ($storeHeader && is_numeric($storeHeader)) {
            $activeStore = Store::where('id', (int)$storeHeader)->where('is_active', true)->first();
        }

        if (!$activeStore) {
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
                'id'      => $activeStore->id,
                'name'    => $activeStore->name,
                'code'    => $activeStore->code,
                'type'    => $activeStore->type,
                'is_main' => (bool)$activeStore->is_main,
            ] : null,
            'stores' => $userStores,
            'active_shift' => $activeShift ? [
                'id'                   => $activeShift->id,
                'shift_number'         => $activeShift->shift_number ?? $activeShift->id,
                'opened_at'            => $activeShift->opened_at,
                'opening_cash_balance' => (float)$activeShift->opening_cash_balance,
            ] : null,
            'system' => [
                'company_name'     => Setting::get('company_name', 'سرور كوفي'),
                'company_subtitle' => Setting::get('company_subtitle', 'لتوريدات خامات مطاحن البن'),
                'system_theme'     => Setting::get('system_theme_color', 'amber'),
                'server_time'      => now()->toDateTimeString(),
            ],
        ];
    }
}
