<?php

declare(strict_types=1);

use App\Services\Entitlements\TenantEntitlementService;
use App\Support\PublicCatalogCache;
use App\Support\TenantCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-EDIT (central DB, data only, owner decision 2026-11-15) on an installation that already has
 * the catalog:
 * - Enterprise = service plan: addon.premium_support is INCLUDED (plan_addon is_available = false,
 *   included_quantity 1, any cycle) and service.onboarding is included with a YEARLY subscription
 *   (still offered, included_quantity 1, included_billing_cycle 'yearly');
 * - api.access is switched off in Enterprise's feature map (hidden, does nothing).
 *
 * Only the catalog `enterprise` row is touched; custom plans never. Idempotent. An empty catalog
 * is left to the seeder (AddonCatalog carries the same rows). down() restores the previous
 * catalog rows (api.access on, no premium_support row on Enterprise, onboarding not included).
 */
return new class extends Migration
{
    private const PREMIUM_SUPPORT = 'addon.premium_support';

    private const ONBOARDING = 'service.onboarding';

    public function up(): void
    {
        $ids = $this->ids();
        if ($ids === null) {
            return;
        }

        [$enterpriseId, $supportId, $onboardingId] = $ids;

        DB::transaction(function () use ($enterpriseId, $supportId, $onboardingId): void {
            $this->setApiAccess($enterpriseId, false);

            if ($supportId !== null) {
                $this->upsertPlanAddon($enterpriseId, $supportId, ['is_available' => false, 'included_quantity' => 1, 'included_billing_cycle' => null]);
            }
            if ($onboardingId !== null) {
                $this->upsertPlanAddon($enterpriseId, $onboardingId, ['is_available' => true, 'included_quantity' => 1, 'included_billing_cycle' => 'yearly']);
            }
        });

        $this->forgetCaches($enterpriseId);
    }

    public function down(): void
    {
        $ids = $this->ids();
        if ($ids === null) {
            return;
        }

        [$enterpriseId, $supportId, $onboardingId] = $ids;

        DB::transaction(function () use ($enterpriseId, $supportId, $onboardingId): void {
            $this->setApiAccess($enterpriseId, true);

            if ($supportId !== null) {
                DB::table('plan_addon')->where(['plan_id' => $enterpriseId, 'addon_id' => $supportId])->delete();
            }
            if ($onboardingId !== null) {
                DB::table('plan_addon')->where(['plan_id' => $enterpriseId, 'addon_id' => $onboardingId])
                    ->update(['included_quantity' => 0, 'included_billing_cycle' => null, 'updated_at' => now()]);
            }
        });

        $this->forgetCaches($enterpriseId);
    }

    /**
     * @return array{0: int, 1: int|null, 2: int|null}|null enterprise plan id and the two add-on ids
     */
    private function ids(): ?array
    {
        foreach (['plans', 'addons', 'plan_addon'] as $table) {
            if (! Schema::hasTable($table)) {
                return null;
            }
        }

        if (! Schema::hasColumn('plan_addon', 'included_billing_cycle')) {
            return null;
        }

        $enterpriseId = DB::table('plans')->where('slug', 'enterprise')->value('id');
        if ($enterpriseId === null) {
            return null;
        }

        $supportId = DB::table('addons')->where('key', self::PREMIUM_SUPPORT)->value('id');
        $onboardingId = DB::table('addons')->where('key', self::ONBOARDING)->value('id');

        return [(int) $enterpriseId, $supportId === null ? null : (int) $supportId, $onboardingId === null ? null : (int) $onboardingId];
    }

    private function setApiAccess(int $planId, bool $on): void
    {
        $raw = DB::table('plans')->where('id', $planId)->value('features');
        $features = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (! is_array($features) || ($features['api.access'] ?? null) === $on) {
            return;
        }

        $features['api.access'] = $on;
        DB::table('plans')->where('id', $planId)->update([
            'features' => json_encode($features, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function upsertPlanAddon(int $planId, int $addonId, array $values): void
    {
        $keys = ['plan_id' => $planId, 'addon_id' => $addonId];
        $now = now();

        if (DB::table('plan_addon')->where($keys)->exists()) {
            DB::table('plan_addon')->where($keys)->update($values + ['updated_at' => $now]);

            return;
        }

        DB::table('plan_addon')->insert($keys + $values + ['created_at' => $now, 'updated_at' => $now]);
    }

    private function forgetCaches(int $enterpriseId): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'plan_id')) {
            DB::table('tenants')->where('plan_id', $enterpriseId)->orderBy('id')->pluck('id')
                ->each(fn (mixed $id) => TenantCache::bumpFor((string) $id, TenantEntitlementService::CACHE_NAMESPACE));
        }

        PublicCatalogCache::forget();
    }
};
