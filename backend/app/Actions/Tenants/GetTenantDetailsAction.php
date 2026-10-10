<?php

namespace App\Actions\Tenants;

use App\Actions\SuperAdmin\GetPlatformSystemUnitsAction;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantResource;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Facades\Tenancy;

class GetTenantDetailsAction
{
    public function __construct(private readonly GetPlatformSystemUnitsAction $platformUnits) {}

    /**
     * جلب تفاصيل المستأجر مع مصفوفة الفيتشرز والباقات عبر JsonResources
     */
    public function execute(string $id): array
    {
        $tenant = Tenant::with(['plan', 'domains', 'subscriptions' => fn ($q) => $q->latest()])->findOrFail($id);
        $allFeatures = PlanFeature::orderBy('sort_order')->get();
        $groupedFeatures = PlanFeature::groupedByModule();
        $plans = PlanResource::collection(Plan::where('is_active', true)->orderBy('sort_order')->get())->resolve();

        $stats = [
            'users_count' => 0,
            'stores_count' => 0,
            'items_count' => 0,
            'invoices_count' => 0,
            'total_sales' => '0.000',
        ];

        // `allowed_units` is a stancl virtual attribute (decoded from `data` on retrieval), so
        // it is read through getAttribute(): $tenant->data is always null after hydration.
        $allowedUnits = $tenant->getAttribute('allowed_units') ?? ['قطعة', 'علبة', 'كرتونة', 'كجم', 'جرام', 'شيكارة', 'طرد', 'دستة', 'لتر'];

        // OPS-2: a pending / running / failed tenant has no database yet: zero stats.
        if ($tenant->isProvisioned()) {
            try {
                Tenancy::initialize($tenant);
                if (Schema::hasTable('users')) {
                    $stats['users_count'] = User::count();
                }
                if (Schema::hasTable('stores')) {
                    $stats['stores_count'] = Store::count();
                }
                if (Schema::hasTable('items')) {
                    $stats['items_count'] = Item::count();
                }
                if (Schema::hasTable('invoices')) {
                    $stats['invoices_count'] = Invoice::count();
                    // Decimal string (bcmath, scale 3, half-up), never a float. MySQL returns
                    // the DECIMAL sum as an exact string; a driver that returns a float
                    // (sqlite) is formatted as plain decimals first: (string) of a large or
                    // tiny float gives an exponent ("1.0E+15") that Decimal cannot parse.
                    // `invoices` has no total_amount column (the old query always threw into
                    // the catch below, so total_sales was always 0 and the tenant units were
                    // never read): net_total of non-cancelled invoices, as InvoiceController.
                    $sum = Invoice::query()->where('status', '!=', 'cancelled')->sum('net_total');
                    $stats['total_sales'] = Decimal::normalize(match (true) {
                        is_int($sum), is_string($sum) => $sum,
                        default => number_format($sum, 3, '.', ''),
                    });
                }
                $tenantUnits = Setting::get('inventory_units');
                if ($tenantUnits) {
                    $allowedUnits = array_values(array_filter(array_map('trim', explode(',', $tenantUnits))));
                }
            } catch (\Throwable $e) {
                Log::warning("Tenant stats query failed for {$tenant->id}: ".$e->getMessage());
            } finally {
                // Never leave the super-admin request inside the tenant (a failed query used to).
                if (tenancy()->initialized) {
                    Tenancy::end();
                }
            }
        }

        // The platform unit catalog is CENTRAL (platform_settings), never a tenant `settings` row.
        $globalUnits = $this->platformUnits->execute();

        return [
            'tenant' => (new TenantResource($tenant))->resolve(),
            'stats' => $stats,
            'allowed_units' => $allowedUnits,
            'global_units' => $globalUnits,
            'features' => $allFeatures,
            'grouped_features' => $groupedFeatures,
            'plans' => $plans,
        ];
    }
}
