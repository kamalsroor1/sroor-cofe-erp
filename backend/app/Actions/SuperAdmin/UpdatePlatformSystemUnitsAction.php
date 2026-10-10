<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\PlatformSetting;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Saves the platform unit catalog into the CENTRAL `platform_settings` table (pinned
 * connection), never into a tenant `settings` table.
 */
final class UpdatePlatformSystemUnitsAction
{
    public function __construct(private readonly CentralAuditLogger $auditLogger) {}

    /**
     * @param  list<string>  $units
     * @return list<string>
     */
    public function execute(array $units, CentralUser $operator): array
    {
        $setting = new PlatformSetting;

        DB::connection($setting->getConnectionName())->transaction(function () use ($units, $operator): void {
            $row = PlatformSetting::query()->where('key', GetPlatformSystemUnitsAction::KEY)->lockForUpdate()->first()
                ?? new PlatformSetting(['key' => GetPlatformSystemUnitsAction::KEY]);

            $row->fill([
                'value' => implode(',', $units),
                'type' => 'string',
                'updated_by' => (int) $operator->getKey(),
            ])->save();

            // Joins the central transaction: the row exists exactly when the change does.
            $this->auditLogger->record(
                CentralAuditEvent::PlatformUnitsUpdated,
                ['units' => $units],
                actor: $operator,
            );
        });

        return $units;
    }
}
