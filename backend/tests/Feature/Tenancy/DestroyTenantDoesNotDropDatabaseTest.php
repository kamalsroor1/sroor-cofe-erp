<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\TenantBackupKind;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Models\User;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Events\DatabaseDeleted;
use Stancl\Tenancy\Events\DeletingDatabase;
use Stancl\Tenancy\Events\DeletingTenant;
use Stancl\Tenancy\Events\TenantDeleted;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * OPS-9 / OPS-11 regression: DELETE /api/v1/super-admin/tenants/{id} never drops a database,
 * never deletes the tenant row and never fires stancl's delete pipeline, end to end (the
 * sync queue runs ArchiveTenantJob inside the request): the tenant ends `archived` with a
 * verified final backup, and its database, data, domains and row are all still there.
 */
final class DestroyTenantDoesNotDropDatabaseTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-destroy-'.bin2hex(random_bytes(5));

        config([
            'queue.default' => 'sync',
            'backup.backup.password' => 'fixture-archive-password-not-a-secret-0000',
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => null,
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.verify_after_upload' => true,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    public function test_delete_archives_with_a_final_backup_and_the_database_survives(): void
    {
        $tenant = $this->createTenant([
            'status' => TenantStatus::Suspended->value,
            'suspension_reason' => TenantSuspensionReason::NonPayment->value,
        ]);
        $id = (string) $tenant->getKey();
        $this->createTenantUser($tenant, 'cashier');
        $users = $this->inTenant($tenant, fn (): int => User::query()->count());

        Event::fake([DeletingTenant::class, TenantDeleted::class, DeletingDatabase::class, DatabaseDeleted::class]);

        $this->deleteJson('/api/v1/super-admin/tenants/'.$id, [
            'confirm_code' => (string) $tenant->slug,
            'expected_status' => TenantStatus::Suspended->value,
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertStatus(202);

        Event::assertNotDispatched(DeletingTenant::class);
        Event::assertNotDispatched(TenantDeleted::class);
        Event::assertNotDispatched(DeletingDatabase::class);
        Event::assertNotDispatched(DatabaseDeleted::class);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Archived->value, $fresh->status, 'The sync queue ran the archive job.');
        $this->assertNull($fresh->purged_at);
        $this->assertSame(1, $fresh->domains()->count());

        $backup = TenantBackup::query()->where('tenant_id', $id)->sole();
        $this->assertSame(TenantBackupKind::Final, $backup->kind);
        $this->assertNotNull($backup->verified_at);

        $this->assertTrue($this->tenantDatabaseExists($tenant), 'The tenant database must still exist.');
        $this->assertSame($users, $this->inTenant($tenant, fn (): int => User::query()->count()), 'Tenant data is untouched.');
    }

    public function test_a_refused_delete_drops_nothing_either(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Active->value]);
        $id = (string) $tenant->getKey();

        Event::fake([DeletingTenant::class, TenantDeleted::class, DeletingDatabase::class, DatabaseDeleted::class]);

        $this->deleteJson('/api/v1/super-admin/tenants/'.$id, [
            'confirm_code' => (string) $tenant->slug,
            'expected_status' => TenantStatus::Suspended->value,
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertStatus(409);

        Event::assertNotDispatched(TenantDeleted::class);
        Event::assertNotDispatched(DatabaseDeleted::class);
        $this->assertSame(TenantStatus::Active->value, Tenant::query()->findOrFail($id)->status);
        $this->assertSame(0, TenantBackup::query()->count());
        $this->assertTrue($this->tenantDatabaseExists($tenant));
    }
}
