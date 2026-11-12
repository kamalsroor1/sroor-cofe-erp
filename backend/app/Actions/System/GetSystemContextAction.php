<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Actions\Branding\GetBrandingAction;
use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\LimitResource;
use App\Http\Resources\BrandingResource;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Branding\TenantBranding;
use App\Services\Entitlements\PlanEnforcer;
use App\Services\Settings\TenantSettings;
use App\Services\TreasuryService;
use App\Support\Money\Decimal;
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
        private readonly PlanEnforcer $planEnforcer,
        private readonly TenantFeatureManagerInterface $featureManager,
        private readonly GetSetupProgressAction $setupProgressAction,
        private readonly GetSubscriptionBannerAction $subscriptionBannerAction,
        private readonly ListSubscriptionReminderAlertsAction $subscriptionReminderAlertsAction,
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

        // SELL-01 review: a session that must change its password gets only what the SPA needs to
        // boot the change-password screen: no shift, no alerts/telemetry, no setup progress.
        $mustChangePassword = $user->must_change_password === true;

        // 2. Active Cash Shift
        $activeShift = null;
        if ($activeStore && ! $mustChangePassword) {
            $activeShift = CashShift::where('store_id', $activeStore->id)
                ->where('status', 'open')
                ->latest('id')
                ->first();
        }

        // 3. System Alerts & Telemetry
        // SELL-02: the user's unread subscription reminder (tenant `notifications`) leads the bell.
        $currentTenant = $tenant instanceof Tenant ? $tenant : null;
        $alerts = $mustChangePassword ? [] : [
            ...$this->subscriptionReminderAlertsAction->execute($user, $currentTenant),
            ...$this->alerts($user, $activeStore),
        ];

        // 4. System Settings & Branding
        $headerLocale = $request->header('X-Locale');
        $locale = GetTranslationsAction::normalizeLocale(is_string($headerLocale) ? $headerLocale : null);
        $translations = $this->translationsAction->execute($locale);
        $translationsHash = $this->translationsAction->hash($locale, $translations);
        $clientHash = $request->query('translations_hash');

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
                // ROLE-01: the shared drawer's float only for its opener or a reports.view reader (as in ApiMeAction).
                'opening_cash_balance' => (int) $activeShift->user_id === (int) $user->id || $user->can('reports.view')
                    ? Decimal::normalize((string) $activeShift->opening_cash_balance)
                    : null,
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
                'system_theme_color' => $tenantBrand->themeColor ?? Setting::get('system_theme_color', TenantBranding::DEFAULT_THEME_COLOR),
                'server_time' => now()->toDateTimeString(),
                // SETG-1 ext / SETG-2 ext / SETG-10 / SETG-13: tenant settings the SPA formats and validates with.
                'currency' => $this->tenantSettings->currency(),
                'currency_decimals' => $this->tenantSettings->currencyDecimals(),
                'timezone' => $this->tenantSettings->timezone(),
                'business_day_cutoff' => $this->tenantSettings->businessDayCutoff(),
                'business_date' => $this->tenantClock->businessDate(),
                'inventory_units' => $this->tenantSettings->inventoryUnits(),
                'low_stock_default_threshold' => $this->tenantSettings->lowStockDefaultThreshold(),
                // SET-02: shop policies the SPA applies (idle lock screen; return window hint).
                'returns_max_days' => $this->tenantSettings->returnsMaxDays(),
                'pos_idle_lock_minutes' => $this->tenantSettings->posIdleLockMinutes(),
                // DATE-02: date-picker limits (max = business_date, min = business_date - this when the user may back-date).
                'backdate_max_days' => $this->tenantSettings->backdateMaxDays(),
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
            'entitlements' => $this->entitlements($tenant),
            'notifications' => $alerts,
            // SELL-02: permanent trial / expiry banner; null for users without billing.manage and
            // for a healthy subscription (see GetSubscriptionBannerAction for the states).
            'subscription_banner' => $mustChangePassword ? null : $this->subscriptionBannerAction->execute($user, $currentTenant),
            'setup_progress' => $mustChangePassword ? null : $this->setupProgressAction->execute($user),
            'locale' => $locale,
            'translations_hash' => $translationsHash,
        ] + ($clientHash === $translationsHash ? [] : ['translations' => $translations]);
    }

    /**
     * Dashboard alerts: low stock, customers in debt, high cash in the drawer.
     *
     * @return list<array<string, string>>
     */
    private function alerts(User $user, ?Store $activeStore): array
    {
        $alerts = [];
        // SETG-10: items without their own minimum use the tenant default threshold.
        $lowStockCount = $this->tenantSettings->whereLowStock(Item::where('is_active', true))->count();

        if ($lowStockCount > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'alert-triangle',
                'title' => __('dashboard.system_alerts.low_stock_title', ['count' => $lowStockCount]),
                'description' => __('dashboard.system_alerts.low_stock_desc'),
                'link' => '/purchases/smart-reorder',
                'link_label' => __('dashboard.system_alerts.low_stock_link'),
            ];
        }

        $debtCount = Customer::where('is_active', true)->where('current_balance', '>', 0)->count();
        if ($debtCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'users',
                'title' => __('dashboard.system_alerts.debts_title', ['count' => $debtCount]),
                'description' => __('dashboard.system_alerts.debts_desc'),
                'link' => '/customers',
                'link_label' => __('dashboard.system_alerts.debts_link'),
            ];
        }

        if ($user->can('daily_journal.view') || $user->hasRole('admin')) {
            try {
                $balances = $this->treasuryService->getBalances($activeStore?->id);
                $cashExpected = Decimal::normalize((string) ($balances['cash']['balance'] ?? 0));
                if (Decimal::cmp($cashExpected, '10000') >= 0) {
                    $alerts[] = [
                        'type' => 'info',
                        'icon' => 'wallet',
                        'title' => __('dashboard.system_alerts.high_cash_title'),
                        'description' => __('dashboard.system_alerts.high_cash_desc', ['amount' => number_format((float) $cashExpected, 0), 'currency' => $this->tenantSettings->currencyLabel()]),
                        'link' => '/daily-journal',
                        'link_label' => __('dashboard.system_alerts.high_cash_link'),
                    ];
                }
            } catch (\Throwable) {
            }
        }

        return $alerts;
    }

    /**
     * Effective plan gating for the SPA, mirroring what the `feature:<key>` middleware enforces
     * (plan + active add-ons + super-admin overrides, canonical keys; one cached read).
     *
     * enforced=false (grandfathered planless tenant): everything is allowed, so `features` is
     * the full catalog and every limit is null (unlimited). enforced=true: `features` holds only
     * the enabled keys and `limits` the effective caps (null = unlimited).
     *
     * @return array{enforced: bool, features: list<string>, limits: array<string, int|null>}
     */
    private function entitlements(mixed $tenant): array
    {
        if (! $tenant instanceof Tenant || ! $this->planEnforcer->isEnforcedForCurrentTenant()) {
            $catalog = array_values(array_unique(array_map(
                fn (string $key): string => $this->canonical($key),
                array_values(array_filter((array) config('entitlements.features', []), 'is_string')),
            )));
            sort($catalog);

            return [
                'enforced' => false,
                'features' => $catalog,
                'limits' => array_fill_keys(LimitResource::values(), null),
            ];
        }

        $entitlements = $this->featureManager->entitlements($tenant);

        return [
            'enforced' => true,
            'features' => $entitlements->features,
            'limits' => $entitlements->limits,
        ];
    }

    private function canonical(string $key): string
    {
        $canonical = (array) config('entitlements.aliases', []);

        return is_string($canonical[$key] ?? null) ? $canonical[$key] : $key;
    }
}
