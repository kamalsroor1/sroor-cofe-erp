<?php

declare(strict_types=1);

use App\Services\Entitlements\TenantEntitlementService;
use App\Support\TenantCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-06 follow-up (owner decision, central DB): the approved catalog no longer gives the Basic
 * plan transfers.manage, mixes.manage, purchases.reorder and audit.logs. Tenants that are on
 * Basic TODAY keep them: this adds the four keys to each Basic tenant's super-admin overrides
 * (tenants.enabled_features, the same list TenantEntitlementService::toggleFeatureOverride()
 * edits, and the one the console "override" toggle shows). Plans are untouched, so tenants that
 * subscribe to Basic afterwards do not get them.
 *
 * Idempotent: keys already present (also under a legacy alias) are not duplicated, other
 * overrides are preserved, and tenants on any other plan are never touched. A second run
 * changes nothing. The entitlements cache of every changed tenant is invalidated.
 *
 * down() is a documented no-op: after the fact it is impossible to tell a grandfathered key from
 * an override the super-admin set by hand, and silently revoking either would remove paid access.
 * Remove a tenant's override from the console if a rollback is really wanted.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const GRANDFATHERED = ['transfers.manage', 'mixes.manage', 'purchases.reorder', 'audit.logs'];

    public function up(): void
    {
        foreach (['plans', 'tenants'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }
        if (! Schema::hasColumn('tenants', 'enabled_features') || ! Schema::hasColumn('tenants', 'plan_id')) {
            return;
        }

        $basicIds = DB::table('plans')->where('slug', 'basic')->pluck('id')->all();
        if ($basicIds === []) {
            return;
        }

        $aliases = (array) config('entitlements.aliases', []);
        $canonical = static function (string $key) use ($aliases): string {
            $mapped = $aliases[$key] ?? $key;

            return is_string($mapped) && $mapped !== '' ? $mapped : $key;
        };

        DB::table('tenants')
            ->whereIn('plan_id', $basicIds)
            ->orderBy('id')
            ->get(['id', 'enabled_features'])
            ->each(function (object $tenant) use ($canonical): void {
                $raw = $tenant->enabled_features;
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                $current = is_array($decoded) ? $decoded : [];

                // Overrides are a list of keys; a legacy `key => bool` map is tolerated.
                $isList = array_is_list($current);
                $present = array_map(
                    static fn (mixed $value, int|string $key): string => $isList ? (is_string($value) ? $value : '') : (string) $key,
                    $current,
                    array_keys($current),
                );
                $presentCanonical = array_map($canonical, $present);

                $missing = array_values(array_filter(
                    self::GRANDFATHERED,
                    static fn (string $key): bool => ! in_array($key, $presentCanonical, true),
                ));
                if ($missing === []) {
                    return;
                }

                if ($isList) {
                    $next = array_values(array_merge($current, $missing));
                } else {
                    $next = $current;
                    foreach ($missing as $key) {
                        $next[$key] = true;
                    }
                }

                DB::table('tenants')->where('id', $tenant->id)->update([
                    'enabled_features' => json_encode($next, JSON_UNESCAPED_UNICODE),
                ]);

                // Explicit tenant scope: this runs in central context.
                TenantCache::bumpFor((string) $tenant->id, TenantEntitlementService::CACHE_NAMESPACE);
            });
    }

    public function down(): void {}
};
