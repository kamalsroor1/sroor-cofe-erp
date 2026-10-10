<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\DTOs\Branding\UpdateLegacyPlatformSettingsDTO;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\Branding\PlatformBranding;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Saves the legacy super-admin platform settings into the CENTRAL `platform_settings`
 * table through PlatformBranding (one central transaction, rows locked, branding cache
 * bumped for every tenant) and audits the change. Never touches the tenant `settings` table.
 */
final class UpdatePlatformSettingsAction
{
    public function __construct(
        private readonly PlatformBranding $branding,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(UpdateLegacyPlatformSettingsDTO $dto, CentralUser $operator): PlatformBrandingDTO
    {
        $values = $dto->toArray();

        // One central transaction: the settings and their audit row commit or roll back together.
        return DB::connection((string) $operator->getConnectionName())->transaction(function () use ($values, $operator): PlatformBrandingDTO {
            $branding = $this->branding->update($values, (int) $operator->getKey());

            $this->auditLogger->record(
                CentralAuditEvent::PlatformSettingsUpdated,
                ['changed' => array_keys($values), 'values' => $values],
                actor: $operator,
            );

            return $branding;
        });
    }
}
