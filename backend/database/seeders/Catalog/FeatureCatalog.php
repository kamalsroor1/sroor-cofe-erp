<?php

declare(strict_types=1);

namespace Database\Seeders\Catalog;

/**
 * ENTI-1.8: the approved plan-feature registry (central `plan_features`).
 *
 * Source: docs/03-architecture/pricing-model-recommendation-2026-10.md §5 + §6, Q-E8 and
 * CTO 2026-10-09:
 * - 18 CORE features = in every paid plan (the 17 of Q-E8 + quotations.manage, which has
 *   no routes in Phase 1);
 * - `blender.access` is renamed to the neutral `mixes.manage` (LEGACY_RENAMES);
 * - hidden keys (is_public = false): `pos.offline` (no route until the end of Phase 2)
 *   and `custom.domain` (Q-E9, hidden until Phase 3).
 *
 * Names and descriptions are translation keys in lang/{ar,en}/plans.php
 * (plans.features.<lang_slug>.name|description); no brand or trade-specific wording.
 */
final class FeatureCatalog
{
    /** Legacy key => approved key, applied to plan_features, plans.features and tenants.enabled_features. */
    public const LEGACY_RENAMES = [
        'blender.access' => 'mixes.manage',
    ];

    /**
     * key => [module, is_core, is_public], in display order.
     *
     * @var array<string, array{0: string, 1: bool, 2: bool}>
     */
    private const FEATURES = [
        'pos.access' => ['sales', true, true],
        'invoices.create' => ['sales', true, true],
        'invoices.edit' => ['sales', true, true],
        'whatsapp.share' => ['sales', true, true],
        'quotations.manage' => ['sales', true, true],
        'pos.offline' => ['sales', false, false],
        'items.manage' => ['inventory', true, true],
        'items.movements' => ['inventory', true, true],
        'transfers.manage' => ['inventory', false, true],
        'mixes.manage' => ['inventory', false, true],
        'purchases.manage' => ['purchases', true, true],
        'purchases.reorder' => ['purchases', false, true],
        'expenses.manage' => ['finance', true, true],
        'payments.manage' => ['finance', true, true],
        'shifts.manage' => ['finance', true, true],
        'treasury.view' => ['finance', true, true],
        'returns.manage' => ['finance', true, true],
        'reports.basic' => ['reports', true, true],
        'reports.advanced' => ['reports', false, true],
        'reports.export' => ['reports', true, true],
        'audit.logs' => ['system', false, true],
        'printing.thermal' => ['system', true, true],
        'printing.a4' => ['system', true, true],
        'telegram.notifications' => ['system', true, true],
        'api.access' => ['system', false, true],
        'custom.domain' => ['system', false, false],
    ];

    /**
     * @return list<array{key: string, module: string, is_core: bool, is_public: bool, sort_order: int, name_key: string, description_key: string}>
     */
    public static function all(): array
    {
        $features = [];
        $order = 0;

        foreach (self::FEATURES as $key => [$module, $isCore, $isPublic]) {
            $features[] = [
                'key' => $key,
                'module' => $module,
                'is_core' => $isCore,
                'is_public' => $isPublic,
                'sort_order' => ++$order,
                'name_key' => self::nameKey($key),
                'description_key' => 'plans.features.'.self::langSlug($key).'.description',
            ];
        }

        return $features;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::FEATURES);
    }

    /**
     * @return list<string>
     */
    public static function coreKeys(): array
    {
        return array_keys(array_filter(self::FEATURES, static fn (array $feature): bool => $feature[1]));
    }

    /**
     * @return list<string>
     */
    public static function hiddenKeys(): array
    {
        return array_keys(array_filter(self::FEATURES, static fn (array $feature): bool => ! $feature[2]));
    }

    public static function nameKey(string $key): string
    {
        return 'plans.features.'.self::langSlug($key).'.name';
    }

    /** `pos.access` -> `pos_access`: translation keys cannot contain the dot of a feature key. */
    public static function langSlug(string $key): string
    {
        return str_replace('.', '_', $key);
    }
}
