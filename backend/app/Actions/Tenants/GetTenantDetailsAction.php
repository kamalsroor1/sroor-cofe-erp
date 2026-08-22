<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Http\Resources\TenantResource;
use App\Http\Resources\PlanResource;

class GetTenantDetailsAction
{
    /**
     * جلب تفاصيل المستأجر مع مصفوفة الفيتشرز والباقات عبر JsonResources
     */
    public function execute(string $id): array
    {
        $tenant = Tenant::with(['plan', 'domains', 'subscriptions' => fn($q) => $q->latest()])->findOrFail($id);
        $allFeatures = PlanFeature::orderBy('sort_order')->get();
        $groupedFeatures = PlanFeature::groupedByModule();
        $plans = PlanResource::collection(Plan::where('is_active', true)->orderBy('sort_order')->get())->resolve();

        $stats = [
            'users_count'    => 0,
            'stores_count'   => 0,
            'items_count'    => 0,
            'invoices_count' => 0,
            'total_sales'    => '0.00',
        ];

        $allowedUnits = $tenant->data['allowed_units'] ?? ['قطعة', 'علبة', 'كرتونة', 'كجم', 'جرام', 'شيكارة', 'طرد', 'دستة', 'لتر'];

        try {
            \Stancl\Tenancy\Facades\Tenancy::initialize($tenant);
            if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                $stats['users_count'] = \App\Models\User::count();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('stores')) {
                $stats['stores_count'] = \App\Models\Store::count();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('items')) {
                $stats['items_count'] = \App\Models\Item::count();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('invoices')) {
                $stats['invoices_count'] = \App\Models\Invoice::count();
                $stats['total_sales'] = number_format((float)\App\Models\Invoice::sum('total_amount'), 2, '.', '');
            }
            $tenantUnits = \App\Models\Setting::get('inventory_units');
            if ($tenantUnits) {
                $allowedUnits = array_values(array_filter(array_map('trim', explode(',', $tenantUnits))));
            }
            \Stancl\Tenancy\Facades\Tenancy::end();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Tenant stats query failed for {$tenant->id}: " . $e->getMessage());
        }

        $globalUnitsStr = \App\Models\Setting::get('global_system_units', 'قطعة,علبة,كرتونة,كجم,جرام,شيكارة,طرد,دستة,باكت,حبة,لتر,مل,متر,طقم,زوج,باليتة');
        $globalUnits = array_values(array_filter(array_map('trim', explode(',', $globalUnitsStr))));

        return [
            'tenant'           => (new TenantResource($tenant))->resolve(),
            'stats'            => $stats,
            'allowed_units'    => $allowedUnits,
            'global_units'     => $globalUnits,
            'features'         => $allFeatures,
            'grouped_features' => $groupedFeatures,
            'plans'            => $plans,
        ];
    }
}
