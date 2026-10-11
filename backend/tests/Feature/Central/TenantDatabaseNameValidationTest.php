<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3G (security audit item C): a tenant database name chosen by an operator
 * (update-db-config, store tenant) must carry the tenant prefix, be a plain identifier,
 * never be the central database and never be another tenant's database.
 */
final class TenantDatabaseNameValidationTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.database.prefix' => 'tenant_']);
        $this->seedCentralPlatformRoles();
        $this->tenant = $this->createTenant();
        $this->headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
    }

    private function dbConfigUri(): string
    {
        return '/api/v1/super-admin/tenants/'.$this->tenant->getTenantKey().'/update-db-config';
    }

    /** @return array<string, array{string}> */
    public static function malformedNames(): array
    {
        return [
            'no tenant prefix' => ['shop_db'],
            'prefix only' => ['tenant_'],
            'sql injection' => ['tenant_x`; drop database central; --'],
            'path traversal' => ['tenant_../central'],
            'dash' => ['tenant_my-db'],
            'too long' => ['tenant_'.str_repeat('a', 58)],
        ];
    }

    #[DataProvider('malformedNames')]
    public function test_update_db_config_rejects_a_malformed_name(string $name): void
    {
        $before = $this->freshDbName();

        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => $name], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenancy_db_name']);

        $this->assertSame($before, $this->freshDbName());
    }

    public function test_update_db_config_rejects_the_central_database(): void
    {
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        config(["database.connections.{$central}.database" => 'tenant_central_platform']);

        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'tenant_central_platform'], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenancy_db_name']);
    }

    public function test_update_db_config_rejects_another_tenants_explicit_database(): void
    {
        $this->bareTenant('takenbyother', ['tenancy_db_name' => 'tenant_taken_by_other']);

        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'tenant_taken_by_other'], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenancy_db_name']);
    }

    public function test_update_db_config_rejects_another_tenants_default_database(): void
    {
        // stancl stores the generated default name (prefix + id + driver suffix).
        $this->bareTenant('otherdefault');

        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'tenant_otherdefault'], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenancy_db_name']);
    }

    public function test_update_db_config_accepts_a_fresh_prefixed_name_and_the_tenants_own_name(): void
    {
        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'tenant_fresh_name_01'], $this->headers)->assertOk();
        $this->assertSame('tenant_fresh_name_01', $this->freshDbName());

        // Re-submitting its own current name is not a collision.
        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'tenant_fresh_name_01'], $this->headers)->assertOk();
    }

    public function test_store_tenant_rejects_bad_database_names_before_provisioning(): void
    {
        $plan = Plan::query()->create([
            'name' => 'باقة',
            'slug' => 'p-'.Str::lower(Str::random(6)),
            'price_monthly' => '0.000',
            'price_yearly' => '0.000',
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
            'features' => [],
        ]);
        $this->bareTenant('inuse', ['tenancy_db_name' => 'tenant_in_use']);
        $tenantsBefore = Tenant::query()->count();

        foreach (['shop_db', 'tenant_in_use', 'tenant_bad;name'] as $name) {
            $slug = 'new-shop-'.Str::lower(Str::random(6));
            $this->postJson('/api/v1/super-admin/tenants', [
                'name' => 'محل جديد',
                'slug' => $slug,
                'email' => $slug.'@shop.test',
                'password' => 'secret1234',
                'plan_id' => $plan->id,
                'tenancy_db_name' => $name,
            ], $this->headers)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['tenancy_db_name']);
        }

        $this->assertSame($tenantsBefore, Tenant::query()->count());
    }

    /**
     * A central tenant row WITHOUT a database: the TenantCreated provisioning pipeline is
     * faked, so nothing is created on disk and the row rolls back with the test.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function bareTenant(string $id, array $attributes = []): Tenant
    {
        Event::fake([TenantCreated::class]);

        return Tenant::query()->create(array_merge([
            'id' => $id,
            'name' => 'محل '.$id,
            'slug' => $id,
            'email' => $id.'@shop.test',
        ], $attributes));
    }

    private function freshDbName(): ?string
    {
        return Tenant::query()->findOrFail($this->tenant->getTenantKey())->tenancy_db_name;
    }
}
