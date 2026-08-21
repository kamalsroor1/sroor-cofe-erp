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
use App\Services\TreasuryService;
use Illuminate\Http\Request;

final class GetSystemContextAction
{
    public function __construct(
        private readonly GetTranslationsAction $translationsAction,
        private readonly TreasuryService $treasuryService
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
            $activeStore = Store::where('id', (int)$storeHeader)->where('is_active', true)->first();
        }

        if (!$activeStore) {
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
        $lowStockCount = Item::where('is_active', true)
            ->whereColumn('current_stock', '<=', 'min_stock_level')
            ->count();

        if ($lowStockCount > 0) {
            $alerts[] = [
                'type'        => 'danger',
                'icon'        => '🚨',
                'title'       => "نواقص بالمخزن ({$lowStockCount} صنف)",
                'description' => 'أصناف بلغت أو تجاوزت حد الطلب الأدنى',
                'link'        => '/purchases/smart-reorder',
                'link_label'  => 'إعادة الطلب الذكي',
            ];
        }

        $debtCount = Customer::where('is_active', true)->where('current_balance', '>', 0)->count();
        if ($debtCount > 0) {
            $alerts[] = [
                'type'        => 'warning',
                'icon'        => '👥',
                'title'       => "مديونيات عملاء ({$debtCount} عميل)",
                'description' => 'يوجد عملاء مستحق عليهم مبالغ آجلة بحاجة للتحصيل',
                'link'        => '/customers',
                'link_label'  => 'قائمة العملاء المدينين',
            ];
        }

        if ($user->can('daily_journal.view') || $user->hasRole('admin')) {
            try {
                $balances = $this->treasuryService->getBalances($activeStore?->id);
                $cashExpected = (float)($balances['cash']['balance'] ?? 0);
                if ($cashExpected >= 10000) {
                    $alerts[] = [
                        'type'        => 'info',
                        'icon'        => '💰',
                        'title'       => 'سيولة نقدية عالية بالدرج',
                        'description' => 'يوجد حالياً ' . number_format($cashExpected, 0) . ' ج.م نقداً بالدرج. يُنصح بتوريد الفائض.',
                        'link'        => '/daily-journal',
                        'link_label'  => 'دفتر اليومية والخزينة',
                    ];
                }
            } catch (\Throwable) {}
        }

        // 4. System Settings & Branding
        $locale = $request->header('X-Locale') ?: app()->getLocale();
        $translations = $this->translationsAction->execute($locale);

        return [
            'auth' => [
                'user'             => (new UserResource($user))->resolve(),
                'is_impersonating' => (bool)session('is_impersonating', false),
            ],
            'tenant' => $tenant ? (new TenantResource($tenant))->resolve() : null,
            'active_store' => $activeStore ? [
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
                'company_name'       => Setting::get('company_name', 'سرور كوفي'),
                'company_subtitle'   => Setting::get('company_subtitle', 'لتوريدات خامات مطاحن البن'),
                'system_theme_color' => Setting::get('system_theme_color', 'amber'),
                'server_time'        => now()->toDateTimeString(),
            ],
            'branding' => [
                'logo_light' => '/logo-light.png?v=' . Setting::get('logo_light_v', '1'),
                'logo_dark'  => '/logo-dark.png?v=' . Setting::get('logo_dark_v', '1'),
                'logo'       => '/logo.png?v=' . Setting::get('logo_v', '1'),
            ],
            'notifications' => $alerts,
            'locale'        => $locale,
            'translations'  => $translations,
        ];
    }
}
