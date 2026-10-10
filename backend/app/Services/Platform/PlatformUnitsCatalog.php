<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use App\Services\Branding\PlatformBranding;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The platform-wide unit catalog (`global_system_units`) tenants pick their units from,
 * stored as a CSV row of the CENTRAL `platform_settings` table.
 *
 * Read through an explicit central cache key (TenantCache::centralKey), so a tenant
 * request and a central request share one entry. The key embeds the central version of
 * the platform_settings namespace, which PlatformSetting bumps on every save/delete:
 * any write (this class, a migration, a direct model write) is visible on the next read.
 *
 * Never throws on read: without the central table (fresh install) or the cache it
 * serves the built-in catalog and caches nothing.
 */
final class PlatformUnitsCatalog
{
    public const KEY = 'global_system_units';

    public const CACHE_NAMESPACE = 'platform_units';

    /** Built-in catalog (unit names are data, not UI copy). */
    public const DEFAULT_UNITS = 'قطعة,علبة,كرتونة,كجم,جرام,شيكارة,طرد,دستة,باكت,حبة,لتر,مل,متر,طقم,زوج,باليتة';

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $units = self::parse($this->stored() ?? '');

        return $units !== [] ? $units : self::parse(self::DEFAULT_UNITS);
    }

    /**
     * Upsert the catalog. Locks the row: the caller owns the central transaction (and the
     * audit row that commits with it).
     *
     * @param  list<string>  $units
     * @return list<string> the saved list
     */
    public function save(array $units, ?int $updatedBy): array
    {
        $units = self::parse(implode(',', $units));

        $row = PlatformSetting::query()->where('key', self::KEY)->lockForUpdate()->first()
            ?? new PlatformSetting(['key' => self::KEY]);

        // PlatformSetting's saved event bumps the central version this cache key embeds.
        $row->fill([
            'value' => implode(',', $units),
            'type' => 'string',
            'updated_by' => $updatedBy,
        ])->save();

        return $units;
    }

    /** Central cache key of the stored catalog (version-stamped). */
    public static function cacheKey(): string
    {
        return TenantCache::centralKey(
            self::CACHE_NAMESPACE.':'.TenantCache::centralVersion(PlatformBranding::CACHE_NAMESPACE),
        );
    }

    /**
     * Trimmed, non-blank, de-duplicated, order kept.
     *
     * @return list<string>
     */
    public static function parse(string $csv): array
    {
        $units = [];
        foreach (explode(',', $csv) as $unit) {
            $unit = trim($unit);

            if ($unit !== '' && ! in_array($unit, $units, true)) {
                $units[] = $unit;
            }
        }

        return $units;
    }

    private function stored(): ?string
    {
        try {
            $cacheKey = self::cacheKey();
            $cached = Cache::get($cacheKey);

            if (is_string($cached)) {
                return $cached;
            }
        } catch (Throwable) {
            $cacheKey = null;
        }

        try {
            $value = PlatformSetting::query()->where('key', self::KEY)->value('value');
        } catch (Throwable) {
            // Central table not migrated yet: built-in catalog, nothing cached.
            return null;
        }

        $value = is_string($value) ? $value : null;

        if ($cacheKey !== null) {
            try {
                // A missing row is cached as '' (parses to no units => built-in catalog).
                Cache::put($cacheKey, $value ?? '', max(60, (int) config('branding.cache_ttl_seconds', 3600)));
            } catch (Throwable) {
                // A cache write failure only costs a query on the next read.
            }
        }

        return $value;
    }
}
