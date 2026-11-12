<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackupTenantsCommand;
use App\Models\TenantBackup;
use App\Support\Backup\BackupIntegrity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TenantTestCase;
use ZipArchive;

/**
 * BKP-01: a damaged archive is never restored and never reported as verified.
 *  - the manifest carries the dump sha256 and a content digest per table;
 *  - readManifest() re-hashes the dump while reading it;
 *  - the restore compares the extracted dump with those digests before importing;
 *  - --allow-unverified (no ledger row) still checks the manifest's own digests.
 */
final class BackupContentDigestTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-0000';

    private const PREFIX = 'tenant_zz_restore_';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('google');
        Notification::fake();
        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-digest-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
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

    public function test_the_manifest_carries_the_dump_sha256_and_table_digests(): void
    {
        $archive = $this->backupOneTenant();

        $manifest = BackupTenantsCommand::readManifest($archive, self::PASSWORD, true);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest['dump_sha256']);
        $this->assertSame(array_keys($manifest['tables']), array_keys($manifest['table_digests']));
    }

    public function test_a_healthy_archive_restores_and_reports_digests_verified(): void
    {
        $id = $this->backupOneTenantId();

        $this->artisan('backup:restore-tenant', ['subject' => $id])
            ->expectsOutputToContain((string) __('console.restore.verified', ['tables' => $this->tableCount($id), 'rows' => $this->rowCount($id)]))
            ->assertExitCode(0);
    }

    public function test_one_flipped_bit_is_rejected_by_read_manifest(): void
    {
        $archive = $this->backupOneTenant();
        $this->flipOneByte($archive);

        $this->expectException(RuntimeException::class);
        BackupTenantsCommand::readManifest($archive, self::PASSWORD, true);
    }

    public function test_a_flipped_bit_is_refused_by_the_restore_even_without_a_ledger_row(): void
    {
        $id = $this->backupOneTenantId();
        $row = TenantBackup::query()->forSubject($id)->latest('id')->firstOrFail();
        $stored = Storage::disk('google')->path($row->path);
        $this->flipOneByte($stored);
        TenantBackup::query()->delete();

        $this->artisan('backup:restore-tenant', [
            'subject' => $id,
            '--disk' => 'google',
            '--path' => $row->path,
            '--allow-unverified' => true,
        ])->assertExitCode(1);

        $this->assertSame([], File::glob(database_path(self::PREFIX.'*.sqlite')));
    }

    public function test_a_changed_dump_with_the_same_row_counts_is_refused(): void
    {
        $id = $this->backupOneTenantId();
        $row = TenantBackup::query()->forSubject($id)->latest('id')->firstOrFail();
        $stored = Storage::disk('google')->path($row->path);

        // Rebuild the archive with a different dump but the ORIGINAL manifest (same counts, old digests).
        $manifest = BackupTenantsCommand::readManifest($stored, self::PASSWORD, true);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($stored, ZipArchive::RDONLY));
        $zip->setPassword(self::PASSWORD);
        $dump = (string) $zip->getFromName($manifest['dump']);
        $zip->close();

        $tampered = $this->tmp.DIRECTORY_SEPARATOR.'t.zip';
        File::ensureDirectoryExists($this->tmp);
        $out = new ZipArchive;
        $this->assertTrue($out->open($tampered, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $out->addFromString($manifest['dump'], $dump.'x');
        $out->addFromString(BackupTenantsCommand::MANIFEST, json_encode($manifest, JSON_THROW_ON_ERROR));
        $out->setEncryptionName($manifest['dump'], ZipArchive::EM_AES_256, self::PASSWORD);
        $out->setEncryptionName(BackupTenantsCommand::MANIFEST, ZipArchive::EM_AES_256, self::PASSWORD);
        $out->close();
        Storage::disk('google')->put($row->path, (string) file_get_contents($tampered));
        TenantBackup::query()->delete();

        $this->artisan('backup:restore-tenant', [
            'subject' => $id,
            '--disk' => 'google',
            '--path' => $row->path,
            '--allow-unverified' => true,
        ])->assertExitCode(1);
    }

    public function test_table_digest_differences_names_changed_and_missing_tables(): void
    {
        $this->assertSame([], BackupIntegrity::differences(['a' => 'x', 'b' => 'y'], ['a' => 'x', 'b' => 'y']));
        $this->assertSame(['b', 'c'], BackupIntegrity::differences(['a' => 'x', 'b' => 'y'], ['a' => 'x', 'b' => 'z', 'c' => 'w']));
    }

    private function backupOneTenantId(): string
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);

        return $id;
    }

    /** Local path of the stored archive of a freshly backed-up tenant. */
    private function backupOneTenant(): string
    {
        $id = $this->backupOneTenantId();
        $row = TenantBackup::query()->forSubject($id)->latest('id')->firstOrFail();

        return Storage::disk('google')->path($row->path);
    }

    private function flipOneByte(string $file): void
    {
        $bytes = (string) file_get_contents($file);
        $position = intdiv(strlen($bytes), 2);
        $bytes[$position] = chr(ord($bytes[$position]) ^ 0x01);
        file_put_contents($file, $bytes);
    }

    private function tableCount(string $id): int
    {
        return count($this->manifestTables($id));
    }

    private function rowCount(string $id): int
    {
        return array_sum($this->manifestTables($id));
    }

    /** @return array<string, int> */
    private function manifestTables(string $id): array
    {
        $row = TenantBackup::query()->forSubject($id)->latest('id')->firstOrFail();

        /** @var array<string, int> */
        return BackupTenantsCommand::readManifest(Storage::disk('google')->path($row->path), self::PASSWORD)['tables'];
    }
}
