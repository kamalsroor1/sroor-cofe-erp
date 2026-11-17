<?php

declare(strict_types=1);

use App\Services\Entitlements\TenantEntitlementService;
use App\Support\PublicCatalogCache;
use App\Support\TenantCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-10 (central DB, data only): the catalog `free` plan becomes the PERMANENT free plan
 * (docs/03-architecture/free-plan-and-self-signup-2026-11-18.md section 3), on an installation that
 * already has it. A fresh install / test DB gets the same values from the idempotent
 * PlansAndFeaturesSeeder (Database\Seeders\Catalog\PlanCatalog).
 *
 *   is_free true, trial_days 0, price 0 / 0, no founder / transition / promotion price;
 *   1 branch, 0 extra warehouses, 0 vans, 2 users, 100 items, 150 sales invoices a month, 200 MB;
 *   features: every core feature except reports.export and telegram.notifications; no extras
 *   (transfers, mixes, reorder, advanced reports, activity log are off).
 *
 * - The main store a tenant is born with is a `retail` store, which counts against max_stores,
 *   never against max_warehouses, so 0 warehouses does not block provisioning.
 * - Feature keys this migration does not know are kept as they are.
 * - Intentional overwrite of the one catalog row (owner-approved values, like the price list).
 * - Idempotent; tenants on the plan get their entitlements cache flushed.
 * - down() puts back the 14-day trial values, only on a row that still holds the values written
 *   here (a later super-admin edit is never overwritten).
 * Literal values on purpose: a migration must not change when the catalog does.
 */
return new class extends Migration
{
    private const SLUG = 'free';

    /** @var array<string, int|bool|string|null> */
    private const NEW_VALUES = [
        'is_free' => true,
        'trial_days' => 0,
        'price_monthly' => '0.000',
        'price_yearly' => '0.000',
        'founder_price_monthly' => null,
        'founder_price_yearly' => null,
        'founder_transition_price_monthly' => null,
        'founder_transition_price_yearly' => null,
        'compare_at_price_monthly' => null,
        'compare_at_price_yearly' => null,
        'promo_starts_at' => null,
        'promo_ends_at' => null,
        'max_stores' => 1,
        'max_warehouses' => 0,
        'max_vans' => 0,
        'max_users' => 2,
        'max_items' => 100,
        'max_invoices_per_month' => 150,
        'max_storage_mb' => 200,
    ];

    /** @var array<string, int|bool> the 14-day trial plan before PLAN-10, for down() */
    private const PREVIOUS_VALUES = [
        'is_free' => false,
        'trial_days' => 14,
        'max_stores' => 1,
        'max_warehouses' => 1,
        'max_vans' => 0,
        'max_users' => 3,
        'max_items' => 200,
        'max_invoices_per_month' => 300,
        'max_storage_mb' => 500,
    ];

    /** @var array<string, bool> */
    private const NEW_FEATURES = [
        'reports.export' => false,
        'telegram.notifications' => false,
        'transfers.manage' => false,
        'mixes.manage' => false,
        'purchases.reorder' => false,
        'reports.advanced' => false,
        'audit.logs' => false,
    ];

    /** @var array<string, bool> */
    private const PREVIOUS_FEATURES = [
        'reports.export' => true,
        'telegram.notifications' => true,
        'transfers.manage' => true,
        'mixes.manage' => true,
        'purchases.reorder' => true,
        'reports.advanced' => true,
        'audit.logs' => true,
    ];

    public function up(): void
    {
        if (! $this->ready()) {
            return;
        }

        $planId = DB::transaction(function (): ?int {
            $row = DB::table('plans')->where('slug', self::SLUG)->lockForUpdate()->first();
            if ($row === null) {
                return null;
            }

            $features = array_merge($this->decodeMap($row->features ?? null), self::NEW_FEATURES);

            DB::table('plans')->where('id', $row->id)->update($this->onlyExisting(self::NEW_VALUES) + [
                'features' => json_encode($features, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            return (int) $row->id;
        });

        $this->flushCaches($planId);
    }

    public function down(): void
    {
        if (! $this->ready()) {
            return;
        }

        $planId = DB::transaction(function (): ?int {
            $row = DB::table('plans')->where('slug', self::SLUG)->lockForUpdate()->first();
            if ($row === null || ! $this->holdsNewValues($row)) {
                return null;
            }

            $features = array_merge($this->decodeMap($row->features ?? null), self::PREVIOUS_FEATURES);

            DB::table('plans')->where('id', $row->id)->update($this->onlyExisting(self::PREVIOUS_VALUES) + [
                'features' => json_encode($features, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            return (int) $row->id;
        });

        $this->flushCaches($planId);
    }

    private function ready(): bool
    {
        return Schema::hasTable('plans') && Schema::hasColumn('plans', 'is_free');
    }

    private function holdsNewValues(object $row): bool
    {
        foreach (['trial_days', 'max_users', 'max_items', 'max_invoices_per_month', 'max_storage_mb', 'max_warehouses'] as $column) {
            if ((int) ($row->{$column} ?? -1) !== (int) self::NEW_VALUES[$column]) {
                return false;
            }
        }

        return (bool) ($row->is_free ?? false);
    }

    /**
     * @param  array<string, int|bool|string|null>  $values
     * @return array<string, int|bool|string|null>
     */
    private function onlyExisting(array $values): array
    {
        return array_filter(
            $values,
            static fn (string $column): bool => Schema::hasColumn('plans', $column),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function flushCaches(?int $planId): void
    {
        if ($planId === null) {
            return;
        }

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'plan_id')) {
            DB::table('tenants')->where('plan_id', $planId)->orderBy('id')->pluck('id')
                ->each(fn (mixed $id) => TenantCache::bumpFor((string) $id, TenantEntitlementService::CACHE_NAMESPACE));
        }

        PublicCatalogCache::forget();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeMap(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
};
