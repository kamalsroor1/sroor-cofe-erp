<?php

declare(strict_types=1);

namespace App\Actions\Central\Platform;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\DTOs\Central\PlatformSettingsDTO;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\PlatformSetting;
use App\Services\Branding\PlatformBranding;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * BRND-2: saves platform settings into the CENTRAL `platform_settings` table through
 * PlatformBranding (rows locked, branding cache bumped for every tenant by the model
 * events) and audits PlatformSettingsUpdated — one central transaction, so the values and
 * their audit row commit or roll back together. Never touches a tenant `settings` table.
 *
 * Used by PUT /super-admin/platform-settings and the legacy POST /super-admin/settings.
 */
final class UpdatePlatformSettingsAction
{
    public function __construct(
        private readonly PlatformBranding $branding,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(PlatformSettingsDTO $dto, CentralUser $operator): PlatformBrandingDTO
    {
        $values = $dto->toArray();

        if ($values === []) {
            return $this->branding->get();
        }

        $connection = (string) (new PlatformSetting)->getConnectionName();

        return DB::connection($connection)->transaction(function () use ($values, $operator): PlatformBrandingDTO {
            $previous = $this->branding->get()->toArray();
            $branding = $this->branding->update($values, (int) $operator->getKey());

            $this->auditLogger->record(
                CentralAuditEvent::PlatformSettingsUpdated,
                [
                    'changed' => array_keys($values),
                    'values' => $values,
                    'previous' => array_intersect_key($previous, $values),
                ],
                actor: $operator,
            );

            return $branding;
        });
    }
}
