<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Actions\Branding\GetBrandingAction;
use App\Http\Resources\BrandingResource;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
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
        private readonly GetBrandingAction $getBrandingAction,
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

        // BRND-3 / BRND-5: one source for the platform and shop brand.
        $branding = $this->getBrandingAction->execute();
        $platformBrand = $branding['platform'];
        $tenantBrand = $branding['tenant'];
        $platformBlock = BrandingResource::platformBlock($platformBrand);
        $platformLogos = $platformBlock['logos'];

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
                'platform_name' => $platformBrand->name,
                // BRND-5: read through TenantBranding (name falls back to tenant name, then platform name).
                'company_name' => $tenantBrand->name ?? (Setting::get('company_name') ?: __('auth.default_company_name')),
                'company_subtitle' => $tenantBrand->subtitle ?? (Setting::get('company_subtitle') ?: ''),
                // SETG-7: real legal/contact info for the A4 invoice header ('' = hide the line).
                'company_phone' => $tenantBrand->phone ?? (Setting::get('company_phone') ?: ''),
                'company_address' => $tenantBrand->address ?? (Setting::get('company_address') ?: ''),
                'commercial_register' => $tenantBrand->commercialRegister ?? (Setting::get('commercial_register') ?: ''),
                'tax_registration_no' => $tenantBrand->taxRegistrationNo ?? (Setting::get('tax_registration_no') ?: ''),
                'system_theme_color' => $tenantBrand->themeColor ?? Setting::get('system_theme_color', 'emerald'),
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
                'platform' => $platformBlock,
                'tenant' => $tenantBrand !== null ? BrandingResource::tenantBlock($tenantBrand) : null,
                // Back-compat keys (pre-BRND-5 SPA): the shop logo, else the platform logo. Never
                // the shared public/logo*.png of the old implementation.
                'logo_light' => $tenantBrand->logoLightUrl ?? $platformLogos['light'],
                'logo_dark' => $tenantBrand->logoDarkUrl ?? $tenantBrand->logoLightUrl ?? $platformLogos['dark'],
                'logo' => $tenantBrand->logoLightUrl ?? $platformLogos['light'],
            ],
            'notifications' => $alerts,
            'locale' => $locale,
            'translations' => $translations,
        ];
    }
}
