<?php

declare(strict_types=1);

use App\Services\Entitlements\TenantEntitlementService;
use App\Support\TenantCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-12 (CENTRAL DB): the grant-only list in `tenants.enabled_features` becomes explicit
 * `grant` rows of `tenant_feature_overrides` (reason = the legacy marker, no expiry, no author),
 * and the list is emptied so a grant lives in exactly one place. Keys are stored canonical
 * (config/entitlements.aliases); a key already present as a row is left alone. Idempotent.
 *
 * down() puts the marker rows back into the list and removes them.
 */
return new class extends Migration
{
    private const REASON_KEY = 'plan_overrides.legacy_migrated_reason';

    public function up(): void
    {
        if (! Schema::hasTable('tenant_feature_overrides') || ! Schema::hasColumn('tenants', 'enabled_features')) {
            return;
        }

        $aliases = (array) config('entitlements.aliases', []);
        $canonical = static function (string $key) use ($aliases): string {
            $mapped = $aliases[$key] ?? $key;

            return is_string($mapped) && $mapped !== '' ? $mapped : $key;
        };
        $reason = (string) trans(self::REASON_KEY, [], 'ar');
        $now = now();

        DB::table('tenants')->whereNotNull('enabled_features')->orderBy('id')->get(['id', 'enabled_features'])
            ->each(function (object $tenant) use ($canonical, $reason, $now): void {
                $raw = $tenant->enabled_features;
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                $current = is_array($decoded) ? $decoded : [];

                // A list of keys; a legacy `key => bool` map is tolerated (only true entries count).
                $keys = array_is_list($current)
                    ? array_filter($current, 'is_string')
                    : array_keys(array_filter($current, static fn (mixed $on): bool => $on === true));

                $keys = array_values(array_unique(array_map($canonical, array_filter($keys, static fn (string $k): bool => $k !== ''))));

                DB::transaction(function () use ($tenant, $keys, $reason, $now): void {
                    foreach ($keys as $key) {
                        $exists = DB::table('tenant_feature_overrides')
                            ->where('tenant_id', $tenant->id)->where('feature_key', $key)->exists();

                        if (! $exists) {
                            DB::table('tenant_feature_overrides')->insert([
                                'tenant_id' => $tenant->id,
                                'feature_key' => $key,
                                'mode' => 'grant',
                                'reason' => $reason,
                                'expires_at' => null,
                                'created_by' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }

                    DB::table('tenants')->where('id', $tenant->id)->update(['enabled_features' => json_encode([])]);
                });

                TenantCache::bumpFor((string) $tenant->id, TenantEntitlementService::CACHE_NAMESPACE);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_feature_overrides') || ! Schema::hasColumn('tenants', 'enabled_features')) {
            return;
        }

        $reason = (string) trans(self::REASON_KEY, [], 'ar');

        DB::table('tenant_feature_overrides')
            ->where('mode', 'grant')->where('reason', $reason)->whereNull('created_by')
            ->orderBy('id')->get()
            ->groupBy('tenant_id')
            ->each(function ($rows, $tenantId): void {
                $existing = DB::table('tenants')->where('id', $tenantId)->value('enabled_features');
                $decoded = is_string($existing) ? json_decode($existing, true) : $existing;
                $list = is_array($decoded) && array_is_list($decoded) ? $decoded : [];

                $next = array_values(array_unique([...$list, ...$rows->pluck('feature_key')->all()]));

                DB::table('tenants')->where('id', $tenantId)->update(['enabled_features' => json_encode($next)]);
                DB::table('tenant_feature_overrides')->whereIn('id', $rows->pluck('id')->all())->delete();
                TenantCache::bumpFor((string) $tenantId, TenantEntitlementService::CACHE_NAMESPACE);
            });
    }
};
