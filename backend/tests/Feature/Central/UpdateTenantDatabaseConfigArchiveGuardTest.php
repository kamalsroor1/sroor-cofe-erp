<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\TenantArchiveState;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * M1 (OPS-9): POST /super-admin/tenants/{id}/update-db-config must not repoint the database of
 * a tenant whose archive is queued/running (the job backs up THIS database), of an archived
 * tenant (a later `tenants:purge` drops the recorded database), or of a purged tenant: 409 and
 * the stored coordinates stay unchanged. A failed archive request does not block it.
 *
 * The tenant is a central row only (no database is created), so nothing real is touched.
 */
final class UpdateTenantDatabaseConfigArchiveGuardTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();

        $id = 'dbcfg-'.Str::lower(Str::random(8));
        $this->tenant = Tenant::query()->create([
            'id' => $id,
            'name' => 'مستأجر حماية الأرشفة',
            'slug' => $id,
            'email' => $id.'@sroor.test',
            'status' => TenantStatus::Suspended->value,
            'tenancy_create_database' => false,
            'tenancy_db_name' => 'tenant_'.$id,
        ]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function blockedStates(): array
    {
        return [
            'archive queued' => [['archive_state' => TenantArchiveState::Queued->value, 'archive_requested_at' => now()], 'subscription.archive_in_progress'],
            'archive running' => [['archive_state' => TenantArchiveState::Running->value, 'archive_requested_at' => now()], 'subscription.archive_in_progress'],
            'archived' => [['status' => TenantStatus::Archived->value, 'archived_at' => now()], 'subscription.already_archived'],
            'purge started' => [['status' => TenantStatus::Archived->value, 'purge_started_at' => now()], 'subscription.tenant_purged'],
        ];
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    #[DataProvider('blockedStates')]
    public function test_the_database_cannot_be_repointed_while_archiving_or_after_archive(array $columns, string $errorCode): void
    {
        Tenant::query()->whereKey($this->tenant->getKey())->update($columns);

        $this->postJson($this->uri(), [
            'tenancy_db_name' => 'tenant_operator_db',
            'tenancy_db_username' => 'operator',
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', $errorCode);

        $fresh = Tenant::query()->findOrFail($this->tenant->getKey());
        $this->assertSame('tenant_'.$this->tenant->getKey(), $fresh->tenancy_db_name);
        $this->assertNull($fresh->tenancy_db_username);
    }

    public function test_a_failed_archive_request_does_not_block_the_update(): void
    {
        Tenant::query()->whereKey($this->tenant->getKey())->update([
            'archive_state' => TenantArchiveState::Failed->value,
            'archive_error_code' => 'final_backup_failed',
        ]);

        $this->postJson($this->uri(), ['tenancy_db_name' => 'tenant_fixed_db'], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertOk();

        $this->assertSame('tenant_fixed_db', Tenant::query()->findOrFail($this->tenant->getKey())->tenancy_db_name);
    }

    private function uri(): string
    {
        return '/api/v1/super-admin/tenants/'.$this->tenant->getKey().'/update-db-config';
    }
}
