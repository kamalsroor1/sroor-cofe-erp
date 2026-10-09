<?php

declare(strict_types=1);

namespace Database\Seeders\Catalog;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use JsonException;

/**
 * ENTI-1.8: writes the approved catalog (FeatureCatalog, PlanCatalog, AddonCatalog) to the
 * CENTRAL tables plan_features, plans, addons and plan_addon.
 *
 * Two modes:
 * - seedMissing() (PlansAndFeaturesSeeder, safe to run any number of times): creates what
 *   is missing (firstOrCreate semantics) and only MERGES missing keys into existing rows:
 *   missing feature keys in plans.features and a missing name_key. Prices, limits, flags,
 *   names and every value a super-admin edited are never overwritten.
 * - applyApproved() (one-time data migration 2026_10_10_200610): writes the approved prices,
 *   limits, features and add-ons over the catalog rows of an existing installation. It does
 *   nothing on an empty database (fresh install / test DB): the seeder creates the catalog.
 *
 * Both first rename legacy feature keys (blender.access -> mixes.manage) everywhere.
 *
 * The query builder is used on purpose instead of the Eloquent models: a migration must not
 * depend on how a model looks later, and every row is filtered to the columns that exist.
 * Names and descriptions are resolved from lang/{ar,en}/plans.php in the application locale
 * for the legacy single-language columns; the `name_key` columns let the UI translate.
 */
final class CatalogWriter
{
    /** @var array<string, list<string>> */
    private array $columns = [];

    public function __construct(private readonly Connection $db) {}

    public function seedMissing(): void
    {
        $this->db->transaction(function (): void {
            $this->renameLegacyFeatureKeys();
            $now = Carbon::now();

            foreach (FeatureCatalog::all() as $feature) {
                $existing = $this->db->table('plan_features')->where('key', $feature['key'])->first();

                if ($existing === null) {
                    $this->insert('plan_features', $this->featureRow($feature), $now);
                } elseif ($this->hasColumn('plan_features', 'name_key') && ($existing->name_key ?? null) === null) {
                    $this->update('plan_features', (int) $existing->id, ['name_key' => $feature['name_key']], $now);
                }
            }

            foreach (PlanCatalog::all() as $plan) {
                $existing = $this->db->table('plans')->where('slug', $plan['slug'])->first();

                if ($existing === null) {
                    $this->insert('plans', $this->planRow($plan), $now);

                    continue;
                }

                $changes = [];
                $features = $this->decodeMap($existing->features ?? null);
                $merged = $features + $plan['features'];
                if ($merged !== $features) {
                    $changes['features'] = $this->json($merged);
                }
                if ($this->hasColumn('plans', 'name_key') && ($existing->name_key ?? null) === null) {
                    $changes['name_key'] = $plan['name_key'];
                }

                if ($changes !== []) {
                    $this->update('plans', (int) $existing->id, $changes, $now);
                }
            }

            foreach (AddonCatalog::all() as $addon) {
                if (! $this->db->table('addons')->where('key', $addon['key'])->exists()) {
                    $this->insert('addons', $this->addonRow($addon), $now);
                }
            }

            $this->writeAvailability($now, false);
        });
    }

    /**
     * @return bool false when the database has no catalog plan yet (nothing was written
     *              except the legacy key rename)
     */
    public function applyApproved(): bool
    {
        return $this->db->transaction(function (): bool {
            $this->renameLegacyFeatureKeys();

            if (! $this->db->table('plans')->whereIn('slug', PlanCatalog::SLUGS)->exists()) {
                return false;
            }

            $now = Carbon::now();

            foreach (FeatureCatalog::all() as $feature) {
                $this->upsert('plan_features', ['key' => $feature['key']], $this->featureRow($feature), $now);
            }

            foreach (PlanCatalog::all() as $plan) {
                $existing = $this->db->table('plans')->where('slug', $plan['slug'])->first();
                $row = $this->planRow($plan);

                if ($existing !== null) {
                    // Approved values win; keys the catalog does not know are kept.
                    $row['features'] = $this->json(array_merge($this->decodeMap($existing->features ?? null), $plan['features']));
                }

                $this->upsert('plans', ['slug' => $plan['slug']], $row, $now);
            }

            foreach (AddonCatalog::all() as $addon) {
                $this->upsert('addons', ['key' => $addon['key']], $this->addonRow($addon), $now);
            }

            $this->writeAvailability($now, true);

            return true;
        });
    }

    public function renameLegacyFeatureKeys(): void
    {
        foreach (FeatureCatalog::LEGACY_RENAMES as $from => $to) {
            $this->renameFeatureKey($from, $to);
        }
    }

    /** Reverse of renameLegacyFeatureKeys(), for the data migration's down(). */
    public function restoreLegacyFeatureKeys(): void
    {
        foreach (FeatureCatalog::LEGACY_RENAMES as $from => $to) {
            $this->renameFeatureKey($to, $from);
        }
    }

    /**
     * Rename one feature key in plan_features.key, the keys of plans.features and the values
     * of tenants.enabled_features (a list of keys; a legacy key => bool map is handled too).
     * When both keys already exist the target wins and the source is removed.
     */
    private function renameFeatureKey(string $from, string $to): void
    {
        $this->db->transaction(function () use ($from, $to): void {
            if ($this->hasTable('plan_features')) {
                $source = $this->db->table('plan_features')->where('key', $from)->first();
                if ($source !== null) {
                    if ($this->db->table('plan_features')->where('key', $to)->exists()) {
                        $this->db->table('plan_features')->where('id', $source->id)->delete();
                    } else {
                        $this->db->table('plan_features')->where('id', $source->id)->update(['key' => $to]);
                    }
                }
            }

            if ($this->hasTable('plans')) {
                foreach ($this->db->table('plans')->orderBy('id')->get(['id', 'features']) as $plan) {
                    $features = $this->decodeMap($plan->features);
                    if (! array_key_exists($from, $features)) {
                        continue;
                    }

                    $renamed = [];
                    foreach ($features as $key => $value) {
                        if ($key === $from) {
                            if (! array_key_exists($to, $features)) {
                                $renamed[$to] = $value;
                            }

                            continue;
                        }
                        $renamed[$key] = $value;
                    }

                    $this->db->table('plans')->where('id', $plan->id)->update(['features' => $this->json($renamed)]);
                }
            }

            if ($this->hasTable('tenants') && $this->hasColumn('tenants', 'enabled_features')) {
                $tenants = $this->db->table('tenants')
                    ->whereNotNull('enabled_features')
                    ->where('enabled_features', 'like', '%'.$from.'%')
                    ->orderBy('id')
                    ->get(['id', 'enabled_features']);

                foreach ($tenants as $tenant) {
                    $renamed = $this->renameInOverrides($tenant->enabled_features, $from, $to);
                    if ($renamed !== null) {
                        $this->db->table('tenants')->where('id', $tenant->id)->update(['enabled_features' => $this->json($renamed)]);
                    }
                }
            }
        });
    }

    /**
     * @return array<array-key, mixed>|null the renamed overrides, or null when nothing changed
     */
    private function renameInOverrides(mixed $raw, string $from, string $to): ?array
    {
        $overrides = $this->decodeMap($raw);

        if (array_is_list($overrides)) {
            if (! in_array($from, $overrides, true)) {
                return null;
            }

            $renamed = array_map(static fn (mixed $key): mixed => $key === $from ? $to : $key, $overrides);

            return array_values(array_unique($renamed, SORT_REGULAR));
        }

        if (! array_key_exists($from, $overrides)) {
            return null;
        }

        $value = $overrides[$from];
        unset($overrides[$from]);
        if (! array_key_exists($to, $overrides)) {
            $overrides[$to] = $value;
        }

        return $overrides;
    }

    /**
     * plan_addon rows of AddonCatalog::availability(). $enforce = false only creates missing
     * rows (seeder); true also resets is_available / included quantity / overrides (migration).
     */
    private function writeAvailability(Carbon $now, bool $enforce): void
    {
        $planIds = $this->db->table('plans')->whereIn('slug', PlanCatalog::SLUGS)->pluck('id', 'slug');
        $addonIds = $this->db->table('addons')->pluck('id', 'key');

        foreach (AddonCatalog::availability() as $row) {
            $planId = $planIds[$row['plan']] ?? null;
            $addonId = $addonIds[$row['addon']] ?? null;
            if ($planId === null || $addonId === null) {
                continue;
            }

            $keys = ['plan_id' => (int) $planId, 'addon_id' => (int) $addonId];
            $values = [
                'is_available' => $row['is_available'],
                'included_quantity' => 0,
                'unit_price_override' => null,
                'yearly_price_override' => null,
            ];

            if ($enforce) {
                $this->upsert('plan_addon', $keys, $keys + $values, $now);
            } elseif (! $this->db->table('plan_addon')->where($keys)->exists()) {
                $this->insert('plan_addon', $keys + $values, $now);
            }
        }
    }

    /**
     * @param  array{key: string, module: string, is_core: bool, is_public: bool, sort_order: int, name_key: string, description_key: string}  $feature
     * @return array<string, mixed>
     */
    private function featureRow(array $feature): array
    {
        return [
            'key' => $feature['key'],
            'name' => $this->text($feature['name_key']),
            'description' => $this->text($feature['description_key']),
            'module' => $feature['module'],
            'type' => 'boolean',
            'default_value' => $feature['is_core'] ? 'true' : 'false',
            'icon' => null,
            'sort_order' => $feature['sort_order'],
            'name_key' => $feature['name_key'],
            'is_core' => $feature['is_core'],
            'is_public' => $feature['is_public'],
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function planRow(array $plan): array
    {
        $row = [
            'name' => $this->text((string) $plan['name_key']),
            'description' => $this->text((string) $plan['description_key']),
            'features' => $this->json($plan['features']),
        ];

        foreach ($plan as $column => $value) {
            if (! in_array($column, ['description_key', 'features'], true)) {
                $row[$column] = $value;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $addon
     * @return array<string, mixed>
     */
    private function addonRow(array $addon): array
    {
        return [
            'key' => $addon['key'],
            'name_key' => $addon['name_key'],
            'type' => $addon['type']->value,
            'feature_key' => $addon['feature_key'],
            'bundled_limits' => $addon['bundled_limits'] === null ? null : $this->json($addon['bundled_limits']),
            'unit_price' => $addon['unit_price'],
            'yearly_price' => $addon['yearly_price'],
            'price_tiers' => $addon['price_tiers'] === null ? null : $this->json($addon['price_tiers']),
            'is_active' => $addon['is_active'],
            'is_public' => $addon['is_public'],
            'sort_order' => $addon['sort_order'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insert(string $table, array $row, Carbon $now): void
    {
        $this->db->table($table)->insert($this->onlyExisting($table, $row + ['created_at' => $now, 'updated_at' => $now]));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function update(string $table, int $id, array $changes, Carbon $now): void
    {
        $this->db->table($table)->where('id', $id)->update($this->onlyExisting($table, $changes + ['updated_at' => $now]));
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $row
     */
    private function upsert(string $table, array $keys, array $row, Carbon $now): void
    {
        $id = $this->db->table($table)->where($keys)->value('id');

        if ($id === null) {
            $this->insert($table, $keys + $row, $now);

            return;
        }

        $this->update($table, (int) $id, $row, $now);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function onlyExisting(string $table, array $row): array
    {
        return array_intersect_key($row, array_flip($this->columnsOf($table)));
    }

    private function hasTable(string $table): bool
    {
        return $this->db->getSchemaBuilder()->hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columnsOf($table), true);
    }

    /**
     * @return list<string>
     */
    private function columnsOf(string $table): array
    {
        return $this->columns[$table] ??= $this->db->getSchemaBuilder()->getColumnListing($table);
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

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
