<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackupTenantsCommand;
use App\Health\Notifications\BackupFailedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Tests\TenantTestCase;
use ZipArchive;

/**
 * W2-B3 lane 3G (security audit) on `backup:restore-tenant`:
 *  - an archive with no ledger row / sha256 is refused unless --allow-unverified;
 *  - dropping the restored copy after a successful verification is the default
 *    (--keep-restored opts out);
 *  - outside local/testing an unencrypted archive or an empty archive password is refused;
 *  - production requires a dedicated restore DB account (no central-account fallback);
 *  - the failure mail carries the exception class only, never the message.
 */
final class RestoreTenantBackupHardeningTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-0000';

    private const PREFIX = 'tenant_zz_restore_';

    private const PROBE_PATH = 'sroor-backups/qaprobe/qaprobe-2026-10-01-01-30-00.zip';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-restore-hard-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => 'ops@example.test',
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.restore_database_prefix' => self::PREFIX,
            'backup.tenants.restore_username' => null,
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

    public function test_an_archive_outside_the_ledger_is_refused_without_allow_unverified(): void
    {
        $this->putProbeArchive(encrypted: true);

        $this->artisan('backup:restore-tenant', $this->probeArgs())
            ->expectsOutputToContain((string) __('console.restore.unverified_refused'))
            ->assertExitCode(1);

        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')));
    }

    public function test_allow_unverified_restores_and_drops_the_copy_by_default(): void
    {
        $this->putProbeArchive(encrypted: true);

        $this->artisan('backup:restore-tenant', $this->probeArgs(['--allow-unverified' => true]))
            ->expectsOutputToContain((string) __('console.restore.no_checksum'))
            ->expectsOutputToContain((string) __('console.restore.dropped', ['database' => self::PREFIX.'probe']))
            ->assertExitCode(0);

        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')), 'dropping after verify is the default');
    }

    public function test_keep_restored_keeps_the_verified_copy(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);
        $database = self::PREFIX.'kept';

        $this->artisan('backup:restore-tenant', ['subject' => $id, '--database' => $database, '--keep-restored' => true])
            ->expectsOutputToContain((string) __('console.restore.kept', ['database' => $database]))
            ->assertExitCode(0);

        $this->assertFileExists(database_path($database.'.sqlite'));
    }

    public function test_a_ledger_backup_is_dropped_after_verification_without_any_flag(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);

        $this->artisan('backup:restore-tenant', ['subject' => $id])->assertExitCode(0);

        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')));
    }

    public function test_an_unencrypted_archive_is_refused_outside_local_and_testing(): void
    {
        $this->putProbeArchive(encrypted: false);
        $this->app->detectEnvironment(static fn (): string => 'staging');

        $this->artisan('backup:restore-tenant', $this->probeArgs(['--allow-unverified' => true]))
            ->expectsOutputToContain((string) __('console.restore.unencrypted_refused'))
            ->assertExitCode(1);

        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')));
    }

    public function test_an_empty_archive_password_is_refused_outside_local_and_testing(): void
    {
        config(['backup.backup.password' => null]);
        $this->app->detectEnvironment(static fn (): string => 'staging');

        $this->artisan('backup:restore-tenant', ['subject' => 'central'])
            ->expectsOutputToContain((string) __('console.restore.unencrypted_refused'))
            ->assertExitCode(1);
    }

    public function test_production_requires_a_dedicated_restore_account(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->artisan('backup:restore-tenant', ['subject' => 'central'])
            ->expectsOutputToContain((string) __('console.restore.restore_user_required'))
            ->assertExitCode(1);

        config(['backup.tenants.restore_username' => 'sroor_restore']);

        $this->artisan('backup:restore-tenant', ['subject' => 'central'])
            ->doesntExpectOutputToContain((string) __('console.restore.restore_user_required'))
            ->assertExitCode(1);
    }

    public function test_the_failure_mail_carries_the_exception_class_only(): void
    {
        $this->putProbeArchive(encrypted: true);

        $this->artisan('backup:restore-tenant', $this->probeArgs())->assertExitCode(1);

        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            static fn (BackupFailedNotification $mail): bool => $mail->command === 'backup:restore-tenant'
                && $mail->failures === ['qaprobe' => RuntimeException::class],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function probeArgs(array $extra = []): array
    {
        return array_merge([
            'subject' => 'qaprobe',
            '--disk' => 'google',
            '--path' => self::PROBE_PATH,
            '--database' => self::PREFIX.'probe',
        ], $extra);
    }

    /** Hand-made archive that is NOT in the ledger, with a manifest matching its dump. */
    private function putProbeArchive(bool $encrypted): void
    {
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
            'tables' => ['probe' => 2],
        ]));
        if ($encrypted) {
            $zip->setEncryptionName('database.sqlite', ZipArchive::EM_AES_256, self::PASSWORD);
            $zip->setEncryptionName(BackupTenantsCommand::MANIFEST, ZipArchive::EM_AES_256, self::PASSWORD);
        }
        $zip->close();

        Storage::disk('google')->put(self::PROBE_PATH, (string) file_get_contents($archive));
    }
}
