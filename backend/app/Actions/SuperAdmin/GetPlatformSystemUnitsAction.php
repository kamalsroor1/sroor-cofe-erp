<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Services\Platform\PlatformUnitsCatalog;

/**
 * The platform-wide unit catalog (`global_system_units`) tenants pick their units from.
 * Stored in the CENTRAL `platform_settings` table; falls back to the built-in catalog.
 * Reads go through PlatformUnitsCatalog (central cache key).
 */
final class GetPlatformSystemUnitsAction
{
    public const KEY = PlatformUnitsCatalog::KEY;

    /** Built-in catalog (unit names are data, not UI copy). */
    public const DEFAULT_UNITS = PlatformUnitsCatalog::DEFAULT_UNITS;

    public function __construct(private readonly PlatformUnitsCatalog $catalog) {}

    /**
     * @return list<string>
     */
    public function execute(): array
    {
        return $this->catalog->all();
    }
}
