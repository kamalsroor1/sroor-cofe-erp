<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\TenantProvisioningStatus;
use App\Models\Setting;
use App\Models\Tenant;
use App\Support\Tenancy\ProvisioningErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * OPS-2 integration (W2 lane 4F): a tenant that is not `ready` has no database yet.
 *  - the public workspace resolver answers 409 `provisioning.workspace_not_ready`;
 *  - the super-admin tenant JSON (list + details) carries the provisioning state, and the
 *    details never try to open the missing tenant database (zero stats);
 *  - details read `allowed_units` from the stancl virtual attribute and format total_sales
 *    as a bcmath decimal string.
 */
final class ProvisioningReadinessIntegrationTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    // ------------------------------------------------------------------ workspace resolver

    public function test_resolver_answers_409_for_a_pending_tenant(): void
    {
        $tenant = $this->unprovisionedTenant(TenantProvisioningStatus::Pending);

        $this->getJson('/api/v1/central/tenants/resolve?code='.$tenant->getTenantKey())
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'status' => 409,
                'error_code' => 'provisioning.workspace_not_ready',
                'message' => __('provisioning.workspace_not_ready'),
                'tenant' => [
                    'tenant_id' => (string) $tenant->getTenantKey(),
                    'name' => $tenant->name,
                    'provisioning_status' => 'pending',
                ],
            ]);
    }

    public function test_resolver_answers_409_for_a_failed_tenant_without_leaking_the_error_code(): void
    {
        $tenant = $this->unprovisionedTenant(TenantProvisioningStatus::Failed, ProvisioningErrorCode::MigrationFailed->value);

        $response = $this->getJson('/api/v1/central/tenants/resolve?code='.$tenant->slug)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'provisioning.workspace_not_ready')
            ->assertJsonPath('tenant.provisioning_status', 'failed')
            ->assertJsonMissingPath('data');

        $this->assertStringNotContainsString(ProvisioningErrorCode::MigrationFailed->value, (string) $response->getContent());
    }

    public function test_resolver_still_resolves_a_ready_tenant(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/central/tenants/resolve?code='.$tenant->getTenantKey())
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_id', (string) $tenant->getTenantKey());
    }

    // ------------------------------------------------------------------ super-admin tenant JSON

    public function test_tenant_details_carry_the_provisioning_state_and_skip_the_missing_database(): void
    {
        $tenant = $this->unprovisionedTenant(
            TenantProvisioningStatus::Failed,
            ProvisioningErrorCode::DatabaseCreateFailed->value,
            ['allowed_units' => ['unit-a', 'unit-b']],
        );
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson("/api/v1/super-admin/tenants/{$tenant->getTenantKey()}", $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.tenant.provisioning_status', 'failed')
            ->assertJsonPath('data.tenant.provisioning_error_code', ProvisioningErrorCode::DatabaseCreateFailed->value)
            ->assertJsonPath('data.tenant.provisioned_at', null)
            // Virtual attribute (stancl `data` column), no longer the always-null $tenant->data[...].
            ->assertJsonPath('data.allowed_units', ['unit-a', 'unit-b'])
            ->assertJsonPath('data.stats.users_count', 0)
            ->assertJsonPath('data.stats.total_sales', '0.000');

        $this->assertFalse(tenancy()->initialized, 'The super-admin request must never stay inside a tenant.');
    }

    public function test_ready_tenant_details_and_list_carry_ready_and_a_decimal_string_total(): void
    {
        $tenant = $this->createTenant();
        Tenant::query()->whereKey($tenant->getTenantKey())->update(['provisioned_at' => now()]);
        $pending = $this->unprovisionedTenant(TenantProvisioningStatus::Pending);
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $details = $this->getJson("/api/v1/super-admin/tenants/{$tenant->getTenantKey()}", $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.tenant.provisioning_status', 'ready')
            ->assertJsonPath('data.tenant.provisioning_error_code', null);
        $this->assertIsString($details->json('data.tenant.provisioned_at'));
        $this->assertMatchesRegularExpression('/^\d+\.\d{3}$/', (string) $details->json('data.stats.total_sales'));
        $this->assertIsString($details->json('data.stats.total_sales'));

        $list = $this->getJson('/api/v1/super-admin/tenants', $headers)->assertStatus(200);
        $statuses = collect($this->listedTenants($list->json()))
            ->mapWithKeys(static fn (array $row): array => [(string) $row['id'] => $row['provisioning_status'] ?? null])
            ->all();

        $this->assertSame('ready', $statuses[(string) $tenant->getTenantKey()] ?? null);
        $this->assertSame('pending', $statuses[(string) $pending->getTenantKey()] ?? null);
    }

    public function test_ready_tenant_details_sum_the_net_total_of_non_cancelled_invoices_as_an_exact_decimal_string(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, function () use ($tenant): void {
            Setting::set('inventory_units', 'كجم,جرام');
            $customerId = DB::table('customers')->insertGetId(['name' => 'عميل', 'created_at' => now(), 'updated_at' => now()]);

            foreach ([['QA-1', '1.105', 'confirmed'], ['QA-2', '2.250', 'confirmed'], ['QA-3', '100.000', 'cancelled']] as [$number, $total, $status]) {
                DB::table('invoices')->insert([
                    'invoice_number' => $number,
                    'customer_id' => $customerId,
                    'user_id' => $this->tenantAdmin($tenant)->getKey(),
                    'store_id' => $this->tenantStore($tenant)->getKey(),
                    'invoice_date' => '2026-01-01',
                    'payment_type' => 'cash',
                    'status' => $status,
                    'payment_status' => 'paid',
                    'subtotal' => $total,
                    'net_total' => $total,
                    'paid_amount' => $total,
                    'remaining_amount' => '0.000',
                    'total_cost' => '0.000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->getJson("/api/v1/super-admin/tenants/{$tenant->getTenantKey()}", $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(200)
            ->assertJsonPath('data.stats.invoices_count', 3)
            ->assertJsonPath('data.stats.total_sales', '3.355')
            // Read after the sum: proves the stats block no longer dies on a missing column.
            ->assertJsonPath('data.allowed_units', ['كجم', 'جرام']);

        $this->assertFalse(tenancy()->initialized);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * A central tenant row in a non-ready provisioning state, WITHOUT a database (as a
     * queued, running or failed provisioning leaves it).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function unprovisionedTenant(TenantProvisioningStatus $status, ?string $errorCode = null, array $attributes = []): Tenant
    {
        $id = 'qa'.Str::lower(Str::random(12));

        Tenant::query()->create(array_merge([
            'id' => $id,
            'name' => 'Provisioning '.$id,
            'slug' => $id,
            'email' => $id.'@harness.test',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'enabled_features' => [],
            'provisioning_status' => $status,
            'provisioning_error_code' => $errorCode,
            // stancl's CreateDatabase job stops the TenantCreated pipeline on this flag.
            'tenancy_create_database' => false,
        ], $attributes));

        return Tenant::query()->findOrFail($id);
    }

    /**
     * Rows of the paginated tenants list, wherever the envelope nests them.
     *
     * @param  mixed  $payload
     * @return list<array<string, mixed>>
     */
    private function listedTenants($payload): array
    {
        foreach (['data.tenants.data', 'tenants.data', 'data.data', 'data'] as $path) {
            $rows = data_get($payload, $path);
            if (is_array($rows) && $rows !== [] && array_is_list($rows) && is_array($rows[0]) && array_key_exists('id', $rows[0])) {
                return $rows;
            }
        }

        $this->fail('Tenants list not found in the response.');
    }
}
