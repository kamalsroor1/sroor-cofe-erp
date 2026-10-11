<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Console\Commands\BackupTenantsCommand;
use App\Enums\TenantBackupKind;
use App\Models\Tenant;
use App\Models\TenantBackup;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * OPS-9: take one tenant's backup through the single backup engine (OPS-5) and PROVE it.
 *
 *   backup:tenants --tenant=<id> --skip-central --no-cleanup --kind=<kind>
 *
 * Proof = for EVERY configured backup disk, a ledger row of this tenant and kind, created by
 * this run (id above the ledger's max id before the run) and verified (the uploaded copy
 * was read back and its sha256 matched). Anything less throws: the caller (archive / purge)
 * must not go on. Never call it inside a database transaction (dump + upload = external I/O).
 */
final class TakeTenantBackupAction
{
    /**
     * @return TenantBackup the proven row on the first configured disk
     */
    public function execute(Tenant $tenant, TenantBackupKind $kind): TenantBackup
    {
        if (! BackupTenantsCommand::ledgerAvailable() || ! BackupTenantsCommand::ledgerHasKind()) {
            throw new RuntimeException((string) __('console.backups.kind_needs_ledger', ['kind' => $kind->value]));
        }

        $disks = $this->configuredDisks();
        if ($disks === []) {
            throw new RuntimeException((string) __('console.backups.no_disks'));
        }

        $tenantId = (string) $tenant->getTenantKey();
        $before = (int) TenantBackup::query()->max('id');

        $exitCode = Artisan::call('backup:tenants', [
            '--tenant' => [$tenantId],
            '--skip-central' => true,
            '--no-cleanup' => true,
            '--kind' => $kind->value,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException((string) __('console.archive.backup_failed', ['tenant' => $tenantId]));
        }

        $proven = [];
        foreach ($disks as $disk) {
            $row = TenantBackup::query()
                ->where('id', '>', $before)
                ->where('tenant_id', $tenantId)
                ->where('disk', $disk)
                ->kind($kind)
                ->verified()
                ->latest('id')
                ->first();

            if (! $row instanceof TenantBackup) {
                throw new RuntimeException((string) __('console.archive.backup_not_verified', ['tenant' => $tenantId, 'disk' => $disk]));
            }

            $proven[] = $row;
        }

        return $proven[0];
    }

    /**
     * The disks backup:tenants writes to by default (same rule as the command).
     *
     * @return list<string>
     */
    private function configuredDisks(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($disk): string => trim((string) $disk),
            (array) config('backup.tenants.disks', []),
        ), static fn (string $disk): bool => $disk !== '')));
    }
}
