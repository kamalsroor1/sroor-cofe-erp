<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Health\Notifications\BackupFailedNotification;
use App\Models\Tenant;
use App\Models\TenantBackup;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Throwable;
use ZipArchive;

/**
 * OPS-5: encrypted backup of the central DB and of every tenant DB, one archive each.
 *
 * Per subject (`central` or a tenant id), from central context:
 *   1. dump: `mysqldump --single-transaction` through spatie/db-dumper (options in
 *      config/database.php `dump`, read-only `sroor_backup` account), or `VACUUM INTO`
 *      for sqlite (dev / tests); tenant dumps run inside `$tenant->run()` and tenancy
 *      is always ended afterwards;
 *   2. row count of every table READ FROM THE DUMP (manifest `tables`, `tables_source`
 *      = dump), the numbers backup:restore-tenant verifies: exact even while the live
 *      tables keep growing during the dump;
 *   3. AES-256 zip (BACKUP_ARCHIVE_PASSWORD) holding the dump + manifest.json, opened
 *      again locally to prove it decrypts;
 *   4. upload to every configured disk (Google Drive on the VPS), a row in the central
 *      `tenant_backups` ledger, then the uploaded copy is read back and its sha256
 *      compared before the row is marked verified;
 *   5. retention 7 daily / 4 weekly / 3 monthly, per subject and disk, only for the
 *      subjects that succeeded in this run.
 *
 * One failing subject never stops the others. Any failure = exit 1, a log entry and a
 * mail to backup.tenants.notify_mail (subject + exception class only; the details are in
 * the log). CTO decision D4: no archive password outside local/testing = refuse to run
 * (exit 1), nothing is dumped.
 */
final class BackupTenantsCommand extends Command
{
    public const MANIFEST = 'manifest.json';

    public const MANIFEST_FORMAT = 1;

    /** Manifest `tables_source`: the row counts were read from the dump itself (not the live DB). */
    public const TABLES_SOURCE_DUMP = 'dump';

    /** Tenant ids are alpha_dash slugs or UUIDs; anything else never reaches a path. */
    public const SUBJECT_PATTERN = '/^[A-Za-z0-9_-]{1,100}$/';

    public const TIMESTAMP_FORMAT = 'Y-m-d-H-i-s';

    protected $signature = 'backup:tenants
        {--tenant=* : Only these tenant ids (any status, archived included)}
        {--skip-central : Do not back up the central database}
        {--only-central : Back up the central database only}
        {--disk=* : Target disks (default: backup.tenants.disks)}
        {--no-cleanup : Do not apply the retention policy after the run}';

    /** Environments where a missing BACKUP_ARCHIVE_PASSWORD only warns (archives unencrypted). */
    public const UNENCRYPTED_ENVIRONMENTS = ['local', 'testing'];

    /**
     * subject => short reason for the failure mail: the exception class only (or a fixed,
     * translated refusal). Exception messages can carry paths, hosts or SQL, so they go to
     * the log and the console, never into a mail.
     *
     * @var array<string, string>
     */
    private array $failures = [];

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.backups.description'));
    }

    public function handle(): int
    {
        $this->failures = [];
        $this->endTenancy();

        $password = self::archivePassword();
        if ($password === null) {
            // D4, tightened by the security audit (W2 lane 3I): unencrypted archives are only
            // tolerated on a developer machine or in the test suite. Staging, `prod`, a typo in
            // APP_ENV... all refuse, like production.
            if (! $this->laravel->environment(self::UNENCRYPTED_ENVIRONMENTS)) {
                return $this->refuse((string) __('console.backups.password_missing'));
            }
            $this->warn((string) __('console.backups.password_missing_dev'));
        }

        if ($this->option('only-central') && $this->option('skip-central')) {
            return $this->refuse((string) __('console.backups.conflicting_options'));
        }

        $disks = $this->targetDisks();
        if ($disks === []) {
            return $this->refuse((string) __('console.backups.no_disks'));
        }
        foreach ($disks as $disk) {
            if (! is_array(config("filesystems.disks.{$disk}"))) {
                return $this->refuse((string) __('console.backups.unknown_disk', ['disk' => $disk]));
            }
        }

        $ledger = self::ledgerAvailable();
        if (! $ledger) {
            $this->warn((string) __('console.backups.ledger_missing'));
        }

        $succeeded = [];
        $total = 0;

        $withCentral = (bool) $this->option('only-central')
            || (! $this->option('skip-central') && (bool) config('backup.tenants.include_central', true) && $this->requestedTenantIds() === []);
        if ($withCentral) {
            $total++;
            if ($this->backupSubject(TenantBackup::CENTRAL, null, $disks, $password, $ledger)) {
                $succeeded[] = TenantBackup::CENTRAL;
            }
        }

        if (! $this->option('only-central')) {
            $seen = [];
            foreach ($this->tenants() as $tenant) {
                $subject = (string) $tenant->getTenantKey();
                $seen[] = $subject;
                $total++;
                if ($this->backupSubject($subject, $tenant, $disks, $password, $ledger)) {
                    $succeeded[] = $subject;
                }
            }
            foreach (array_diff($this->requestedTenantIds(), $seen) as $missing) {
                $total++;
                $this->recordFailure($missing, new RuntimeException((string) __('console.backups.tenant_not_found', ['tenant' => $missing])));
            }
        }

        if (! $this->option('no-cleanup')) {
            foreach ($succeeded as $subject) {
                foreach ($disks as $disk) {
                    $this->applyRetention($subject, $disk, $ledger);
                }
            }
        }

        $this->info((string) __('console.backups.finished', [
            'ok' => count($succeeded),
            'total' => $total,
            'failed' => count($this->failures),
        ]));

        if ($this->failures !== []) {
            $this->notifyFailures();

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------------------------
    // Shared with backup:restore-tenant and the health checks
    // ---------------------------------------------------------------------------------

    public static function archivePassword(): ?string
    {
        $password = config('backup.backup.password');

        return is_string($password) && $password !== '' ? $password : null;
    }

    /** Connection dumped as "the central DB". */
    public static function centralBackupConnection(): string
    {
        $configured = config('backup.tenants.central_connection');

        return is_string($configured) && $configured !== ''
            ? $configured
            : (string) config('tenancy.database.central_connection');
    }

    public static function ledgerAvailable(): bool
    {
        try {
            return Schema::connection((new TenantBackup)->getConnectionName())->hasTable('tenant_backups');
        } catch (Throwable) {
            return false;
        }
    }

    public static function subjectDirectory(string $subject): string
    {
        $prefix = trim((string) config('backup.tenants.path_prefix', ''), '/');

        return $prefix === '' ? $subject : $prefix.'/'.$subject;
    }

    /**
     * Row count of every base table of a connection, sorted by table name.
     *
     * @return array<string, int>
     */
    public static function countRows(Connection $connection): array
    {
        $driver = $connection->getDriverName();

        $rows = match ($driver) {
            'mysql', 'mariadb' => $connection->select(
                'select TABLE_NAME as name from information_schema.TABLES where TABLE_SCHEMA = database() and TABLE_TYPE = ? order by TABLE_NAME',
                ['BASE TABLE'],
            ),
            'sqlite' => $connection->select(
                "select name from sqlite_master where type = 'table' and name not like 'sqlite\\_%' escape '\\' order by name",
            ),
            default => throw new RuntimeException((string) __('console.backups.unsupported_driver', ['driver' => $driver])),
        };

        $counts = [];
        foreach ($rows as $row) {
            $name = (string) ((array) $row)['name'];
            $counts[$name] = (int) $connection->table($name)->count();
        }
        ksort($counts);

        return $counts;
    }

    /**
     * Opens an archive, checks every entry is AES-256 encrypted (when a password is
     * given), reads the whole dump entry through decryption and returns the manifest.
     *
     * @return array<string, mixed>
     */
    public static function readManifest(string $archive, ?string $password): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException((string) __('console.backups.archive_unreadable'));
        }

        try {
            if ($password !== null) {
                $zip->setPassword($password);
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if ($stat === false || $stat['encryption_method'] !== ZipArchive::EM_AES_256) {
                        throw new RuntimeException((string) __('console.backups.archive_not_encrypted'));
                    }
                }
            }

            $raw = $zip->getFromName(self::MANIFEST);
            if (! is_string($raw) || $raw === '') {
                throw new RuntimeException((string) __('console.backups.archive_unreadable'));
            }
            $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($manifest)
                || ($manifest['format'] ?? null) !== self::MANIFEST_FORMAT
                || ! is_string($manifest['dump'] ?? null)
                || ! is_array($manifest['tables'] ?? null)
                || $zip->locateName($manifest['dump']) === false) {
                throw new RuntimeException((string) __('console.backups.archive_unreadable'));
            }

            // Reading the dump to the end makes zip check its CRC through decryption.
            $stream = $zip->getStream($manifest['dump']);
            if ($stream === false) {
                throw new RuntimeException((string) __('console.backups.archive_unreadable'));
            }
            try {
                while (! feof($stream)) {
                    if (fread($stream, 1048576) === false) {
                        throw new RuntimeException((string) __('console.backups.archive_unreadable'));
                    }
                }
            } finally {
                fclose($stream);
            }

            /** @var array<string, mixed> $manifest */
            return $manifest;
        } finally {
            $zip->close();
        }
    }

    /** sha256 of a file on a disk, streamed (the archive is never loaded in memory). */
    public static function remoteSha256(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException((string) __('console.backups.download_failed', ['disk' => $disk]));
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Retention 7 daily / 4 weekly / 3 monthly (consecutive periods, like spatie's
     * DefaultStrategy): everything from the last `keep_all_backups_for_days` days, then the
     * newest archive of each ISO week for `keep_weekly_backups_for_weeks`, then the newest
     * of each month for `keep_monthly_backups_for_months`; older archives go. The newest
     * archive is never deleted.
     *
     * @param  array<string, CarbonInterface>  $backups  path => creation time
     * @param  array<string, int>  $retention
     * @return list<string> paths to delete
     */
    public static function selectForDeletion(array $backups, CarbonInterface $now, array $retention): array
    {
        if ($backups === []) {
            return [];
        }

        uasort($backups, static fn (CarbonInterface $a, CarbonInterface $b): int => $b->getTimestamp() <=> $a->getTimestamp());

        $now = CarbonImmutable::instance($now);
        $dailyEnd = $now->subDays(max(0, (int) ($retention['keep_all_backups_for_days'] ?? 7)));
        $weeklyEnd = $dailyEnd->subWeeks(max(0, (int) ($retention['keep_weekly_backups_for_weeks'] ?? 4)));
        $monthlyEnd = $weeklyEnd->subMonthsNoOverflow(max(0, (int) ($retention['keep_monthly_backups_for_months'] ?? 3)));

        $newest = array_key_first($backups);
        $kept = [];
        $delete = [];
        foreach ($backups as $path => $createdAt) {
            if ($path === $newest || $createdAt->greaterThanOrEqualTo($dailyEnd)) {
                continue;
            }
            if ($createdAt->greaterThanOrEqualTo($weeklyEnd)) {
                $bucket = 'w'.$createdAt->format('o-W');
            } elseif ($createdAt->greaterThanOrEqualTo($monthlyEnd)) {
                $bucket = 'm'.$createdAt->format('Y-m');
            } else {
                $delete[] = (string) $path;

                continue;
            }
            // Iterating newest first: the first archive of a bucket is the one kept.
            if (isset($kept[$bucket])) {
                $delete[] = (string) $path;
            } else {
                $kept[$bucket] = true;
            }
        }

        return $delete;
    }

    // ---------------------------------------------------------------------------------
    // One subject
    // ---------------------------------------------------------------------------------

    /**
     * @param  list<string>  $disks
     */
    private function backupSubject(string $subject, ?Tenant $tenant, array $disks, ?string $password, bool $ledger): bool
    {
        if (preg_match(self::SUBJECT_PATTERN, $subject) !== 1) {
            $this->recordFailure($subject, new RuntimeException((string) __('console.backups.invalid_subject')));

            return false;
        }

        $this->line((string) __('console.backups.subject_started', ['subject' => $subject]));
        $work = $this->makeWorkDirectory($subject);

        try {
            $createdAt = CarbonImmutable::now();
            $dumpBase = $work.DIRECTORY_SEPARATOR.'database';

            [$dumpFile, $manifest] = $tenant === null
                ? $this->dumpConnection(self::centralBackupConnection(), $dumpBase)
                : $this->dumpTenant($tenant, $dumpBase);

            $manifest = [
                'format' => self::MANIFEST_FORMAT,
                'subject' => $subject,
                'tenant_id' => $tenant === null ? null : $subject,
                'created_at' => $createdAt->toIso8601String(),
                'encrypted' => $password !== null,
            ] + $manifest;

            $name = $subject.'-'.$createdAt->format(self::TIMESTAMP_FORMAT).'.zip';
            $archive = $work.DIRECTORY_SEPARATOR.$name;
            $this->writeArchive($archive, $dumpFile, $manifest, $password);

            $sha256 = (string) hash_file('sha256', $archive);
            $size = (int) filesize($archive);
            $path = self::subjectDirectory($subject).'/'.$name;

            foreach ($disks as $disk) {
                $this->upload($disk, $path, $archive, $sha256, $size, $subject, $ledger);
            }

            $this->info((string) __('console.backups.subject_done', [
                'subject' => $subject,
                'tables' => count($manifest['tables']),
                'size' => $size,
                'disks' => implode(', ', $disks),
            ]));

            return true;
        } catch (Throwable $e) {
            $this->recordFailure($subject, $e);

            return false;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * @return array{0: string, 1: array{driver: string, dump: string, tables: array<string, int>}}
     */
    private function dumpTenant(Tenant $tenant, string $dumpBase): array
    {
        try {
            /** @var array{0: string, 1: array{driver: string, dump: string, tables: array<string, int>}} */
            return $tenant->run(fn (): array => $this->dumpConnection('tenant', $dumpBase));
        } finally {
            $this->endTenancy();
        }
    }

    /**
     * @return array{0: string, 1: array{driver: string, dump: string, tables: array<string, int>}}
     */
    private function dumpConnection(string $connectionName, string $dumpBase): array
    {
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();

        // The manifest describes the DUMP itself, never the live database: counting the live
        // tables on another connection before `mysqldump --single-transaction` takes its
        // snapshot made busy tables (sales, audit, tokens) mismatch on restore (code review,
        // W2 lane 3I). sqlite: the VACUUM INTO copy is opened and counted; MySQL/MariaDB: the
        // CREATE TABLE / INSERT rows of the .sql file are counted (countDumpRows()).
        switch ($driver) {
            case 'sqlite':
                $file = $dumpBase.'.sqlite';
                $connection->statement('VACUUM INTO ?', [$file]);
                $this->assertDumpWritten($file);
                $tables = self::countSqliteFileRows($file);
                break;
            case 'mysql':
            case 'mariadb':
                $file = $dumpBase.'.sql';
                DbDumperFactory::createFromConnection($connectionName)->dumpToFile($file);
                $this->assertDumpWritten($file);
                $tables = self::countDumpRows($file);
                break;
            default:
                throw new RuntimeException((string) __('console.backups.unsupported_driver', ['driver' => $driver]));
        }

        return [$file, ['driver' => $driver, 'dump' => basename($file), 'tables_source' => self::TABLES_SOURCE_DUMP, 'tables' => $tables]];
    }

    private function assertDumpWritten(string $file): void
    {
        if (! is_file($file) || (int) filesize($file) === 0) {
            throw new RuntimeException((string) __('console.backups.dump_empty'));
        }
    }

    /**
     * Row count of every table of a sqlite database FILE (the VACUUM INTO copy), through a
     * throw-away connection that is always purged.
     *
     * @return array<string, int>
     */
    public static function countSqliteFileRows(string $file): array
    {
        $name = 'backup_manifest_'.bin2hex(random_bytes(4));
        config(['database.connections.'.$name => [
            'driver' => 'sqlite',
            'database' => $file,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        try {
            return self::countRows(DB::connection($name));
        } finally {
            DB::purge($name);
            config(['database.connections.'.$name => null]);
        }
    }

    /**
     * Row count per table of a mysqldump file, read from the file itself: every
     * `CREATE TABLE` starts a table at 0, every `INSERT INTO` line adds its top-level value
     * tuples (extended inserts included). Streams the file line by line. Sorted by name.
     *
     * @return array<string, int>
     */
    public static function countDumpRows(string $file): array
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException((string) __('console.backups.dump_empty'));
        }

        $counts = [];
        try {
            while (($line = fgets($handle)) !== false) {
                if (str_starts_with($line, 'CREATE TABLE `')) {
                    if (preg_match('/^CREATE TABLE `((?:[^`]|``)+)`/', $line, $m) === 1) {
                        $counts[str_replace('``', '`', $m[1])] ??= 0;
                    }

                    continue;
                }

                if (! str_starts_with($line, 'INSERT INTO `')
                    || preg_match('/^INSERT INTO `((?:[^`]|``)+)`/', $line, $m) !== 1) {
                    continue;
                }

                $values = strpos($line, ' VALUES ');
                if ($values === false) {
                    continue;
                }

                $table = str_replace('``', '`', $m[1]);
                $counts[$table] = ($counts[$table] ?? 0) + self::countInsertTuples(substr($line, $values + 8));
            }
        } finally {
            fclose($handle);
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Top-level `( ... )` tuples of the VALUES part of one mysqldump INSERT statement.
     * Quoted strings are skipped with mysqldump's escaping (backslash escapes, '' doubling),
     * so parentheses or commas inside data never count.
     */
    public static function countInsertTuples(string $values): int
    {
        $count = 0;
        $depth = 0;
        $inQuote = false;
        $length = strlen($values);
        $i = 0;

        while ($i < $length) {
            $i += strcspn($values, $inQuote ? "\\'" : "'()", $i);
            if ($i >= $length) {
                break;
            }

            $char = $values[$i];
            if ($inQuote) {
                if ($char === '\\') {
                    $i += 2;

                    continue;
                }
                $inQuote = false;
            } elseif ($char === "'") {
                $inQuote = true;
            } elseif ($char === '(') {
                if ($depth === 0) {
                    $count++;
                }
                $depth++;
            } else {
                $depth = max(0, $depth - 1);
            }
            $i++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeArchive(string $archive, string $dumpFile, array $manifest, ?string $password): void
    {
        $entry = (string) $manifest['dump'];

        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException((string) __('console.backups.archive_failed'));
        }

        $ok = $zip->addFile($dumpFile, $entry)
            && $zip->addFromString(self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        if ($ok && $password !== null) {
            $ok = $zip->setEncryptionName($entry, ZipArchive::EM_AES_256, $password)
                && $zip->setEncryptionName(self::MANIFEST, ZipArchive::EM_AES_256, $password);
        }

        if (! $ok || ! $zip->close()) {
            throw new RuntimeException((string) __('console.backups.archive_failed'));
        }

        self::readManifest($archive, $password);
    }

    private function upload(string $disk, string $path, string $archive, string $sha256, int $size, string $subject, bool $ledger): void
    {
        $stream = fopen($archive, 'rb');
        if ($stream === false) {
            throw new RuntimeException((string) __('console.backups.archive_failed'));
        }

        try {
            $written = Storage::disk($disk)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written === false) {
            throw new RuntimeException((string) __('console.backups.upload_failed', ['disk' => $disk]));
        }

        $row = $ledger ? TenantBackup::query()->create([
            'tenant_id' => $subject === TenantBackup::CENTRAL ? null : $subject,
            'disk' => $disk,
            'path' => $path,
            'sha256' => $sha256,
            'size_bytes' => $size,
        ]) : null;

        if (! (bool) config('backup.tenants.verify_after_upload', true)) {
            return;
        }

        if (! hash_equals($sha256, self::remoteSha256($disk, $path))) {
            throw new RuntimeException((string) __('console.backups.verify_failed', ['disk' => $disk]));
        }

        $row?->forceFill(['verified_at' => now()])->save();
    }

    private function applyRetention(string $subject, string $disk, bool $ledger): void
    {
        try {
            $pattern = '/^'.preg_quote($subject, '/').'-(\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2})\.zip$/';
            $dated = [];
            foreach (Storage::disk($disk)->files(self::subjectDirectory($subject)) as $file) {
                if (preg_match($pattern, basename($file), $match) !== 1) {
                    continue;
                }
                $createdAt = CarbonImmutable::createFromFormat(self::TIMESTAMP_FORMAT, $match[1]);
                if ($createdAt instanceof CarbonImmutable) {
                    $dated[$file] = $createdAt;
                }
            }

            $retention = (array) config('backup.tenants.retention', []);
            foreach (self::selectForDeletion($dated, CarbonImmutable::now(), $retention) as $file) {
                if (! Storage::disk($disk)->delete($file)) {
                    throw new RuntimeException((string) __('console.backups.cleanup_failed', ['disk' => $disk]));
                }
                if ($ledger) {
                    TenantBackup::query()->where('disk', $disk)->where('path', $file)->delete();
                }
                $this->line((string) __('console.backups.pruned', ['subject' => $subject, 'file' => basename($file)]));
            }
        } catch (Throwable $e) {
            $this->recordFailure($subject.' ('.__('console.backups.retention').')', $e);
        }
    }

    // ---------------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------------

    /**
     * Tenants to back up: the requested ids (any status), or every provisioned tenant
     * (OPS-2: a pending / running / failed one has no database) whose status is not in
     * backup.tenants.skip_statuses (archived tenants keep their final backup).
     *
     * @return LazyCollection<int, Tenant>
     */
    private function tenants(): LazyCollection
    {
        $ids = $this->requestedTenantIds();
        $skip = array_values(array_filter((array) config('backup.tenants.skip_statuses', []), 'is_string'));

        return Tenant::query()
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($ids === [], fn ($query) => $query->provisioned())
            ->when($ids === [] && $skip !== [], fn ($query) => $query->whereNotIn('status', $skip))
            ->orderBy('id')
            ->cursor();
    }

    /** @return list<string> */
    private function requestedTenantIds(): array
    {
        $ids = (array) $this->option('tenant');

        return array_values(array_unique(array_filter(array_map(
            static fn ($id): string => trim((string) $id),
            $ids,
        ), static fn (string $id): bool => $id !== '')));
    }

    /** @return list<string> */
    private function targetDisks(): array
    {
        $requested = (array) $this->option('disk');
        $disks = $requested !== [] ? $requested : (array) config('backup.tenants.disks', []);

        return array_values(array_unique(array_filter(array_map(
            static fn ($disk): string => trim((string) $disk),
            $disks,
        ), static fn (string $disk): bool => $disk !== '')));
    }

    private function makeWorkDirectory(string $subject): string
    {
        $base = rtrim((string) config('backup.tenants.temporary_directory'), '/\\');
        File::ensureDirectoryExists($base, 0700);

        $dir = $base.DIRECTORY_SEPARATOR.$subject.'-'.Str::lower(Str::random(10));
        File::ensureDirectoryExists($dir, 0700);

        return $dir;
    }

    private function endTenancy(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }

    private function recordFailure(string $subject, Throwable $e): void
    {
        $reason = $e->getMessage() !== '' ? $e->getMessage() : $e::class;
        $this->failures[$subject] = $e::class;

        Log::error('backup:tenants failed for a subject', [
            'subject' => $subject,
            'exception' => $e::class,
            'message' => $reason,
        ]);

        $this->error((string) __('console.backups.subject_failed', ['subject' => $subject, 'reason' => $reason]));
    }

    private function refuse(string $message): int
    {
        $this->failures = ['*' => $message];
        $this->error($message);
        Log::critical('backup:tenants refused to run', ['reason' => $message]);
        $this->notifyFailures();

        return self::FAILURE;
    }

    private function notifyFailures(): void
    {
        Log::critical('backup:tenants finished with failures', ['failures' => $this->failures]);

        $to = config('backup.tenants.notify_mail');
        if (! is_string($to) || trim($to) === '') {
            return;
        }

        try {
            Notification::route('mail', trim($to))
                ->notifyNow(new BackupFailedNotification('backup:tenants', $this->failures));
        } catch (Throwable $e) {
            Log::error('backup:tenants could not send the failure mail', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }
}
