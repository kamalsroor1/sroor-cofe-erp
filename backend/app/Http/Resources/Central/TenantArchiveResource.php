<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Enums\TenantBackupKind;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Support\Tenancy\PurgeEligibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OPS-9 archive / purge block of a tenant (super-admin tenant page, archive endpoints):
 *
 *   { state: queued|running|failed|null, state_label, requested_at, error_code, error_label,
 *     archived_at, final_backup: {id, created_at, disk, sha256_prefix}|null,
 *     purge_eligible_at, purged_at }
 *
 * Codes and timestamps only; never a path or a full checksum.
 *
 * @mixin Tenant
 */
final class TenantArchiveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;
        $state = $tenant->archive_state;
        $code = $tenant->archive_error_code;
        $eligibility = PurgeEligibility::forTenant($tenant, now());

        return [
            'state' => $state?->value,
            'state_label' => $state?->label(),
            'requested_at' => $tenant->archive_requested_at?->toIso8601String(),
            'error_code' => $code,
            'error_label' => $code === null ? null : (string) __('subscription.archive_error_codes.'.$code),
            'archived_at' => $tenant->archived_at?->toIso8601String(),
            'final_backup' => $this->finalBackup($tenant),
            'purge_eligible_at' => $eligibility->eligibleAt?->toIso8601String(),
            'purged_at' => $tenant->purged_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, created_at: string|null, disk: string, sha256_prefix: string}|null
     */
    private function finalBackup(Tenant $tenant): ?array
    {
        if ($tenant->final_backup_id === null) {
            return null;
        }

        $backup = TenantBackup::query()
            ->whereKey($tenant->final_backup_id)
            ->where('tenant_id', (string) $tenant->getKey())
            ->kind(...TenantBackupKind::finalKinds())
            ->first();

        if (! $backup instanceof TenantBackup) {
            return null;
        }

        return [
            'id' => (int) $backup->getKey(),
            'created_at' => $backup->created_at?->toIso8601String(),
            'disk' => $backup->disk,
            'sha256_prefix' => substr($backup->sha256, 0, 12),
        ];
    }
}
