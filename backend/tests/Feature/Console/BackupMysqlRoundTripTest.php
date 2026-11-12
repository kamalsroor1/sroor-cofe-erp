<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackupTenantsCommand;
use App\Models\Tenant;
use App\Models\TenantBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TenantTestCase;

/**
 * OPS-23 on a real MySQL server with the real dump client found on PATH (`mysqldump`: the Oracle
 * client on the CI runner, often the MariaDB client on a developer machine or a VPS; both layouts
 * must work):
 *  - a tenant with data (hostile text, several extended INSERT statements) backs up with a
 *    manifest equal to the live counts, and backup:restore-tenant verifies every table;
 *  - a dump that lost its rows, or was cut short, fails the backup: nothing is archived,
 *    uploaded or marked verified.
 *
 * `mysql-only`: needs a MySQL server and the mysqldump / mysql clients (phpunit.mysql.xml).
 */
#[Group('mysql')]
#[Group('mysql-only')]
final class BackupMysqlRoundTripTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-0023';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-ops23-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($this->tmp);

        Storage::fake('google');
        Notification::fake();
        Http::fake();

        $template = $this->templateConnection();
        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.temporary_directory' => $this->tmp.DIRECTORY_SEPARATOR.'work',
            'backup.tenants.verify_after_upload' => true,
            'backup.tenants.notify_telegram_chat_id' => null,
            'backup.tenants.notify_mail' => null,
            // Small network buffer: every big table is split over several INSERT statements.
            "database.connections.{$template}.dump.add_extra_option" => '--no-tablespaces --hex-blob --net-buffer-length=4096',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    public function test_a_tenant_with_data_backs_up_and_restores_with_every_table_matching(): void
    {
        $tenant = $this->tenantWithData();
        $live = $this->inTenant($tenant, static fn (): array => BackupTenantsCommand::countRows(DB::connection()));

        $this->artisan('backup:tenants', ['--tenant' => [(string) $tenant->getTenantKey()]])->assertExitCode(0);

        $row = TenantBackup::query()->where('tenant_id', $tenant->getTenantKey())->sole();
        $this->assertNotNull($row->verified_at);
        $manifest = BackupTenantsCommand::readManifest(Storage::disk('google')->path($row->path), self::PASSWORD);

        $this->assertSame($live, $manifest['tables']);
        $this->assertGreaterThanOrEqual(1200, $manifest['tables']['customers']);

        $this->artisan('backup:restore-tenant', ['subject' => (string) $tenant->getTenantKey()])
            ->expectsOutputToContain((string) __('console.restore.verified', ['tables' => count($live), 'rows' => array_sum($live)]))
            ->assertExitCode(0);
    }

    public function test_a_dump_that_lost_its_rows_fails_and_is_not_verified(): void
    {
        $tenant = $this->tenantWithData();
        // A "complete" dump (trailer present) whose INSERT statements are gone: the all-zero
        // manifest the MariaDB client produced before OPS-23.
        $this->fakeDumpClient("| awk '/^INSERT INTO/{skip=1} !skip{print} skip && /;\$/{skip=0}'");

        $this->artisan('backup:tenants', ['--tenant' => [(string) $tenant->getTenantKey()]])->assertExitCode(1);

        $this->assertNothingRecorded($tenant);
    }

    public function test_a_truncated_dump_fails_and_is_not_verified(): void
    {
        $tenant = $this->tenantWithData();
        $this->fakeDumpClient('| head -n 40');

        $this->artisan('backup:tenants', ['--tenant' => [(string) $tenant->getTenantKey()]])->assertExitCode(1);

        $this->assertNothingRecorded($tenant);
    }

    private function tenantWithData(): Tenant
    {
        $this->requireClient('mysqldump');
        $this->requireClient('mysql');

        $tenant = $this->createTenant();
        $this->createTenantUser($tenant);

        $this->inTenant($tenant, static function (): void {
            $now = now();
            $hostile = [
                "عميل (اختبار), 'O'Neil'; \"x\"",
                "line1\nline2\n(3,'z');",
                'back\\slash\\',
                'x),(y',
                '',
            ];
            foreach (array_chunk(range(1, 1200), 300) as $chunk) {
                DB::table('customers')->insert(array_map(static fn (int $i): array => [
                    'name' => $hostile[$i % count($hostile)].' #'.$i,
                    'notes' => $i % 3 === 0 ? null : str_repeat('(;\'),', 10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });

        return $tenant;
    }

    /** A `mysqldump` on a private path: the real client piped through $filter. */
    private function fakeDumpClient(string $filter): void
    {
        $real = (string) (new ExecutableFinder)->find('mysqldump');
        $bin = $this->tmp.DIRECTORY_SEPARATOR.'bin';
        File::ensureDirectoryExists($bin);
        File::put($bin.DIRECTORY_SEPARATOR.'mysqldump', "#!/bin/sh\n".escapeshellarg($real)." \"\$@\" {$filter}\n");
        chmod($bin.DIRECTORY_SEPARATOR.'mysqldump', 0700);

        config(["database.connections.{$this->templateConnection()}.dump.dump_binary_path" => $bin]);
    }

    /** The connection tenant connections (and so the tenant dumps) are built from. */
    private function templateConnection(): string
    {
        return (string) (config('tenancy.database.template_tenant_connection') ?: config('tenancy.database.central_connection'));
    }

    private function assertNothingRecorded(Tenant $tenant): void
    {
        $this->assertFalse(TenantBackup::query()->where('tenant_id', $tenant->getTenantKey())->exists());
        $this->assertSame([], Storage::disk('google')->allFiles());
    }

    private function requireClient(string $name): void
    {
        if ((new ExecutableFinder)->find($name) === null) {
            $this->fail("OPS-23: the `{$name}` client is required on PATH for the mysql group.");
        }
    }
}
