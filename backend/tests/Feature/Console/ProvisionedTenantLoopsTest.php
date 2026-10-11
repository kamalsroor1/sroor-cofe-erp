<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Tenancy\MigrateProvisionedTenants;
use App\Console\Tenancy\RollbackProvisionedTenants;
use App\Console\Tenancy\RunForProvisionedTenants;
use App\Console\Tenancy\SeedProvisionedTenants;
use App\Enums\TenantProvisioningStatus;
use App\Health\Checks\BackupFreshnessCheck;
use App\Models\Tenant;
use App\Models\TenantBackup;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Health\Enums\Status;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\RollingBackDatabase;
use Tests\TenantTestCase;

/**
 * OPS-2 integration (W2 lane 4F): every loop over "all tenants" skips tenants whose
 * provisioning is not `ready` (pending / running / failed have no database). Covered:
 * stancl's tenants:migrate / rollback / run defaults (deploy stage 7, setup-local.ps1),
 * backup:tenants (nightly + deploy pre-migration backup), the Backup Freshness health
 * check and tenants:audit-super-admin. An explicit --tenants list is kept as given.
 */
final class ProvisionedTenantLoopsTest extends TenantTestCase
{
    private string $tmp = '';

    protected function tearDown(): void
    {
        if ($this->tmp !== '') {
            File::deleteDirectory($this->tmp);
        }
        DB::purge('backup_central_probe');

        parent::tearDown();
    }

    public function test_stancl_tenant_commands_are_swapped_for_the_provisioned_defaults(): void
    {
        $commands = $this->app->make(Kernel::class)->all();

        $this->assertInstanceOf(MigrateProvisionedTenants::class, $commands['tenants:migrate']);
        $this->assertInstanceOf(RollbackProvisionedTenants::class, $commands['tenants:rollback']);
        $this->assertInstanceOf(SeedProvisionedTenants::class, $commands['tenants:seed']);
        $this->assertInstanceOf(RunForProvisionedTenants::class, $commands['tenants:run']);
    }

    public function test_tenants_migrate_without_tenants_option_skips_unprovisioned_tenants(): void
    {
        $ready = $this->createTenant();
        $pending = $this->unprovisionedTenant(TenantProvisioningStatus::Pending);
        $failed = $this->unprovisionedTenant(TenantProvisioningStatus::Failed);
        $migrated = $this->recordTenantEvents(MigratingDatabase::class);

        $exit = Artisan::call('tenants:migrate', ['--force' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertSame([(string) $ready->getTenantKey()], $migrated->getArrayCopy());
        $this->assertStringNotContainsString($pending, $output);
        $this->assertStringNotContainsString($failed, $output);
        $this->assertStringContainsString(__('console.tenants.skipped_not_provisioned', ['count' => 2]), $output);
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_tenants_migrate_with_only_unprovisioned_tenants_runs_for_none(): void
    {
        // Without the guard an empty --tenants list makes stancl fall back to ALL tenants.
        $this->unprovisionedTenant(TenantProvisioningStatus::Running);
        $migrated = $this->recordTenantEvents(MigratingDatabase::class);

        $exit = Artisan::call('tenants:migrate', ['--force' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertSame([], $migrated->getArrayCopy());
        $this->assertStringContainsString(__('console.tenants.none_provisioned'), $output);
    }

    public function test_an_explicit_tenants_option_is_kept_as_given(): void
    {
        $first = $this->createTenant();
        $this->createTenant();
        $migrated = $this->recordTenantEvents(MigratingDatabase::class);

        $exit = Artisan::call('tenants:migrate', ['--tenants' => [(string) $first->getTenantKey()], '--force' => true]);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertSame([(string) $first->getTenantKey()], $migrated->getArrayCopy());
    }

    public function test_tenants_rollback_and_run_default_to_provisioned_tenants(): void
    {
        $ready = $this->createTenant();
        $pending = $this->unprovisionedTenant(TenantProvisioningStatus::Pending);
        $rolledBack = $this->recordTenantEvents(RollingBackDatabase::class);

        $this->assertSame(0, Artisan::call('tenants:rollback', ['--pretend' => true, '--force' => true]), Artisan::output());
        $this->assertSame([(string) $ready->getTenantKey()], $rolledBack->getArrayCopy());

        $this->assertSame(0, Artisan::call('tenants:run', ['commandname' => 'inspire']));
        $output = Artisan::output();
        $this->assertStringContainsString('Tenant: '.$ready->getTenantKey(), $output);
        $this->assertStringNotContainsString($pending, $output);
    }

    public function test_backup_tenants_skips_unprovisioned_tenants(): void
    {
        $this->configureBackups();
        $ready = $this->createTenant();
        $pending = $this->unprovisionedTenant(TenantProvisioningStatus::Pending);
        $failed = $this->unprovisionedTenant(TenantProvisioningStatus::Failed);

        // A tenant without a database inside the loop would be a failure (exit 1 + mail).
        $this->artisan('backup:tenants', ['--skip-central' => true])->assertExitCode(0);

        $this->assertSame(1, TenantBackup::query()->where('tenant_id', $ready->getTenantKey())->whereNotNull('verified_at')->count());
        $this->assertSame(0, TenantBackup::query()->whereIn('tenant_id', [$pending, $failed])->count());
        Notification::assertNothingSent();
    }

    public function test_backup_freshness_does_not_require_unprovisioned_tenants(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        $pending = $this->unprovisionedTenant(TenantProvisioningStatus::Failed);
        Tenant::query()->whereKey($pending)->update(['created_at' => now()->subDays(3)]);
        TenantBackup::query()->insert([
            'tenant_id' => null,
            'disk' => 'google',
            'path' => 'sroor-backups/central/x.zip',
            'sha256' => str_repeat('a', 64),
            'size_bytes' => 10,
            'created_at' => now()->subHour(),
            'verified_at' => now()->subHour(),
        ]);

        $result = BackupFreshnessCheck::new()->run();

        $this->assertSame(Status::ok(), $result->status, $result->getNotificationMessage());
        $this->assertSame(1, $result->meta['checked']);
    }

    public function test_super_admin_audit_skips_unprovisioned_tenants(): void
    {
        $ready = $this->createTenant();
        $pending = $this->unprovisionedTenant(TenantProvisioningStatus::Pending);

        Artisan::call('tenants:audit-super-admin');
        $output = Artisan::output();

        $this->assertStringContainsString((string) $ready->getTenantKey(), $output);
        $this->assertStringNotContainsString($pending, $output);
        $this->assertFalse(tenancy()->initialized);
    }

    // ------------------------------------------------------------------ helpers

    /** A central tenant row in a non-ready provisioning state, WITHOUT a database. */
    private function unprovisionedTenant(TenantProvisioningStatus $status): string
    {
        $id = 'qa'.Str::lower(Str::random(12));

        Tenant::query()->create([
            'id' => $id,
            'name' => 'Provisioning '.$id,
            'slug' => $id,
            'email' => $id.'@harness.test',
            'status' => 'active',
            'enabled_features' => [],
            'provisioning_status' => $status,
            // stancl's CreateDatabase job stops the TenantCreated pipeline on this flag.
            'tenancy_create_database' => false,
        ]);

        return $id;
    }

    /**
     * Tenant ids for which the given stancl per-tenant event fired.
     *
     * @param  class-string  $event
     * @return \ArrayObject<int, string>
     */
    private function recordTenantEvents(string $event): \ArrayObject
    {
        $ids = new \ArrayObject;
        Event::listen($event, static function (object $fired) use ($ids): void {
            $ids->append((string) $fired->tenant->getTenantKey());
        });

        return $ids;
    }

    private function configureBackups(): void
    {
        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-backup-loops-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => 'fixture-archive-password-not-a-secret-0000',
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => 'ops@example.test',
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.verify_after_upload' => true,
        ]);
    }
}
