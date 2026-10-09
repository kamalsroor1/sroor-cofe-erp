<?php

declare(strict_types=1);

namespace Database\Seeders\Catalog;

use App\Enums\Billing\AddonType;

/**
 * ENTI-1.8: the approved add-on catalog (central `addons` + `plan_addon`).
 *
 * Source: docs/03-architecture/pricing-model-recommendation-2026-10.md §7, Q-E2, Q-E3,
 * Q-E9 and CTO W1 Q3/Q4 [2026-10-09]:
 * - every price is an explicit stored DECIMAL(12,3) string, VAT excluded;
 * - yearly = 10 months, stored explicitly per add-on AND per volume tier
 *   (store/van 2,490 / 2,120 / 1,870), never derived at runtime;
 * - volume tiers price the WHOLE quantity at the tier price (Q-E3); users get no volume
 *   discount other than 59 from the 6th user (Q-E2);
 * - `premium_support` is a monthly RECURRING add-on (299), `onboarding` a one-time
 *   SERVICE (1,500);
 * - `custom.domain` is hidden (is_public = false) and not available on any plan until
 *   Phase 3 (Q-E9). Hidden = is_public false; there is no separate "purchasable" flag.
 *
 * `bundled_limits` = limits one unit raises, read by the entitlement engine (ENTI-2.2):
 * stores / warehouses / vans / users / items / storage_mb.
 */
final class AddonCatalog
{
    private const ALL_PAID = ['basic', 'pro', 'enterprise'];

    /**
     * @var list<array{key: string, lang: string, type: AddonType, feature_key: string|null, bundled_limits: array<string, int>|null, unit_price: string, yearly_price: string|null, price_tiers: list<array{min_qty: int, unit_price: string, yearly_price: string}>|null, is_public: bool, plans: list<string>, unavailable_on: list<string>}>
     */
    private const ADDONS = [
        [
            'key' => 'addon.store', 'lang' => 'store', 'type' => AddonType::Recurring, 'feature_key' => null,
            'bundled_limits' => ['stores' => 1, 'users' => 1],
            'unit_price' => '249.000', 'yearly_price' => '2490.000',
            'price_tiers' => [
                ['min_qty' => 3, 'unit_price' => '212.000', 'yearly_price' => '2120.000'],
                ['min_qty' => 6, 'unit_price' => '187.000', 'yearly_price' => '1870.000'],
            ],
            'is_public' => true, 'plans' => self::ALL_PAID, 'unavailable_on' => [],
        ],
        [
            'key' => 'addon.warehouse', 'lang' => 'warehouse', 'type' => AddonType::Recurring, 'feature_key' => null,
            'bundled_limits' => ['warehouses' => 1],
            'unit_price' => '149.000', 'yearly_price' => '1490.000', 'price_tiers' => null,
            'is_public' => true, 'plans' => self::ALL_PAID, 'unavailable_on' => [],
        ],
        [
            'key' => 'addon.van', 'lang' => 'van', 'type' => AddonType::Recurring, 'feature_key' => null,
            'bundled_limits' => ['vans' => 1, 'users' => 1],
            'unit_price' => '249.000', 'yearly_price' => '2490.000',
            'price_tiers' => [
                ['min_qty' => 3, 'unit_price' => '212.000', 'yearly_price' => '2120.000'],
                ['min_qty' => 6, 'unit_price' => '187.000', 'yearly_price' => '1870.000'],
            ],
            'is_public' => true, 'plans' => self::ALL_PAID, 'unavailable_on' => [],
        ],
        [
            'key' => 'addon.user', 'lang' => 'user', 'type' => AddonType::Recurring, 'feature_key' => null,
            'bundled_limits' => ['users' => 1],
            'unit_price' => '79.000', 'yearly_price' => '790.000',
            'price_tiers' => [
                ['min_qty' => 6, 'unit_price' => '59.000', 'yearly_price' => '590.000'],
            ],
            'is_public' => true, 'plans' => self::ALL_PAID, 'unavailable_on' => [],
        ],
        [
            'key' => 'addon.storage_10gb', 'lang' => 'storage_10gb', 'type' => AddonType::Recurring, 'feature_key' => null,
            'bundled_limits' => ['storage_mb' => 10240],
            'unit_price' => '49.000', 'yearly_price' => '490.000', 'price_tiers' => null,
            'is_public' => true, 'plans' => self::ALL_PAID, 'unavailable_on' => [],
        ],
        [
            'key' => 'addon.items_5k', 'lang' => 'items_5k', 'type' => AddonType::Recurring, 'feature_key' => null,
            'bundled_limits' => ['items' => 5000],
            'unit_price' => '99.000', 'yearly_price' => '990.000', 'price_tiers' => null,
            'is_public' => true, 'plans' => ['basic'], 'unavailable_on' => [],
        ],
        [
            'key' => 'mixes.manage', 'lang' => 'mixes', 'type' => AddonType::Recurring, 'feature_key' => 'mixes.manage',
            'bundled_limits' => null,
            'unit_price' => '199.000', 'yearly_price' => '1990.000', 'price_tiers' => null,
            'is_public' => true, 'plans' => ['basic', 'pro'], 'unavailable_on' => [],
        ],
        [
            'key' => 'reports.advanced', 'lang' => 'reports_advanced', 'type' => AddonType::Recurring, 'feature_key' => 'reports.advanced',
            'bundled_limits' => null,
            'unit_price' => '149.000', 'yearly_price' => '1490.000', 'price_tiers' => null,
            'is_public' => true, 'plans' => ['basic'], 'unavailable_on' => [],
        ],
        [
            'key' => 'audit.logs', 'lang' => 'audit_logs', 'type' => AddonType::Recurring, 'feature_key' => 'audit.logs',
            'bundled_limits' => null,
            'unit_price' => '99.000', 'yearly_price' => '990.000', 'price_tiers' => null,
            'is_public' => true, 'plans' => ['basic'], 'unavailable_on' => [],
        ],
        [
            'key' => 'api.access', 'lang' => 'api_access', 'type' => AddonType::Recurring, 'feature_key' => 'api.access',
            'bundled_limits' => null,
            'unit_price' => '299.000', 'yearly_price' => '2990.000', 'price_tiers' => null,
            'is_public' => true, 'plans' => ['pro'], 'unavailable_on' => [],
        ],
        [
            'key' => 'custom.domain', 'lang' => 'custom_domain', 'type' => AddonType::Recurring, 'feature_key' => 'custom.domain',
            'bundled_limits' => null,
            'unit_price' => '149.000', 'yearly_price' => '1490.000', 'price_tiers' => null,
            'is_public' => false, 'plans' => [], 'unavailable_on' => ['pro'],
        ],
        [
            'key' => 'addon.premium_support', 'lang' => 'premium_support', 'type' => AddonType::Recurring, 'feature_key' => null,
            'bundled_limits' => null,
            'unit_price' => '299.000', 'yearly_price' => '2990.000', 'price_tiers' => null,
            'is_public' => true, 'plans' => ['basic', 'pro'], 'unavailable_on' => [],
        ],
        [
            'key' => 'service.onboarding', 'lang' => 'onboarding', 'type' => AddonType::Service, 'feature_key' => null,
            'bundled_limits' => null,
            'unit_price' => '1500.000', 'yearly_price' => null, 'price_tiers' => null,
            'is_public' => true, 'plans' => self::ALL_PAID, 'unavailable_on' => [],
        ],
    ];

    /**
     * @return list<array{key: string, name_key: string, description_key: string, type: AddonType, feature_key: string|null, bundled_limits: array<string, int>|null, unit_price: string, yearly_price: string|null, price_tiers: list<array{min_qty: int, unit_price: string, yearly_price: string}>|null, is_active: bool, is_public: bool, sort_order: int}>
     */
    public static function all(): array
    {
        $addons = [];
        $order = 0;

        foreach (self::ADDONS as $addon) {
            $addons[] = [
                'key' => $addon['key'],
                'name_key' => 'plans.addons.'.$addon['lang'].'.name',
                'description_key' => 'plans.addons.'.$addon['lang'].'.description',
                'type' => $addon['type'],
                'feature_key' => $addon['feature_key'],
                'bundled_limits' => $addon['bundled_limits'],
                'unit_price' => $addon['unit_price'],
                'yearly_price' => $addon['yearly_price'],
                'price_tiers' => $addon['price_tiers'],
                'is_active' => true,
                'is_public' => $addon['is_public'],
                'sort_order' => ++$order,
            ];
        }

        return $addons;
    }

    /**
     * `plan_addon` rows: which add-on is offered on which catalog plan. An add-on listed in
     * `unavailable_on` gets a row with is_available = false (known to the plan, not sold).
     * No included quantities: an add-on that a plan includes is simply on in the plan's
     * features (e.g. mixes.manage on Enterprise), and no per-plan price overrides.
     *
     * @return list<array{plan: string, addon: string, is_available: bool}>
     */
    public static function availability(): array
    {
        $rows = [];

        foreach (self::ADDONS as $addon) {
            foreach ($addon['plans'] as $slug) {
                $rows[] = ['plan' => $slug, 'addon' => $addon['key'], 'is_available' => true];
            }
            foreach ($addon['unavailable_on'] as $slug) {
                $rows[] = ['plan' => $slug, 'addon' => $addon['key'], 'is_available' => false];
            }
        }

        return $rows;
    }
}
