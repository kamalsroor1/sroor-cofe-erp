<?php

declare(strict_types=1);

namespace Database\Seeders\Catalog;

use App\Models\Plan;

/**
 * ENTI-1.8: the approved plan ladder (central `plans`).
 *
 * Source: docs/03-architecture/pricing-model-recommendation-2026-10.md §4 (prices, limits),
 * §5 (feature matrix) and CTO W1 Q4 [2026-10-09]:
 * - PRICE-2026-11 (owner decision 2026-11-15, supersedes 449 / 899 / 1,799 and founder
 *   299 / 599 / 999): monthly 499 / 999 / 1,499 and yearly = 10 months 4,990 / 9,990 / 14,990;
 * - founder schedule (first 50 paying customers, ENTI-1.7, config billing.founder), from the
 *   first verified payment: months 1-12 founder 349 / 699 / 1,099 (yearly 3,490 / 6,990 /
 *   10,990), months 13-24 transition 429 / 849 / 1,299 (yearly 4,290 / 8,490 / 12,990), then
 *   the list price;
 * - no promotion ships with the catalog (compare_at_* NULL): the owner switches one on from the
 *   console with an end date (App\Support\Billing\PlanPromotion);
 * - every price EXCLUDES VAT (14% is added on the SaaS invoice, ENTI-3.2);
 * - NULL limit = unlimited (never a 999… sentinel);
 * - no plan has trial days of its own; paid plans have trial_days = 0, so a tenant an operator
 *   creates on them waits in pending_payment until paid (CTO W1 Q4). An approved SIGNUP on a paid
 *   plan gets the platform default trial instead (SELL-06, App\Services\Signup\SignupTrialTerms);
 * - PLAN-10 (docs/03-architecture/free-plan-and-self-signup-2026-11-18.md 3): `free` is the
 *   PERMANENT free plan (is_free): never billed, no trial, its tenants are always active. Limits are
 *   on the volume of work (2 users, 100 items, 150 sales invoices a month, 200 MB, 1 branch, no
 *   extra warehouse or van); it has every core feature except report export and Telegram
 *   notifications, and no extra.
 *
 * Money is a DECIMAL(12,3) string. Names/descriptions are keys in lang/{ar,en}/plans.php.
 */
final class PlanCatalog
{
    /** Slugs owned by the catalog; any other plan is a super-admin custom plan and is never touched. */
    public const SLUGS = Plan::CATALOG_SLUGS;

    /**
     * Non-core features per plan (core features are on in every plan).
     *
     * @var array<string, list<string>>
     */
    private const EXTRA_FEATURES = [
        // PLAN-10: the permanent free plan has no extra.
        'free' => [],
        // PLAN-01: Starter has transfers; reorder / profit reports / activity log come with the
        // control bundle and mixes as an add-on. Growth includes mixes.
        'basic' => ['transfers.manage'],
        'pro' => ['transfers.manage', 'mixes.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs'],
        // custom.domain stays off until Phase 3 (Q-E9: automatic TLS per domain); pos.offline is
        // off everywhere until its routes exist (end of Phase 2). api.access is hidden and does
        // nothing, so Enterprise no longer lists it (owner decision 2026-11-15); Enterprise is the
        // service plan: premium support and (yearly) onboarding are included (AddonCatalog).
        'enterprise' => ['transfers.manage', 'mixes.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs'],
    ];

    /**
     * Core features a plan does NOT get (PLAN-10). Every other core feature is on in every plan.
     *
     * @var array<string, list<string>>
     */
    private const REMOVED_CORE_FEATURES = [
        'free' => ['reports.export', 'telegram.notifications'],
    ];

    /**
     * @var array<string, array<string, string|int|bool|null>>
     */
    private const PLANS = [
        'free' => [
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
            'trial_days' => 0,
            'is_free' => true,
            'is_active' => true,
            'is_public' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ],
        'basic' => [
            'price_monthly' => '499.000',
            'price_yearly' => '4990.000',
            'founder_price_monthly' => '349.000',
            'founder_price_yearly' => '3490.000',
            'founder_transition_price_monthly' => '429.000',
            'founder_transition_price_yearly' => '4290.000',
            'compare_at_price_monthly' => null,
            'compare_at_price_yearly' => null,
            'promo_starts_at' => null,
            'promo_ends_at' => null,
            'max_stores' => 1,
            'max_warehouses' => 1,
            'max_vans' => 0,
            'max_users' => 3,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => 2048,
            'trial_days' => 0,
            'is_free' => false,
            'is_active' => true,
            'is_public' => true,
            'is_popular' => false,
            'sort_order' => 2,
        ],
        'pro' => [
            'price_monthly' => '999.000',
            'price_yearly' => '9990.000',
            'founder_price_monthly' => '699.000',
            'founder_price_yearly' => '6990.000',
            'founder_transition_price_monthly' => '849.000',
            'founder_transition_price_yearly' => '8490.000',
            'compare_at_price_monthly' => null,
            'compare_at_price_yearly' => null,
            'promo_starts_at' => null,
            'promo_ends_at' => null,
            'max_stores' => 3,
            'max_warehouses' => 2,
            'max_vans' => 1,
            'max_users' => 8,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => 10240,
            'trial_days' => 0,
            'is_free' => false,
            'is_active' => true,
            'is_public' => true,
            'is_popular' => true,
            'sort_order' => 3,
        ],
        'enterprise' => [
            'price_monthly' => '1499.000',
            'price_yearly' => '14990.000',
            'founder_price_monthly' => '1099.000',
            'founder_price_yearly' => '10990.000',
            'founder_transition_price_monthly' => '1299.000',
            'founder_transition_price_yearly' => '12990.000',
            'compare_at_price_monthly' => null,
            'compare_at_price_yearly' => null,
            'promo_starts_at' => null,
            'promo_ends_at' => null,
            'max_stores' => 10,
            'max_warehouses' => 5,
            'max_vans' => 5,
            'max_users' => 25,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => 51200,
            'trial_days' => 0,
            'is_free' => false,
            'is_active' => true,
            'is_public' => true,
            'is_popular' => false,
            'sort_order' => 4,
        ],
    ];

    /**
     * @return list<array<string, mixed>> each plan: slug, name_key, description_key, the
     *                                    PLANS attributes and `features` (every catalog key => bool)
     */
    public static function all(): array
    {
        $plans = [];

        foreach (self::PLANS as $slug => $attributes) {
            $plans[] = ['slug' => $slug] + $attributes + [
                'name_key' => self::nameKey($slug),
                'description_key' => 'plans.plans.'.$slug.'.description',
                'features' => self::features($slug),
            ];
        }

        return $plans;
    }

    /**
     * Every catalog feature key => on/off for $slug (core on unless the plan removes it, listed
     * extras on, rest off).
     *
     * @return array<string, bool>
     */
    public static function features(string $slug): array
    {
        $core = array_diff(FeatureCatalog::coreKeys(), self::removedCoreFeatures($slug));
        $on = array_merge($core, self::EXTRA_FEATURES[$slug] ?? []);
        $features = [];

        foreach (FeatureCatalog::keys() as $key) {
            $features[$key] = in_array($key, $on, true);
        }

        return $features;
    }

    /**
     * PLAN-10: core features $slug does NOT get (every other core feature is on).
     *
     * @return list<string>
     */
    public static function removedCoreFeatures(string $slug): array
    {
        return self::REMOVED_CORE_FEATURES[$slug] ?? [];
    }

    public static function nameKey(string $slug): string
    {
        return 'plans.plans.'.$slug.'.name';
    }
}
