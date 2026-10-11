<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Console\Commands\BackupTenantsCommand;
use App\Models\TenantBackup;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * OPS-9 (CTO Q4): prove a ledger backup can bring a tenant back BEFORE its database is
 * dropped.
 *
 *  1. the row belongs to this tenant and was verified at upload time;
 *  2. the archive on its disk still hashes to the ledger sha256 (re-downloaded now);
 *  3. it decrypts with BACKUP_ARCHIVE_PASSWORD and its manifest names this tenant
 *     (BackupTenantsCommand::readManifest reads the whole dump through the CRC check);
 *  4. restore drill (purge.require_restore_drill): `backup:restore-tenant <id> --backup=<row>`
 *     restores into a NEW scratch database, compares every table's row count with the
 *     manifest, and drops the scratch copy. The live database is never touched.
 *
 * Any failure throws a RuntimeException with a translated message. $runner runs the drill
 * command (default Artisan::call); tenants:purge passes its own call() so the drill shares
 * the operator's console (the --db-user password prompt).
 */
final class VerifyTenantBackupAction
{
    /**
     * @param  (Closure(string, array<string, mixed>): int)|null  $runner
     */
    public function execute(TenantBackup $backup, string $tenantId, bool $restoreDrill, ?string $dbUser = null, ?Closure $runner = null): void
    {
        if ($backup->tenant_id !== $tenantId) {
            throw new RuntimeException((string) __('console.purge.backup_wrong_tenant', ['backup' => $backup->getKey()]));
        }

        if ($backup->verified_at === null) {
            throw new RuntimeException((string) __('console.purge.backup_unverified', ['backup' => $backup->getKey()]));
        }

        if (! hash_equals($backup->sha256, BackupTenantsCommand::remoteSha256($backup->disk, $backup->path))) {
            throw new RuntimeException((string) __('console.purge.backup_checksum_mismatch', ['backup' => $backup->getKey()]));
        }

        $this->assertDecryptsForTenant($backup, $tenantId);

        if (! $restoreDrill) {
            return;
        }

        $parameters = ['subject' => $tenantId, '--backup' => (string) $backup->getKey()];
        if ($dbUser !== null && $dbUser !== '') {
            $parameters['--db-user'] = $dbUser;
        }

        $runner ??= static fn (string $command, array $arguments): int => Artisan::call($command, $arguments);

        if ($runner('backup:restore-tenant', $parameters) !== 0) {
            throw new RuntimeException((string) __('console.purge.restore_drill_failed', ['backup' => $backup->getKey()]));
        }
    }

    private function assertDecryptsForTenant(TenantBackup $backup, string $tenantId): void
    {
        $base = rtrim((string) config('backup.tenants.temporary_directory'), '/\\');
        File::ensureDirectoryExists($base, 0700);
        $work = $base.DIRECTORY_SEPARATOR.'purge-verify-'.Str::lower(Str::random(10));
        File::ensureDirectoryExists($work, 0700);

        try {
            $archive = $work.DIRECTORY_SEPARATOR.'archive.zip';
            $in = Storage::disk($backup->disk)->readStream($backup->path);
            if (! is_resource($in)) {
                throw new RuntimeException((string) __('console.backups.download_failed', ['disk' => $backup->disk]));
            }
            $out = fopen($archive, 'wb');
            if ($out === false) {
                fclose($in);
                throw new RuntimeException((string) __('console.backups.download_failed', ['disk' => $backup->disk]));
            }
            try {
                $copied = stream_copy_to_stream($in, $out);
            } finally {
                fclose($in);
                fclose($out);
            }
            if ($copied === false || ! hash_equals($backup->sha256, (string) hash_file('sha256', $archive))) {
                throw new RuntimeException((string) __('console.purge.backup_checksum_mismatch', ['backup' => $backup->getKey()]));
            }

            try {
                $manifest = BackupTenantsCommand::readManifest($archive, BackupTenantsCommand::archivePassword());
            } catch (Throwable $e) {
                throw new RuntimeException((string) __('console.purge.backup_undecryptable', ['backup' => $backup->getKey()]), 0, $e);
            }

            if (($manifest['subject'] ?? null) !== $tenantId || ($manifest['tenant_id'] ?? null) !== $tenantId) {
                throw new RuntimeException((string) __('console.purge.backup_wrong_tenant', ['backup' => $backup->getKey()]));
            }
        } finally {
            File::deleteDirectory($work);
        }
    }
}
