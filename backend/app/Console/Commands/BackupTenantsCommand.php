<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TenantBackupKind;
use App\Health\Notifications\BackupFailedNotification;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Support\Backup\BackupIntegrity;
use App\Support\Backup\BackupTelegramAlert;
use App\Support\Backup\MysqlDumpRowCounter;
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
use JsonException;
use RuntimeException;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\DbDumper;
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
 *      tables keep growing during the dump. OPS-23: the counter reads both the mysqldump
 *      and the mariadb-dump INSERT layouts, and the dump counts must fit a live bracket
 *      taken around the dump (dumpCountProblems()) or the subject fails: an empty,
 *      truncated or mis-read dump is never archived nor marked verified;
 *   3. AES-256 zip (BACKUP_ARCHIVE_PASSWORD) holding the dump + manifest.json, opened
 *      again locally to prove it decrypts;
 *   4. upload to every configured disk (Google Drive on the VPS), a row in the central
 *      `tenant_backups` ledger, then the uploaded copy is read back and its sha256
 *      compared before the row is marked verified;
 *   5. retention 7 daily / 4 weekly / 3 monthly, per subject and disk, only for the
 *      subjects that succeeded in this run.
 *
 * OPS-9: `--kind=final|pre_purge` (internal, used by the archive flow and tenants:purge for ONE
 * tenant, without the central DB) records the kind in the ledger and suffixes the file name
 * with it; retention never deletes those archives (CTO Q6). A purged tenant is never backed up.
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
        {--no-cleanup : Do not apply the retention policy after the run}
        {--kind=scheduled : Internal (OPS-9): scheduled, manual, or final / pre_purge for one --tenant with --skip-central}';

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

    private TenantBackupKind $kind = TenantBackupKind::Scheduled;

    /** The ledger has the OPS-9 `kind` column (false on a release that runs before its migration). */
    private bool $kindColumn = false;

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

        $refusal = $this->resolveKind($ledger);
        if ($refusal !== null) {
            return $this->refuse($refusal);
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
                if ($tenant->purged_at !== null || ($tenant->isPurged() && $this->kind !== TenantBackupKind::PrePurge)) {
                    // OPS-9: requested by id, but its database is gone (or going). Only a resumed
                    // tenants:purge may still take its pre_purge backup while the database exists.
                    $this->recordFailure($subject, new RuntimeException((string) __('console.backups.tenant_purged', ['tenant' => $subject])));

                    continue;
                }
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
    public static function readManifest(string $archive, ?string $password, bool $requireDigest = false): array
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
            try {
                $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException((string) __('console.backups.archive_corrupt'));
            }
            if (! is_array($manifest)
                || ($manifest['format'] ?? null) !== self::MANIFEST_FORMAT
                || ! is_string($manifest['dump'] ?? null)
                || ! is_array($manifest['tables'] ?? null)
                || $zip->locateName($manifest['dump']) === false) {
                throw new RuntimeException((string) __('console.backups.archive_unreadable'));
            }

            // BKP-01: PHP's zip stream does not prove a flipped bit (AES entries carry no CRC), so the
            // dump is hashed while it is read and compared with the manifest's own sha256 (and with
            // the zip CRC32 when the entry has one). A mismatch means the archive is damaged.
            $expected = $manifest['dump_sha256'] ?? null;
            if ($expected !== null && (! is_string($expected) || preg_match('/^[a-f0-9]{64}$/', $expected) !== 1)) {
                throw new RuntimeException((string) __('console.backups.archive_corrupt'));
            }
            if ($requireDigest && $expected === null) {
                throw new RuntimeException((string) __('console.backups.archive_digest_missing'));
            }

            $stream = $zip->getStream($manifest['dump']);
            if ($stream === false) {
                throw new RuntimeException((string) __('console.backups.archive_unreadable'));
            }
            $sha = hash_init('sha256');
            $crc = hash_init('crc32b');
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1048576);
                    if ($chunk === false) {
                        throw new RuntimeException((string) __('console.backups.archive_corrupt'));
                    }
                    hash_update($sha, $chunk);
                    hash_update($crc, $chunk);
                }
            } finally {
                fclose($stream);
            }

            $shaHex = hash_final($sha);
            $crcValue = hexdec(hash_final($crc));
            if ($expected !== null && ! hash_equals($expected, $shaHex)) {
                throw new RuntimeException((string) __('console.backups.archive_corrupt'));
            }
            $stat = $zip->statName($manifest['dump']);
            if ($stat !== false && (int) $stat['crc'] !== 0 && (int) $stat['crc'] !== (int) $crcValue) {
                throw new RuntimeException((string) __('console.backups.archive_corrupt'));
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

            // BKP-01: the dump's own sha256 and a content digest per table travel inside the manifest.
            $manifest['dump_sha256'] = BackupIntegrity::fileSha256($dumpFile);
            $manifest['table_digests'] = BackupIntegrity::tableDigests($dumpFile, (string) $manifest['driver']);

            $manifest = [
                'format' => self::MANIFEST_FORMAT,
                'subject' => $subject,
                'tenant_id' => $tenant === null ? null : $subject,
                'created_at' => $createdAt->toIso8601String(),
                'encrypted' => $password !== null,
            ] + $manifest;

            // Protected kinds carry their kind in the name: the retention pattern never matches them.
            $name = $subject.'-'.$createdAt->format(self::TIMESTAMP_FORMAT)
                .($this->kind->isProtected() ? '-'.$this->kind->value : '').'.zip';
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
        //
        // OPS-23: those dump counts are then checked against a live bracket taken around the dump
        // (dumpCountProblems()): an empty, truncated or mis-parsed dump fails the subject here,
        // before anything is archived, uploaded or marked verified.
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException((string) __('console.backups.unsupported_driver', ['driver' => $driver]));
        }

        $autoBefore = self::autoIncrementCounters($connection);
        $countsBefore = self::countRows($connection);

        if ($driver === 'sqlite') {
            $file = $dumpBase.'.sqlite';
            $connection->statement('VACUUM INTO ?', [$file]);
            $this->assertDumpWritten($file);
            $tables = self::countSqliteFileRows($file);
            $complete = true; // VACUUM INTO either writes a whole database or fails
        } else {
            $file = $dumpBase.'.sql';
            self::mysqlDumper($connectionName)->dumpToFile($file);
            $this->assertDumpWritten($file);
            $scan = MysqlDumpRowCounter::scanFile($file);
            $tables = $scan['tables'];
            $complete = $scan['complete'];
        }

        $countsAfter = self::countRows($connection);
        $autoAfter = self::autoIncrementCounters($connection);

        $this->assertDumpConsistent(self::dumpCountProblems($tables, $complete, $countsBefore, $countsAfter, $autoBefore, $autoAfter));

        return [$file, ['driver' => $driver, 'dump' => basename($file), 'tables_source' => self::TABLES_SOURCE_DUMP, 'tables' => $tables]];
    }

    /**
     * spatie dumper for a MySQL/MariaDB connection, with the options the manifest check depends
     * on pinned whatever config/database.php `dump` says (OPS-23):
     *  - --single-transaction: one consistent snapshot, the instant the live bracket brackets;
     *  - comments on: both clients then end the file with `-- Dump completed` (the completeness
     *    check; spatie skips comments by default);
     *  - --extended-insert: the layout the counter is tuned for (it reads one-row inserts too).
     * The INSERT layout itself (mysqldump 8 one line, mariadb-dump one row per line) is NOT forced:
     * the counter reads both.
     */
    public static function mysqlDumper(string $connectionName): DbDumper
    {
        $dumper = DbDumperFactory::createFromConnection($connectionName);
        if ($dumper instanceof MySql) {
            $dumper->useSingleTransaction()->doNotSkipComments()->useExtendedInserts();
        }

        return $dumper;
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
     * Row count per table of a mysqldump / mariadb-dump file (plain or gzip), read from the file
     * itself by MysqlDumpRowCounter (OPS-23: rows on the INSERT line or on the following lines).
     * Sorted by table name.
     *
     * @return array<string, int>
     */
    public static function countDumpRows(string $file): array
    {
        return MysqlDumpRowCounter::scanFile($file)['tables'];
    }

    /**
     * Top-level `( ... )` tuples of the VALUES part of one INSERT statement (quoted strings,
     * escapes and doubled quotes skipped, so parentheses or commas inside data never count).
     */
    public static function countInsertTuples(string $values): int
    {
        return MysqlDumpRowCounter::countTuples($values);
    }

    /**
     * OPS-23: AUTO_INCREMENT counter of every table that has an auto-increment column, read
     * live (SHOW CREATE TABLE on MySQL/MariaDB: information_schema.TABLES.AUTO_INCREMENT is a
     * cached statistic on MySQL 8; sqlite_sequence on sqlite). A table that never received a
     * row reports 1. Tables without such a column are absent.
     *
     * @return array<string, int>
     */
    public static function autoIncrementCounters(Connection $connection): array
    {
        $counters = [];

        switch ($connection->getDriverName()) {
            case 'mysql':
            case 'mariadb':
                $tables = $connection->select(
                    'select distinct c.TABLE_NAME as name from information_schema.COLUMNS c
                        join information_schema.TABLES t on t.TABLE_SCHEMA = c.TABLE_SCHEMA and t.TABLE_NAME = c.TABLE_NAME
                        where c.TABLE_SCHEMA = database() and t.TABLE_TYPE = ? and c.EXTRA like ?',
                    ['BASE TABLE', '%auto_increment%'],
                );
                foreach ($tables as $row) {
                    $name = (string) ((array) $row)['name'];
                    $create = (array) $connection->selectOne('show create table `'.str_replace('`', '``', $name).'`');
                    $sql = (string) (array_values($create)[1] ?? '');
                    // The table options come last: take the last AUTO_INCREMENT=n of the statement.
                    $counters[$name] = preg_match_all('/\bAUTO_INCREMENT=(\d+)/', $sql, $m) > 0
                        ? (int) end($m[1])
                        : 1;
                }
                break;
            case 'sqlite':
                $tables = $connection->select(
                    "select name from sqlite_master where type = 'table' and lower(sql) like '%autoincrement%'",
                );
                $sequence = [];
                if ($connection->select("select name from sqlite_master where type = 'table' and name = 'sqlite_sequence'") !== []) {
                    foreach ($connection->select('select name, seq from sqlite_sequence') as $row) {
                        $row = (array) $row;
                        $sequence[(string) $row['name']] = (int) $row['seq'];
                    }
                }
                foreach ($tables as $row) {
                    $name = (string) ((array) $row)['name'];
                    $counters[$name] = ($sequence[$name] ?? 0) + 1;
                }
                break;
            default:
                throw new RuntimeException((string) __('console.backups.unsupported_driver', ['driver' => $connection->getDriverName()]));
        }
        ksort($counters);

        return $counters;
    }

    /**
     * OPS-23: does the dump describe the live database? The dump's snapshot cannot be shared
     * with another session, so the live side is a BRACKET around the dump:
     *
     *   before: AUTO_INCREMENT counters, then row counts   (just before the dump starts)
     *   after:  row counts, then AUTO_INCREMENT counters   (just after it ends)
     *
     * For a table with an auto-increment column, I = after.ai - before.ai bounds the inserts made
     * while the dump ran, so the rows at the dump's snapshot lie in
     * [after.count - I, before.count + I]. A table nobody wrote to (I = 0, same count) must match
     * EXACTLY; a busy table can only drift by what was inserted. Deletes alone can only lower
     * after.count, which only widens the window. Tables without such a column (cache, pivots,
     * sessions) are only checked for presence. Also: the dump must be complete (trailer, no open
     * statement) and contain every table that existed before and after it.
     *
     * Assumption: rows are not inserted with an explicit id BELOW the counter while a backup runs
     * (true for the application; a manual import during a backup could false-fail it, which only
     * means "retry", never a wrong backup marked verified).
     *
     * @param  array<string, int>  $dump  rows per table read from the dump
     * @param  array<string, int>  $countsBefore
     * @param  array<string, int>  $countsAfter
     * @param  array<string, int>  $autoBefore
     * @param  array<string, int>  $autoAfter
     * @return list<string> one problem per table (`*` = the whole dump), empty = consistent
     */
    public static function dumpCountProblems(array $dump, bool $complete, array $countsBefore, array $countsAfter, array $autoBefore, array $autoAfter): array
    {
        $problems = [];
        if (array_sum($dump) === 0 && array_sum($countsBefore) + array_sum($countsAfter) > 0) {
            $problems[] = '*: '.__('console.backups.dump_zero_rows');
        }
        if (! $complete) {
            $problems[] = '*: '.__('console.backups.dump_incomplete');
        }

        foreach (array_keys(array_intersect_key($countsBefore, $countsAfter)) as $table) {
            if (! array_key_exists($table, $dump)) {
                $problems[] = $table.': '.__('console.backups.dump_table_missing');

                continue;
            }
            if (! isset($autoBefore[$table], $autoAfter[$table])) {
                continue;
            }
            $inserted = max(0, $autoAfter[$table] - $autoBefore[$table]);
            $low = max(0, $countsAfter[$table] - $inserted);
            $high = $countsBefore[$table] + $inserted;
            if ($dump[$table] < $low || $dump[$table] > $high) {
                $problems[] = $table.': '.__('console.backups.dump_rows_out_of_range', [
                    'rows' => $dump[$table],
                    'low' => $low,
                    'high' => $high,
                ]);
            }
        }

        return $problems;
    }

    /**
     * @param  list<string>  $problems
     */
    private function assertDumpConsistent(array $problems): void
    {
        if ($problems === []) {
            return;
        }

        Log::error('backup:tenants dump does not match the live database', ['problems' => $problems]);

        throw new RuntimeException((string) __('console.backups.dump_mismatch', [
            'count' => count($problems),
            'tables' => implode('; ', array_slice($problems, 0, 5)),
        ]));
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

        self::readManifest($archive, $password, true);
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
        ] + ($this->kindColumn ? ['kind' => $this->kind] : [])) : null;

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

            // OPS-9 (CTO Q6): final / pre_purge archives are never pruned, whatever their name.
            if ($ledger && $this->kindColumn) {
                $protected = TenantBackup::query()
                    ->where('disk', $disk)
                    ->whereIn('path', array_keys($dated))
                    ->kind(...TenantBackupKind::finalKinds())
                    ->pluck('path')
                    ->all();
                $dated = array_diff_key($dated, array_flip($protected));
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

    /**
     * --kind (OPS-9). A final / pre_purge backup is one tenant's last copy: exactly one
     * --tenant, no central DB, and a ledger that can record the kind (else it could be pruned
     * or not be found by the archive guard). Returns a translated refusal, or null.
     */
    private function resolveKind(bool $ledger): ?string
    {
        $this->kindColumn = $ledger && self::ledgerHasKind();

        $kind = TenantBackupKind::tryFrom(trim((string) ($this->option('kind') ?? TenantBackupKind::Scheduled->value)));
        if ($kind === null) {
            return (string) __('console.backups.kind_invalid', ['kinds' => implode(', ', TenantBackupKind::values())]);
        }
        $this->kind = $kind;

        if (! $kind->isProtected()) {
            return null;
        }

        if (count($this->requestedTenantIds()) !== 1 || ! $this->option('skip-central') || $this->option('only-central')) {
            return (string) __('console.backups.kind_needs_single_tenant', ['kind' => $kind->value]);
        }

        if (! $this->kindColumn) {
            return (string) __('console.backups.kind_needs_ledger', ['kind' => $kind->value]);
        }

        return null;
    }

    public static function ledgerHasKind(): bool
    {
        try {
            return Schema::connection((new TenantBackup)->getConnectionName())->hasColumn('tenant_backups', 'kind');
        } catch (Throwable) {
            return false;
        }
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

        BackupTelegramAlert::send('backup:tenants', $this->failures);

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
