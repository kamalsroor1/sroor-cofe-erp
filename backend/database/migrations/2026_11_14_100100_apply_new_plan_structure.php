<?php

declare(strict_types=1);

use App\Services\Entitlements\TenantEntitlementService;
use App\Support\PublicCatalogCache;
use App\Support\TenantCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-01 (central DB, data only): the new plan structure on an installation that already has
 * the catalog.
 *
 * - basic / pro: no item and no monthly-invoice limit (NULL = unlimited); trial: 1 warehouse.
 * - basic gets transfers.manage, pro gets mixes.manage (merged into plans.features).
 * - reports.advanced becomes a visible, sold feature (BILL-03 now enforces it).
 * - add-ons: the "control" bundle (bundle.control, 199 / 1,990, basic only) is created; the
 *   single reports.advanced / audit.logs add-ons and addon.items_5k are RETIRED (is_active =
 *   false, unavailable on every plan), mixes.manage is offered on basic only (pro includes it).
 * - NOTHING is taken away: subscription_addons rows are untouched (a retired add-on keeps
 *   working for the shops that already hold it) and every Basic tenant gets reports.advanced as
 *   a super-admin override, because until BILL-03 it saw profit figures for free.
 *
 * Idempotent (a second run changes nothing). It touches a handful of catalog rows and one cheap
 * per-tenant pass. down() reverts the catalog rows but NOT the per-tenant overrides (they cannot
 * be told apart from an override set by hand).
 */
return new class extends Migration
{
    private const BUNDLE_KEY = 'bundle.control';

    /** @var list<string> */
    private const RETIRED = ['addon.items_5k', 'reports.advanced', 'audit.logs'];

    public function up(): void
    {
        // An empty catalog (fresh install / test DB) is left to the idempotent seeder, which
        // creates the bundle with its feature list and the right sort order.
        if (! Schema::hasTable('plans') || ! DB::table('plans')->exists()) {
            return;
        }

        $touchedPlans = $this->updatePlans();
        $this->updateFeatureRegistry();
        $this->updateAddons();
        $this->grandfatherBasicTenants();

        // Every tenant on a changed plan resolves its entitlements again.
        if ($touchedPlans !== [] && Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'plan_id')) {
            DB::table('tenants')->whereIn('plan_id', $touchedPlans)->orderBy('id')->pluck('id')
                ->each(fn (mixed $id) => TenantCache::bumpFor((string) $id, TenantEntitlementService::CACHE_NAMESPACE));
        }

        PublicCatalogCache::forget();
    }

    public function down(): void
    {
        if (Schema::hasTable('addons') && Schema::hasColumn('addons', 'is_active')) {
            DB::table('addons')->whereIn('key', self::RETIRED)->update(['is_active' => true]);
            DB::table('addons')->where('key', self::BUNDLE_KEY)->update(['is_active' => false, 'is_public' => false]);
        }

        if (Schema::hasTable('plan_features') && Schema::hasColumn('plan_features', 'is_public')) {
            DB::table('plan_features')->where('key', 'reports.advanced')->update(['is_public' => false]);
        }

        PublicCatalogCache::forget();
    }

    /**
     * @return list<int|string> ids of the plans that were changed
     */
    private function updatePlans(): array
    {
        $touched = [];

        foreach (DB::table('plans')->whereIn('slug', ['free', 'basic', 'pro'])->get() as $plan) {
            $changes = [];

            if (in_array($plan->slug, ['basic', 'pro'], true)) {
                foreach (['max_items', 'max_invoices_per_month'] as $column) {
                    if (Schema::hasColumn('plans', $column) && $plan->{$column} !== null) {
                        $changes[$column] = null;
                    }
                }

                $features = $this->decodeMap($plan->features ?? null);
                $grant = $plan->slug === 'basic' ? 'transfers.manage' : 'mixes.manage';
                if (($features[$grant] ?? false) !== true) {
                    $features[$grant] = true;
                    $changes['features'] = json_encode($features, JSON_UNESCAPED_UNICODE);
                }
            }

            if ($plan->slug === 'free' && (int) $plan->max_warehouses === 0) {
                $changes['max_warehouses'] = 1;
            }

            if ($changes !== []) {
                DB::table('plans')->where('id', $plan->id)->update($changes);
                $touched[] = $plan->id;
            }
        }

        return $touched;
    }

    private function updateFeatureRegistry(): void
    {
        if (Schema::hasTable('plan_features') && Schema::hasColumn('plan_features', 'is_public')) {
            DB::table('plan_features')->where('key', 'reports.advanced')->update(['is_public' => true]);
        }
    }

    private function updateAddons(): void
    {
        if (! Schema::hasTable('addons') || ! Schema::hasColumn('addons', 'feature_keys')) {
            return;
        }

        $now = now();
        $bundleFeatures = json_encode(['reports.advanced', 'audit.logs', 'purchases.reorder']);

        // The older catalog migration (2026_10_10_200610) may have inserted the bundle before the
        // feature_keys column existed: it then has no feature list and would unlock nothing.
        DB::table('addons')->where('key', self::BUNDLE_KEY)->whereNull('feature_keys')
            ->update(['feature_keys' => $bundleFeatures, 'updated_at' => $now]);

        if (! DB::table('addons')->where('key', self::BUNDLE_KEY)->exists()) {
            DB::table('addons')->insert([
                'key' => self::BUNDLE_KEY,
                'name_key' => 'plans.addons.control_bundle.name',
                'type' => 'recurring',
                'feature_key' => null,
                'feature_keys' => $bundleFeatures,
                'bundled_limits' => null,
                'unit_price' => '199.000',
                'yearly_price' => '1990.000',
                'price_tiers' => null,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => ((int) DB::table('addons')->max('sort_order')) + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('addons')->whereIn('key', self::RETIRED)->update(['is_active' => false]);

        if (! Schema::hasTable('plan_addon')) {
            return;
        }

        $plans = DB::table('plans')->pluck('id', 'slug');
        $addons = DB::table('addons')->pluck('id', 'key');

        // [addon key, plan slug, is_available]
        $rows = [['bundle.control', 'basic', true], ['mixes.manage', 'basic', true], ['mixes.manage', 'pro', false]];
        foreach (self::RETIRED as $key) {
            foreach ($plans as $slug => $planId) {
                $rows[] = [$key, (string) $slug, false];
            }
        }

        foreach ($rows as [$key, $slug, $available]) {
            $planId = $plans[$slug] ?? null;
            $addonId = $addons[$key] ?? null;
            if ($planId === null || $addonId === null) {
                continue;
            }

            $existing = DB::table('plan_addon')->where(['plan_id' => $planId, 'addon_id' => $addonId])->first();

            if ($existing === null) {
                if ($available) {
                    DB::table('plan_addon')->insert([
                        'plan_id' => $planId,
                        'addon_id' => $addonId,
                        'is_available' => true,
                        'included_quantity' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } elseif ((bool) $existing->is_available !== $available) {
                DB::table('plan_addon')->where('id', $existing->id)->update(['is_available' => $available, 'updated_at' => $now]);
            }
        }
    }

    /** Basic shops keep seeing profit figures: add reports.advanced to their feature overrides. */
    private function grandfatherBasicTenants(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'enabled_features') || ! Schema::hasColumn('tenants', 'plan_id')) {
            return;
        }

        $basicIds = DB::table('plans')->where('slug', 'basic')->pluck('id')->all();
        if ($basicIds === []) {
            return;
        }

        DB::table('tenants')->whereIn('plan_id', $basicIds)->orderBy('id')->get(['id', 'enabled_features'])
            ->each(function (object $tenant): void {
                $current = $this->decodeMap($tenant->enabled_features ?? null);

                if (array_is_list($current)) {
                    if (in_array('reports.advanced', $current, true)) {
                        return;
                    }
                    $next = [...$current, 'reports.advanced'];
                } else {
                    if (($current['reports.advanced'] ?? false) === true) {
                        return;
                    }
                    $next = $current + ['reports.advanced' => true];
                }

                DB::table('tenants')->where('id', $tenant->id)->update([
                    'enabled_features' => json_encode($next, JSON_UNESCAPED_UNICODE),
                ]);

                TenantCache::bumpFor((string) $tenant->id, TenantEntitlementService::CACHE_NAMESPACE);
            });
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeMap(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
};
