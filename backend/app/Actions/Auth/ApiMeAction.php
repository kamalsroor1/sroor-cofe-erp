<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\TenantAccessLevel;
use App\Http\Resources\UserResource;
use App\Models\CashShift;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Branding\TenantBranding;
use App\Support\ActiveStore;
use App\Support\Money\Decimal;
use App\Support\Tenancy\TenantLifecyclePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class ApiMeAction
{
    public function __construct(private readonly TenantLifecyclePolicy $lifecycle) {}

    /**
     * Retrieve authenticated user data, stores, active shift, and preferences
     */
    public function execute(User $user, Request $request): array
    {
        $system = [
            'company_name' => Setting::get('company_name') ?: (function_exists('tenant') && tenant('name') ? tenant('name') : __('auth.default_company_name')),
            'company_subtitle' => Setting::get('company_subtitle') ?: '',
            'system_theme' => Setting::get('system_theme_color', TenantBranding::DEFAULT_THEME_COLOR),
            'server_time' => now()->toDateTimeString(),
        ];

        $subscription = $this->subscriptionBlock();

        // SEC-01: a blocked tenant's session only learns who it is and why it is blocked.
        if ($subscription !== null && $subscription['access'] === TenantAccessLevel::Blocked->value) {
            return [
                'user' => (new UserResource($user))->resolve(),
                'system' => $system,
                'subscription' => $subscription,
            ];
        }

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
                'opening_cash_balance' => Decimal::normalize((string) $activeShift->opening_cash_balance),
            ] : null,
            'system' => $system,
            'subscription' => $subscription,
        ];
    }

    /**
     * The derived lifecycle state of the current tenant (null outside a tenant context).
     *
     * @return array{status: string, access: string, message: string, days_left: int|null}|null
     */
    private function subscriptionBlock(): ?array
    {
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        if (! $tenant instanceof Tenant) {
            return null;
        }

        $decision = $this->lifecycle->evaluate($tenant->lifecycleSnapshot(), CarbonImmutable::now());

        return [
            'status' => $decision->effectiveStatus->value,
            'access' => $decision->access()->value,
            'message' => (string) __($decision->messageKey(), $decision->messageParameters()),
            'days_left' => $decision->daysLeft,
        ];
    }
}
