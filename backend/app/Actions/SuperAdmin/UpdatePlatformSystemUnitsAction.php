<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\PlatformSetting;
use App\Services\CentralAuditLogger;
use App\Services\Platform\PlatformUnitsCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Saves the platform unit catalog into the CENTRAL `platform_settings` table (pinned
 * connection), never into a tenant `settings` table. The row and its audit entry commit
 * or roll back together.
 */
final class UpdatePlatformSystemUnitsAction
{
    public function __construct(
        private readonly PlatformUnitsCatalog $catalog,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<string>  $units
     * @return list<string>
     */
    public function execute(array $units, CentralUser $operator): array
    {
        $connection = (string) (new PlatformSetting)->getConnectionName();

        return DB::connection($connection)->transaction(function () use ($units, $operator): array {
            $saved = $this->catalog->save($units, (int) $operator->getKey());

            $this->auditLogger->record(
                CentralAuditEvent::PlatformUnitsUpdated,
                ['units' => $saved],
                actor: $operator,
            );

            return $saved;
        });
    }
}
