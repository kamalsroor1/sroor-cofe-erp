<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\Branding\PlatformBranding;
use App\Services\Settings\TenantSettings;
use App\Services\TreasuryService;
use App\Support\TenantClock;
use Illuminate\Http\Request;

final class GetSystemContextAction
{
    public function __construct(
        private readonly GetTranslationsAction $translationsAction,
        private readonly TreasuryService $treasuryService,
        private readonly TenantSettings $tenantSettings,
        private readonly TenantClock $tenantClock,
        private readonly PlatformBranding $platformBranding,
    ) {}

    /**
     * Build unified bootstrap context for Vue 3 SPA
     */
    public function execute(User $user, Request $request): array
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        // 1. Store Resolution
        $activeStore = null;
        $storeHeader = $request->header('X-Store-Id');
        if ($storeHeader && is_numeric($storeHeader)) {
            $activeStore = Store::where('id', (int) $storeHeader)->where('is_active', true)->first();
        }

        if (! $activeStore) {
            $activeStore = $user->getCurrentStore();
        }

        $userStores = $user->hasRole('admin')
            ? Store::where('is_active', true)->orderBy('is_main', 'desc')->get(['id', 'name', 'code', 'type', 'is_main'])
            : $user->stores()->where('is_active', true)->get(['stores.id', 'name', 'code', 'type', 'is_main']);

        // 2. Active Cash Shift
        $activeShift = null;
        if ($activeStore) {
            $activeShift = CashShift::where('store_id', $activeStore->id)
                ->where('status', 'open')
                ->latest('id')
                ->first();
        }

        // 3. System Alerts & Telemetry
        $alerts = [];
        // SETG-10: items without their own minimum use the tenant default threshold.
        $lowStockCount = $this->tenantSettings->whereLowStock(Item::where('is_active', true))->count();

        if ($lowStockCount > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => '🚨',
                'title' => "نواقص بالمخزن ({$lowStockCount} صنف)",
                'description' => 'أصناف بلغت أو تجاوزت حد الطلب الأدنى',
                'link' => '/purchases/smart-reorder',
                'link_label' => 'إعادة الطلب الذكي',
            ];
        }

        $debtCount = Customer::where('is_active', true)->where('current_balance', '>', 0)->count();
        if ($debtCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => '👥',
                'title' => "مديونيات عملاء ({$debtCount} عميل)",
                'description' => 'يوجد عملاء مستحق عليهم مبالغ آجلة بحاجة للتحصيل',
                'link' => '/customers',
                'link_label' => 'قائمة العملاء المدينين',
            ];
        }

        if ($user->can('daily_journal.view') || $user->hasRole('admin')) {
            try {
                $balances = $this->treasuryService->getBalances($activeStore?->id);
                $cashExpected = (float) ($balances['cash']['balance'] ?? 0);
                if ($cashExpected >= 10000) {
                    $alerts[] = [
                        'type' => 'info',
                        'icon' => '💰',
                        'title' => 'سيولة نقدية عالية بالدرج',
                        'description' => 'يوجد حالياً '.number_format($cashExpected, 0).' ج.م نقداً بالدرج. يُنصح بتوريد الفائض.',
                        'link' => '/daily-journal',
                        'link_label' => 'دفتر اليومية والخزينة',
                    ];
                }
            } catch (\Throwable) {
            }
        }

        // 4. System Settings & Branding
        $headerLocale = $request->header('X-Locale');
        $locale = GetTranslationsAction::normalizeLocale(is_string($headerLocale) ? $headerLocale : null);
        $translations = $this->translationsAction->execute($locale);

        return [
            'auth' => [
                'user' => (new UserResource($user))->resolve(),
                'is_impersonating' => (bool) session('is_impersonating', false),
            ],
            'tenant' => $tenant ? (new TenantResource($tenant))->resolve() : null,
            'active_store' => $activeStore ? [
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
                // BRND-1: the platform brand is central (platform_settings), never a tenant setting.
                'platform_name' => $this->platformBranding->get()->name,
                'company_name' => Setting::get('company_name') ?: ($tenant?->name ?? __('auth.default_company_name')),
                'company_subtitle' => Setting::get('company_subtitle') ?: '',
                // SETG-7: real legal/contact info for the A4 invoice header ('' = hide the line).
                'company_phone' => Setting::get('company_phone') ?: '',
                'company_address' => Setting::get('company_address') ?: '',
                'commercial_register' => Setting::get('commercial_register') ?: '',
                'tax_registration_no' => Setting::get('tax_registration_no') ?: '',
                'system_theme_color' => Setting::get('system_theme_color', 'emerald'),
                'server_time' => now()->toDateTimeString(),
                // SETG-1 ext / SETG-2 ext / SETG-10 / SETG-13: tenant settings the SPA formats and validates with.
                'currency' => $this->tenantSettings->currency(),
                'currency_decimals' => $this->tenantSettings->currencyDecimals(),
                'timezone' => $this->tenantSettings->timezone(),
                'business_day_cutoff' => $this->tenantSettings->businessDayCutoff(),
                'business_date' => $this->tenantClock->businessDate(),
                'inventory_units' => $this->tenantSettings->inventoryUnits(),
                'low_stock_default_threshold' => $this->tenantSettings->lowStockDefaultThreshold(),
            ],
            'branding' => [
                'logo_light' => '/logo-light.png?v='.Setting::get('logo_light_v', '1'),
                'logo_dark' => '/logo-dark.png?v='.Setting::get('logo_dark_v', '1'),
                'logo' => '/logo.png?v='.Setting::get('logo_v', '1'),
            ],
            'notifications' => $alerts,
            'locale' => $locale,
            'translations' => $translations,
        ];
    }
}
