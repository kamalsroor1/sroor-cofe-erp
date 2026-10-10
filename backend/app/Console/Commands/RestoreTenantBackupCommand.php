<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Health\Notifications\BackupFailedNotification;
use App\Models\TenantBackup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * OPS-5: restore one backup archive into a NEW, separate database and prove it is
 * complete (row count of every table == the manifest written at backup time).
 *
 * It never touches the live database: the target name always starts with
 * backup.tenants.restore_database_prefix and an existing database/file is refused.
 * Swapping a restored database in for a live tenant is a manual, documented step
 * (docs/07-operations/backup-restore.md §5), never done by this command.
 *
 *   php artisan backup:restore-tenant <tenant id|central> [--backup=<ledger id>]
 *       [--disk=google --path=sroor-backups/<id>/<file>.zip] [--database=<name>]
 *       [--keep-restored] [--allow-unverified] [--db-user=sroor_provisioner]
 *
 * The archive is downloaded to a private temp dir, its sha256 compared with the ledger,
 * decrypted with BACKUP_ARCHIVE_PASSWORD (D4: refused in production when empty),
 * imported (mysql client with a mode-600 option file, or a file copy for sqlite),
 * counted, and dropped again unless --keep-restored (monthly restore drill).
 *
 * Security audit (W2-B3 lane 3G):
 *  - production: MySQL restores run under a dedicated account (DB_RESTORE_USERNAME or
 *    --db-user), never the central application account;
 *  - an archive without a ledger row / sha256 is refused unless --allow-unverified;
 *  - outside local/testing an unencrypted archive (or an empty archive password) is refused;
 *  - the failure mail carries the exception class only; the message goes to the log.
 */
final class RestoreTenantBackupCommand extends Command
{
    private const RESTORE_CONNECTION = 'backup_restore';

    private const ADMIN_CONNECTION = 'backup_restore_admin';

    protected $signature = 'backup:restore-tenant
        {subject : Tenant id, or "central" for the central database}
        {--backup= : tenant_backups id (default: the latest verified backup of the subject)}
        {--disk= : Read the archive from this disk instead of the ledger (with --path)}
        {--path= : Path of the archive on --disk}
        {--database= : Name of the new database (must start with the restore prefix)}
        {--drop-after-verify : Kept for compatibility: dropping after a successful verification is the default}
        {--keep-restored : Keep the restored database after a successful verification (default: drop it)}
        {--allow-unverified : Accept an archive with no ledger row / sha256 to check (explicit operator decision)}
        {--db-user= : MySQL account that creates the new database (the password is asked, never passed)}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.restore.description'));
    }

    public function handle(): int
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $subject = trim((string) $this->argument('subject'));
        $password = BackupTenantsCommand::archivePassword();

        try {
            if (preg_match(BackupTenantsCommand::SUBJECT_PATTERN, $subject) !== 1) {
                throw new RuntimeException((string) __('console.backups.invalid_subject'));
            }
            if ($password === null && $this->laravel->environment('production')) {
                throw new RuntimeException((string) __('console.backups.password_missing'));
            }
            if ($password === null && ! $this->allowsUnencrypted()) {
                throw new RuntimeException((string) __('console.restore.unencrypted_refused'));
            }
            if ($this->laravel->environment('production') && ! $this->hasDedicatedRestoreUser()) {
                throw new RuntimeException((string) __('console.restore.restore_user_required'));
            }

            return $this->restore($subject, $password);
        } catch (Throwable $e) {
            $this->error((string) __('console.restore.failed', ['reason' => $e->getMessage()]));
            Log::error('backup:restore-tenant failed', ['subject' => $subject, 'exception' => $e::class, 'message' => $e->getMessage()]);
            // The mail carries the exception class only (BackupFailedNotification contract).
            $this->notify($subject, $e::class);

            return self::FAILURE;
        }
    }

    private function restore(string $subject, ?string $password): int
    {
        [$disk, $path, $expectedSha] = $this->resolveArchive($subject);
        $this->line((string) __('console.restore.source', ['disk' => $disk, 'path' => $path]));

        $work = $this->makeWorkDirectory();
        $created = null;

        try {
            $archive = $work.DIRECTORY_SEPARATOR.'archive.zip';
            $sha = $this->download($disk, $path, $archive);
            if ($expectedSha !== null && ! hash_equals($expectedSha, $sha)) {
                throw new RuntimeException((string) __('console.restore.checksum_mismatch'));
            }
            if ($expectedSha === null) {
                if (! $this->option('allow-unverified')) {
                    throw new RuntimeException((string) __('console.restore.unverified_refused'));
                }
                $this->warn((string) __('console.restore.no_checksum'));
            }
            if (! $this->allowsUnencrypted() && ! $this->isFullyEncrypted($archive)) {
                throw new RuntimeException((string) __('console.restore.unencrypted_refused'));
            }

            $manifest = BackupTenantsCommand::readManifest($archive, $password);
            if (($manifest['subject'] ?? null) !== $subject) {
                throw new RuntimeException((string) __('console.restore.subject_mismatch', [
                    'expected' => $subject,
                    'actual' => (string) ($manifest['subject'] ?? '?'),
                ]));
            }

            $dumpFile = $this->extractDump($archive, (string) $manifest['dump'], $password, $work);
            $database = $this->targetDatabaseName($subject);
            $driver = (string) ($manifest['driver'] ?? '');

            $created = match ($driver) {
                'sqlite' => $this->restoreSqlite($dumpFile, $database),
                'mysql', 'mariadb' => $this->restoreMysql($dumpFile, $database, $work),
                default => throw new RuntimeException((string) __('console.backups.unsupported_driver', ['driver' => $driver])),
            };
            $this->info((string) __('console.restore.restored', ['database' => $database]));

            /** @var array<string, int> $expected */
            $expected = array_map('intval', (array) $manifest['tables']);
            $actual = BackupTenantsCommand::countRows(DB::connection(self::RESTORE_CONNECTION));
            $mismatches = $this->compare($expected, $actual);

            if ($mismatches !== []) {
                $this->table(
                    [__('console.restore.col_table'), __('console.restore.col_expected'), __('console.restore.col_actual')],
                    $mismatches,
                );
                $this->disconnect();
                $created = null; // keep it for inspection

                throw new RuntimeException((string) __('console.restore.count_mismatch', [
                    'count' => count($mismatches),
                    'database' => $database,
                ]));
            }

            $this->info((string) __('console.restore.verified', [
                'tables' => count($actual),
                'rows' => array_sum($actual),
            ]));

            if (! $this->option('keep-restored')) {
                $this->drop($created);
                $created = null;
                $this->info((string) __('console.restore.dropped', ['database' => $database]));
            } else {
                $created = null; // the operator asked for a restored copy: keep it
                $this->line((string) __('console.restore.kept', ['database' => $database]));
            }

            return self::SUCCESS;
        } finally {
            $this->disconnect();
            if ($created !== null) {
                // Import failed half-way: remove only what this run created.
                try {
                    $this->drop($created);
                } catch (Throwable $e) {
                    Log::error('backup:restore-tenant could not drop a partial restore', ['message' => $e->getMessage()]);
                }
            }
            File::deleteDirectory($work);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string|null} disk, path, expected sha256
     */
    private function resolveArchive(string $subject): array
    {
        $disk = $this->option('disk');
        $path = $this->option('path');
        if (is_string($disk) && $disk !== '' && is_string($path) && $path !== '') {
            if (! is_array(config("filesystems.disks.{$disk}"))) {
                throw new RuntimeException((string) __('console.backups.unknown_disk', ['disk' => $disk]));
            }
            $row = BackupTenantsCommand::ledgerAvailable()
                ? TenantBackup::query()->where('disk', $disk)->where('path', $path)->latest('id')->first()
                : null;

            return [$disk, $path, $row?->sha256];
        }

        if (! BackupTenantsCommand::ledgerAvailable()) {
            throw new RuntimeException((string) __('console.backups.ledger_missing'));
        }

        $id = $this->option('backup');
        $query = TenantBackup::query()->forSubject($subject);
        $row = is_string($id) && $id !== ''
            ? $query->whereKey((int) $id)->first()
            : $query->verified()->latest('created_at')->latest('id')->first();

        if ($row === null) {
            throw new RuntimeException((string) __('console.restore.no_backup', ['subject' => $subject]));
        }

        return [$row->disk, $row->path, $row->sha256];
    }

    private function download(string $disk, string $path, string $target): string
    {
        $in = Storage::disk($disk)->readStream($path);
        if (! is_resource($in)) {
            throw new RuntimeException((string) __('console.backups.download_failed', ['disk' => $disk]));
        }
        $out = fopen($target, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException((string) __('console.backups.download_failed', ['disk' => $disk]));
        }

        try {
            if (stream_copy_to_stream($in, $out) === false) {
                throw new RuntimeException((string) __('console.backups.download_failed', ['disk' => $disk]));
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        return (string) hash_file('sha256', $target);
    }

    private function extractDump(string $archive, string $entry, ?string $password, string $work): string
    {
        if (preg_match('/^database\.(sql|sqlite)$/', $entry) !== 1) {
            throw new RuntimeException((string) __('console.backups.archive_unreadable'));
        }

        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException((string) __('console.backups.archive_unreadable'));
        }

        try {
            if ($password !== null) {
                $zip->setPassword($password);
            }
            if (! $zip->extractTo($work, [$entry])) {
                throw new RuntimeException((string) __('console.backups.archive_unreadable'));
            }
        } finally {
            $zip->close();
        }

        return $work.DIRECTORY_SEPARATOR.$entry;
    }

    private function targetDatabaseName(string $subject): string
    {
        $prefix = (string) config('backup.tenants.restore_database_prefix');
        $requested = $this->option('database');
        $name = is_string($requested) && $requested !== ''
            ? $requested
            // Technical uniqueness suffix of a scratch database name, not a business date:
            // an explicit UTC instant (no tenant is initialized here, so TenantClock does not apply).
            : $prefix.str_replace('-', '_', $subject).'_'.CarbonImmutable::now('UTC')->format('YmdHis');

        if ($prefix === '' || ! str_starts_with($name, $prefix) || preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1) {
            throw new RuntimeException((string) __('console.restore.invalid_database', ['prefix' => $prefix]));
        }

        return $name;
    }

    /**
     * @return array{driver: string, database: string}
     */
    private function restoreSqlite(string $dumpFile, string $database): array
    {
        $file = database_path($database.'.sqlite');
        if (file_exists($file)) {
            throw new RuntimeException((string) __('console.restore.database_exists', ['database' => $database]));
        }
        if (! copy($dumpFile, $file)) {
            throw new RuntimeException((string) __('console.restore.import_failed'));
        }

        config(['database.connections.'.self::RESTORE_CONNECTION => [
            'driver' => 'sqlite',
            'database' => $file,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        return ['driver' => 'sqlite', 'database' => $file];
    }

    /**
     * @return array{driver: string, database: string}
     */
    private function restoreMysql(string $dumpFile, string $database, string $work): array
    {
        $base = (array) config('database.connections.'.BackupTenantsCommand::centralBackupConnection());
        // --db-user + hidden prompt wins (config is cached on the VPS, so shell env vars
        // would be ignored); then backup.tenants.restore_*; then the central account.
        $username = config('backup.tenants.restore_username');
        $password = config('backup.tenants.restore_password');
        $promptUser = $this->option('db-user');
        if (is_string($promptUser) && $promptUser !== '') {
            $username = $promptUser;
            $password = (string) $this->secret((string) __('console.restore.ask_password', ['user' => $promptUser]));
        }
        if (is_string($username) && $username !== '') {
            $base['username'] = $username;
            $base['password'] = is_string($password) ? $password : '';
        } elseif ($this->laravel->environment('production')) {
            // Never fall back to the central application account in production.
            throw new RuntimeException((string) __('console.restore.restore_user_required'));
        }
        unset($base['dump'], $base['url']);

        config(['database.connections.'.self::ADMIN_CONNECTION => ['database' => ''] + $base]);
        $admin = DB::connection(self::ADMIN_CONNECTION);

        $exists = $admin->select('select SCHEMA_NAME from information_schema.SCHEMATA where SCHEMA_NAME = ?', [$database]);
        if ($exists !== []) {
            throw new RuntimeException((string) __('console.restore.database_exists', ['database' => $database]));
        }

        // $database matched ^[A-Za-z0-9_]+$ (targetDatabaseName), so quoting is safe.
        $charset = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($base['charset'] ?? 'utf8mb4'));
        $collation = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($base['collation'] ?? 'utf8mb4_unicode_ci'));
        $admin->statement("create database `{$database}` character set {$charset} collate {$collation}");
        $created = ['driver' => 'mysql', 'database' => $database];

        $optionFile = $work.DIRECTORY_SEPARATOR.'restore.cnf';
        File::put($optionFile, $this->mysqlOptionFile($base));
        @chmod($optionFile, 0600);

        try {
            $binary = rtrim((string) config('backup.tenants.mysql_binary_path', ''), '/\\');
            $process = new Process([
                ($binary === '' ? '' : $binary.DIRECTORY_SEPARATOR).'mysql',
                '--defaults-extra-file='.$optionFile,
                $database,
            ]);
            $process->setTimeout((float) (int) config('database.connections.'.BackupTenantsCommand::centralBackupConnection().'.dump.timeout', 3600));
            $input = fopen($dumpFile, 'rb');
            if ($input === false) {
                throw new RuntimeException((string) __('console.restore.import_failed'));
            }
            $process->setInput($input);
            $process->run();
            if (is_resource($input)) {
                fclose($input);
            }
            if (! $process->isSuccessful()) {
                Log::error('backup:restore-tenant mysql import failed', ['exit' => $process->getExitCode(), 'error' => Str::limit($process->getErrorOutput(), 500)]);

                throw new RuntimeException((string) __('console.restore.import_failed'));
            }
        } catch (Throwable $e) {
            $this->drop($created);

            throw $e;
        } finally {
            File::delete($optionFile);
        }

        config(['database.connections.'.self::RESTORE_CONNECTION => ['database' => $database] + $base]);

        return $created;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function mysqlOptionFile(array $connection): string
    {
        $quote = static fn (mixed $value): string => '"'.addcslashes((string) $value, '\\"').'"';

        $lines = ['[client]', 'user = '.$quote($connection['username'] ?? ''), 'password = '.$quote($connection['password'] ?? '')];
        if (($connection['unix_socket'] ?? '') !== '') {
            $lines[] = 'socket = '.$quote($connection['unix_socket']);
        } else {
            $lines[] = 'host = '.$quote($connection['host'] ?? '127.0.0.1');
            $lines[] = 'port = '.(int) ($connection['port'] ?? 3306);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, int>  $expected
     * @param  array<string, int>  $actual
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function compare(array $expected, array $actual): array
    {
        $rows = [];
        foreach (array_unique([...array_keys($expected), ...array_keys($actual)]) as $table) {
            $want = $expected[$table] ?? null;
            $got = $actual[$table] ?? null;
            if ($want !== $got) {
                $rows[] = [(string) $table, $want === null ? '-' : (string) $want, $got === null ? '-' : (string) $got];
            }
        }

        return $rows;
    }

    /**
     * @param  array{driver: string, database: string}  $created
     */
    private function drop(array $created): void
    {
        $this->disconnect();

        if ($created['driver'] === 'sqlite') {
            File::delete($created['database']);

            return;
        }

        $prefix = (string) config('backup.tenants.restore_database_prefix');
        if ($prefix === '' || ! str_starts_with($created['database'], $prefix) || preg_match('/^[A-Za-z0-9_]{1,64}$/', $created['database']) !== 1) {
            throw new RuntimeException((string) __('console.restore.invalid_database', ['prefix' => $prefix]));
        }
        DB::connection(self::ADMIN_CONNECTION)->statement("drop database `{$created['database']}`");
    }

    private function disconnect(): void
    {
        DB::purge(self::RESTORE_CONNECTION);
    }

    /** Unencrypted archives are a local/testing convenience only. */
    private function allowsUnencrypted(): bool
    {
        return $this->laravel->environment(['local', 'testing']);
    }

    /** Every entry of the archive is encrypted (ZipArchive::EM_NONE = plaintext). */
    private function isFullyEncrypted(string $archive): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException((string) __('console.backups.archive_unreadable'));
        }

        try {
            if ($zip->numFiles === 0) {
                return false;
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false || $stat['encryption_method'] === ZipArchive::EM_NONE) {
                    return false;
                }
            }

            return true;
        } finally {
            $zip->close();
        }
    }

    /** DB_RESTORE_USERNAME (backup.tenants.restore_username) or --db-user; never the central account. */
    private function hasDedicatedRestoreUser(): bool
    {
        $promptUser = $this->option('db-user');
        $configured = config('backup.tenants.restore_username');

        return (is_string($promptUser) && $promptUser !== '') || (is_string($configured) && $configured !== '');
    }

    private function makeWorkDirectory(): string
    {
        $base = rtrim((string) config('backup.tenants.temporary_directory'), '/\\');
        File::ensureDirectoryExists($base, 0700);
        $dir = $base.DIRECTORY_SEPARATOR.'restore-'.Str::lower(Str::random(10));
        File::ensureDirectoryExists($dir, 0700);

        return $dir;
    }

    private function notify(string $subject, string $reason): void
    {
        $to = config('backup.tenants.notify_mail');
        if (! is_string($to) || trim($to) === '') {
            return;
        }

        try {
            Notification::route('mail', trim($to))
                ->notifyNow(new BackupFailedNotification('backup:restore-tenant', [$subject => $reason]));
        } catch (Throwable $e) {
            Log::error('backup:restore-tenant could not send the failure mail', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }
}
