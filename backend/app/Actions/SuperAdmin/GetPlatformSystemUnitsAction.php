<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Models\PlatformSetting;
use Throwable;

/**
 * The platform-wide unit catalog (`global_system_units`) tenants pick their units from.
 * Stored in the CENTRAL `platform_settings` table; falls back to the built-in catalog.
 */
final class GetPlatformSystemUnitsAction
{
    public const KEY = 'global_system_units';

    /** Built-in catalog (unit names are data, not UI copy). */
    public const DEFAULT_UNITS = 'قطعة,علبة,كرتونة,كجم,جرام,شيكارة,طرد,دستة,باكت,حبة,لتر,مل,متر,طقم,زوج,باليتة';

    /**
     * @return list<string>
     */
    public function execute(): array
    {
        try {
            $stored = PlatformSetting::query()->where('key', self::KEY)->value('value');
        } catch (Throwable) {
            // Central table not migrated yet: serve the built-in catalog.
            $stored = null;
        }

        $units = self::parse(is_string($stored) ? $stored : '');

        return $units !== [] ? $units : self::parse(self::DEFAULT_UNITS);
    }

    /**
     * @return list<string>
     */
    private static function parse(string $csv): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $csv)),
            static fn (string $unit): bool => $unit !== '',
        ));
    }
}
