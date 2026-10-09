<?php

declare(strict_types=1);

namespace Database\Seeders\Catalog;

/**
 * ENTI-1.8: the approved plan ladder (central `plans`).
 *
 * Source: docs/03-architecture/pricing-model-recommendation-2026-10.md §4 (prices, limits),
 * §5 (feature matrix) and CTO W1 Q4 [2026-10-09]:
 * - monthly 449 / 899 / 1,799 and yearly = 10 months 4,490 / 8,990 / 17,990;
 * - founder prices (first 50 paying customers, 12 months, ENTI-1.7) 299 / 599 / 999 monthly
 *   and 2,990 / 5,990 / 9,990 yearly;
 * - every price EXCLUDES VAT (14% is added on the SaaS invoice, ENTI-3.2);
 * - NULL limit = unlimited (never a 999… sentinel);
 * - only the trial plan has trial days; paid plans have trial_days = 0, so a tenant that
 *   signs up on them waits in pending_payment until paid (CTO W1 Q4).
 *
 * Money is a DECIMAL(12,3) string. Names/descriptions are keys in lang/{ar,en}/plans.php.
 */
final class PlanCatalog
{
    /** Slugs owned by the catalog; any other plan is a super-admin custom plan and is never touched. */
    public const SLUGS = ['free', 'basic', 'pro', 'enterprise'];

    /**
     * Non-core features per plan (core features are on in every plan).
     *
     * @var array<string, list<string>>
     */
    private const EXTRA_FEATURES = [
        // The trial shows the Growth plan in full (without the API/domain), §5.
        'free' => ['transfers.manage', 'mixes.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs'],
        'basic' => [],
        'pro' => ['transfers.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs'],
        // custom.domain stays off until Phase 3 (Q-E9: automatic TLS per domain); pos.offline is
        // off everywhere until its routes exist (end of Phase 2).
        'enterprise' => ['transfers.manage', 'mixes.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs', 'api.access'],
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
            'max_stores' => 1,
            'max_warehouses' => 0,
            'max_vans' => 0,
            'max_users' => 3,
            'max_items' => 200,
            'max_invoices_per_month' => 300,
            'max_storage_mb' => 500,
            'trial_days' => 14,
            'is_active' => true,
            'is_public' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ],
        'basic' => [
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'founder_price_monthly' => '299.000',
            'founder_price_yearly' => '2990.000',
            'max_stores' => 1,
            'max_warehouses' => 1,
            'max_vans' => 0,
            'max_users' => 3,
            'max_items' => 3000,
            'max_invoices_per_month' => 6000,
            'max_storage_mb' => 2048,
            'trial_days' => 0,
            'is_active' => true,
            'is_public' => true,
            'is_popular' => false,
            'sort_order' => 2,
        ],
        'pro' => [
            'price_monthly' => '899.000',
            'price_yearly' => '8990.000',
            'founder_price_monthly' => '599.000',
            'founder_price_yearly' => '5990.000',
            'max_stores' => 3,
            'max_warehouses' => 2,
            'max_vans' => 1,
            'max_users' => 8,
            'max_items' => 20000,
            'max_invoices_per_month' => 30000,
            'max_storage_mb' => 10240,
            'trial_days' => 0,
            'is_active' => true,
            'is_public' => true,
            'is_popular' => true,
            'sort_order' => 3,
        ],
        'enterprise' => [
            'price_monthly' => '1799.000',
            'price_yearly' => '17990.000',
            'founder_price_monthly' => '999.000',
            'founder_price_yearly' => '9990.000',
            'max_stores' => 10,
            'max_warehouses' => 5,
            'max_vans' => 5,
            'max_users' => 25,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => 51200,
            'trial_days' => 0,
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
     * Every catalog feature key => on/off for $slug (core on, listed extras on, rest off).
     *
     * @return array<string, bool>
     */
    public static function features(string $slug): array
    {
        $on = array_merge(FeatureCatalog::coreKeys(), self::EXTRA_FEATURES[$slug] ?? []);
        $features = [];

        foreach (FeatureCatalog::keys() as $key) {
            $features[$key] = in_array($key, $on, true);
        }

        return $features;
    }

    public static function nameKey(string $slug): string
    {
        return 'plans.plans.'.$slug.'.name';
    }
}
