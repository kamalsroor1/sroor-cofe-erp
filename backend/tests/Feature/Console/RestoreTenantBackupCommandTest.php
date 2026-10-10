<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackupTenantsCommand;
use App\Models\TenantBackup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TenantTestCase;
use ZipArchive;

/**
 * OPS-5 restore drill: `backup:restore-tenant` restores an encrypted archive into a NEW
 * database (never over an existing one), checks the ledger sha256 and the subject, and
 * proves the restore is complete by comparing the row count of every table with the
 * manifest written at backup time.
 */
final class RestoreTenantBackupCommandTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-0000';

    private const PREFIX = 'tenant_zz_restore_';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-restore-cmd-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => 'ops@example.test',
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.restore_database_prefix' => self::PREFIX,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('backup_restore');
        foreach (File::glob(database_path(self::PREFIX.'*.sqlite')) as $file) {
            File::delete($file);
        }
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    public function test_the_latest_verified_backup_is_restored_into_a_new_database_with_identical_row_counts(): void
    {
        $tenant = $this->createTenant();
        $this->createTenantUser($tenant);
        $this->createTenantUser($tenant);
        $id = (string) $tenant->getTenantKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);
        $users = $this->inTenant($tenant, fn (): int => User::query()->count());
        $database = self::PREFIX.'drill_keep';

        $this->artisan('backup:restore-tenant', ['subject' => $id, '--database' => $database, '--keep-restored' => true])
            ->expectsOutputToContain((string) __('console.restore.restored', ['database' => $database]))
            ->assertExitCode(0);

        $file = database_path($database.'.sqlite');
        $this->assertFileExists($file, 'Without --drop-after-verify the restored copy is kept.');
        $restored = new PDO('sqlite:'.$file);
        $this->assertSame($users, (int) $restored->query('select count(*) from users')->fetchColumn());
        $restored = null;

        $live = $this->inTenant($tenant, fn (): int => User::query()->count());
        $this->assertSame($users, $live, 'The live tenant database is never touched.');
        Notification::assertNothingSent();
    }

    public function test_the_restore_drill_drops_the_copy_after_a_successful_verification(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);

        $this->artisan('backup:restore-tenant', ['subject' => $id, '--drop-after-verify' => true])->assertExitCode(0);

        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')));
    }

    public function test_an_archive_that_does_not_match_the_ledger_checksum_is_refused(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);
        $row = TenantBackup::query()->where('tenant_id', $id)->sole();
        Storage::disk('google')->put($row->path, Storage::disk('google')->get($row->path).'tampered');

        $this->artisan('backup:restore-tenant', ['subject' => $id])
            ->expectsOutputToContain((string) __('console.restore.checksum_mismatch'))
            ->assertExitCode(1);

        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')));
    }

    public function test_a_wrong_archive_password_is_refused(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);
        config(['backup.backup.password' => 'another-password-that-is-long-enough-0000']);

        $this->artisan('backup:restore-tenant', ['subject' => $id])->assertExitCode(1);

        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')));
    }

    public function test_an_archive_of_another_subject_is_refused(): void
    {
        $first = $this->createTenant();
        $second = $this->createTenant();
        $this->artisan('backup:tenants', ['--tenant' => [(string) $first->getTenantKey()]])->assertExitCode(0);
        $row = TenantBackup::query()->where('tenant_id', $first->getTenantKey())->sole();

        $this->artisan('backup:restore-tenant', [
            'subject' => (string) $second->getTenantKey(),
            '--disk' => 'google',
            '--path' => $row->path,
        ])->assertExitCode(1);

        $this->artisan('backup:restore-tenant', [
            'subject' => (string) $second->getTenantKey(),
            '--backup' => (string) $row->id,
        ])->assertExitCode(1);

        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')));
    }

    public function test_the_target_must_be_a_new_database_with_the_restore_prefix(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);

        // A live tenant database name is refused outright.
        $this->artisan('backup:restore-tenant', ['subject' => $id, '--database' => $this->tenantDatabaseName($tenant)])
            ->expectsOutputToContain((string) __('console.restore.invalid_database', ['prefix' => self::PREFIX]))
            ->assertExitCode(1);

        // An existing restore target is never overwritten.
        $existing = database_path(self::PREFIX.'already_there.sqlite');
        File::put($existing, 'keep me');
        $this->artisan('backup:restore-tenant', ['subject' => $id, '--database' => self::PREFIX.'already_there'])
            ->assertExitCode(1);
        $this->assertSame('keep me', File::get($existing));
    }

    public function test_a_row_count_mismatch_fails_and_keeps_the_copy_for_inspection(): void
    {
        // Hand-made archive (not in the ledger): the manifest claims 3 rows, the dump has 2.
        File::ensureDirectoryExists($this->tmp);
        $dump = $this->tmp.DIRECTORY_SEPARATOR.'database.sqlite';
        $pdo = new PDO('sqlite:'.$dump);
        $pdo->exec('create table probe (id integer primary key)');
        $pdo->exec('insert into probe (id) values (1), (2)');
        $pdo = null;

        $archive = $this->tmp.DIRECTORY_SEPARATOR.'probe.zip';
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($dump, 'database.sqlite');
        $zip->addFromString(BackupTenantsCommand::MANIFEST, (string) json_encode([
            'format' => BackupTenantsCommand::MANIFEST_FORMAT,
            'subject' => 'qaprobe',
            'tenant_id' => 'qaprobe',
            'driver' => 'sqlite',
            'dump' => 'database.sqlite',
            'tables' => ['probe' => 3],
        ]));
        $zip->setEncryptionName('database.sqlite', ZipArchive::EM_AES_256, self::PASSWORD);
        $zip->setEncryptionName(BackupTenantsCommand::MANIFEST, ZipArchive::EM_AES_256, self::PASSWORD);
        $zip->close();
        Storage::disk('google')->put('sroor-backups/qaprobe/qaprobe-2026-10-01-01-30-00.zip', (string) file_get_contents($archive));
        $database = self::PREFIX.'mismatch';

        $this->artisan('backup:restore-tenant', [
            'subject' => 'qaprobe',
            '--disk' => 'google',
            '--path' => 'sroor-backups/qaprobe/qaprobe-2026-10-01-01-30-00.zip',
            '--database' => $database,
            '--drop-after-verify' => true,
            '--allow-unverified' => true,
        ])
            ->expectsOutputToContain((string) __('console.restore.no_checksum'))
            ->expectsOutputToContain((string) __('console.restore.count_mismatch', ['count' => 1, 'database' => $database]))
            ->assertExitCode(1);

        $this->assertFileExists(database_path($database.'.sqlite'), 'A mismatching restore is kept for inspection, even with --drop-after-verify.');
    }

    public function test_d4_refuses_to_restore_in_production_without_an_archive_password(): void
    {
        config(['backup.backup.password' => null]);
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->artisan('backup:restore-tenant', ['subject' => 'central'])
            ->expectsOutputToContain((string) __('console.backups.password_missing'))
            ->assertExitCode(1);
    }
}
