<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\CentralUser;
use App\Models\Item;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * IDEN-4.8 smoke test for the multi-tenant harness (Tests\TenantTestCase +
 * Tests\Concerns\InteractsWithTenants).
 *
 * Proves the harness gives every test REAL database-per-tenant isolation:
 * two tenants never share rows, a request never inherits the previous
 * request's tenant / authenticated user, tokens do not cross tenants, the
 * central DB holds no tenant tables, nothing is left on disk afterwards, and
 * provisioning stays under the per-tenant overhead budget.
 *
 * Runs on sqlite (central :memory: + one file per tenant) and on MySQL 8
 * (QA-1 job, `--group mysql`).
 */
#[Group('harness')]
#[Group('mysql')]
final class TenantHarnessSmokeTest extends TenantTestCase
{
    /** Acceptance criterion: provisioning overhead < 1.5 s per tenant. */
    private const MAX_SECONDS_PER_TENANT = 1.5;

    public function test_two_tenants_get_distinct_databases_with_identical_baseline(): void
    {
        $a = $this->createTenant(['name' => 'محمصة الأمل']);
        $b = $this->createTenant(['name' => 'Coffee Corner']);

        $this->assertNotSame($a->getTenantKey(), $b->getTenantKey());
        $this->assertNotSame($this->tenantDatabaseName($a), $this->tenantDatabaseName($b));
        $this->assertTrue($this->tenantDatabaseExists($a));
        $this->assertTrue($this->tenantDatabaseExists($b));

        foreach ([$a, $b] as $tenant) {
            $baseline = $this->inTenant($tenant, fn (): array => [
                'tenant' => tenant()?->getTenantKey(),
                'users' => User::query()->count(),
                'main_stores' => Store::query()->where('is_main', true)->count(),
                'admin_is_admin' => User::query()->firstOrFail()->hasRole('admin'),
                'has_items_table' => Schema::hasTable('items'),
            ]);

            $this->assertSame([
                'tenant' => $tenant->getTenantKey(),
                'users' => 1,
                'main_stores' => 1,
                'admin_is_admin' => true,
                'has_items_table' => true,
            ], $baseline);
        }

        $this->assertSame('محمصة الأمل', Tenant::query()->findOrFail($a->getTenantKey())->name);
        $this->assertFalse(tenancy()->initialized, 'inTenant() must leave the test in central context.');
    }

    public function test_central_database_holds_no_tenant_tables(): void
    {
        $this->createTenant();

        $central = Schema::connection($this->centralConnectionName());

        $this->assertTrue($central->hasTable('tenants'));
        $this->assertTrue($central->hasTable('domains'));
        $this->assertFalse($central->hasTable('items'), 'Tenant tables leaked into the central DB.');
        $this->assertFalse($central->hasTable('invoices'), 'Tenant tables leaked into the central DB.');
        $this->assertFalse($central->hasTable('stores'), 'Tenant tables leaked into the central DB.');
    }

    public function test_rows_written_in_one_tenant_are_invisible_in_the_other(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->inTenant($a, fn () => $this->makeItem('QA-A-01', 'بن يمني', '0.250'));

        $this->assertSame(1, $this->inTenant($a, fn (): int => Item::query()->count()));
        $this->assertSame(0, $this->inTenant($b, fn (): int => Item::query()->count()));
        $this->assertSame(
            '0.250',
            $this->inTenant($a, fn (): string => (string) Item::query()->where('code', 'QA-A-01')->value('current_stock')),
        );

        // Same code in B is allowed: unique constraints are per tenant DB.
        $this->inTenant($b, fn () => $this->makeItem('QA-A-01', 'Brazil Santos', '7.000'));
        $this->assertSame(
            'بن يمني',
            $this->inTenant($a, fn (): string => (string) Item::query()->where('code', 'QA-A-01')->value('name')),
        );
    }

    public function test_in_tenant_restores_the_previous_context_when_nested(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $seen = $this->inTenant($a, function () use ($b): array {
            $inner = $this->inTenant($b, fn (): ?string => tenant()?->getTenantKey());

            return [$inner, tenant()?->getTenantKey()];
        });

        $this->assertSame([$b->getTenantKey(), $a->getTenantKey()], $seen);
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_tenant_headers_authenticate_each_tenant_admin_against_its_own_database(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($a))
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->tenantAdmin($a)->id)
            ->assertJsonPath('data.user.phone', $this->tenantAdmin($a)->phone);

        $this->assertFalse(tenancy()->initialized, 'A request must not leave tenancy initialized for the test.');

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($b))
            ->assertOk()
            ->assertJsonPath('data.user.phone', $this->tenantAdmin($b)->phone);
    }

    public function test_api_lists_only_the_requesting_tenants_rows_on_sequential_requests(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $this->inTenant($a, fn () => $this->makeItem('QA-ONLY-A', 'صنف المستأجر أ', '3.000'));
        $this->inTenant($b, fn () => $this->makeItem('QA-ONLY-B', 'صنف المستأجر ب', '4.000'));

        // A then B then A: each request must resolve its own tenant, never the previous one.
        $this->getJson('/api/v1/items', $this->tenantHeaders($a))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'QA-ONLY-A')
            ->assertJsonMissing(['code' => 'QA-ONLY-B']);

        $this->getJson('/api/v1/items', $this->tenantHeaders($b))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'QA-ONLY-B')
            ->assertJsonMissing(['code' => 'QA-ONLY-A']);

        $this->getJson('/api/v1/items', $this->tenantHeaders($a))
            ->assertOk()
            ->assertJsonPath('data.0.code', 'QA-ONLY-A');
    }

    public function test_host_based_resolution_reaches_the_tenant_database(): void
    {
        $a = $this->createTenant();
        $this->inTenant($a, fn () => $this->makeItem('QA-HOST-A', 'صنف بالدومين', '1.000'));

        $headers = $this->tenantHeaders($a);
        unset($headers['X-Tenant']);

        $this->getJson($this->tenantUrl($a, '/api/v1/items'), $headers)
            ->assertOk()
            ->assertJsonPath('data.0.code', 'QA-HOST-A');
    }

    public function test_token_of_tenant_a_is_rejected_by_tenant_b(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $crossover = array_merge($this->tenantHeaders($b), [
            'Authorization' => 'Bearer '.$this->tenantToken($a),
        ]);

        $this->getJson('/api/v1/auth/me', $crossover)->assertUnauthorized();
        $this->getJson('/api/v1/items', $crossover)->assertUnauthorized();
    }

    public function test_authenticated_user_does_not_leak_into_the_next_request(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($a))->assertOk();

        // No Authorization header: the user resolved by the previous request must not be reused.
        $this->getJson('/api/v1/auth/me', ['X-Tenant' => $b->getTenantKey()])->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', ['X-Tenant' => $a->getTenantKey()])->assertUnauthorized();
    }

    public function test_unknown_tenant_header_is_404(): void
    {
        $this->createTenant();

        $this->getJson('/api/v1/auth/me', ['X-Tenant' => 'qa-does-not-exist'])->assertNotFound();
    }

    public function test_tenant_user_with_explicit_permissions_is_scoped_to_its_tenant(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $cashier = $this->createTenantUser($a, role: 'cashier');
        $viewer = $this->createTenantUser($a, permissions: ['items.view']);

        $this->assertTrue($this->inTenant($a, fn (): bool => User::query()->findOrFail($cashier->id)->hasRole('cashier')));
        $this->assertTrue($this->inTenant($a, fn (): bool => User::query()->findOrFail($viewer->id)->can('items.view')));
        $this->assertSame(1, $this->inTenant($b, fn (): int => User::query()->count()), 'Users created for A appeared in B.');

        $this->getJson('/api/v1/items', $this->tenantHeaders($a, $viewer))->assertOk();
        // The viewer's token lives in A's DB: presenting it to B must not authenticate anyone.
        $crossover = array_merge($this->tenantHeaders($b), [
            'Authorization' => 'Bearer '.$this->tenantToken($a, $viewer),
        ]);
        $this->getJson('/api/v1/items', $crossover)->assertUnauthorized();
    }

    public function test_central_super_admin_lives_only_in_the_central_database(): void
    {
        $tenant = $this->createTenant();
        $super = $this->centralSuperAdmin();

        $this->assertInstanceOf(CentralUser::class, $super);
        $this->assertSame($this->centralConnectionName(), $super->getConnectionName());
        $this->assertTrue(
            DB::connection($this->centralConnectionName())->table($super->getTable())->where('id', $super->getKey())->exists(),
        );
        // Harness facts only. Whether a CentralUser passes PlatformSuperAdmin::check()
        // is IDEN-1.x authorization semantics and is asserted in those tests.

        // Never mirrored into a tenant DB.
        $this->assertSame(
            0,
            $this->inTenant($tenant, fn (): int => User::query()->where('email', $super->email)->count()),
        );

        $centralHeaders = $this->centralHeaders($super);
        $this->assertArrayHasKey('Authorization', $centralHeaders);
        $this->assertArrayNotHasKey('X-Tenant', $centralHeaders);
        $this->assertArrayNotHasKey('X-Store-Id', $centralHeaders);
    }

    public function test_cleanup_removes_every_tenant_database_and_storage_directory(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        // Touch tenant-scoped storage so the cleanup has something to remove.
        $storageDirs = [];
        foreach ([$a, $b] as $tenant) {
            $storageDirs[] = $this->inTenant($tenant, function (): string {
                $dir = storage_path('app');
                @mkdir($dir, 0777, true);
                file_put_contents($dir.DIRECTORY_SEPARATOR.'harness-probe.txt', 'probe');

                return storage_path();
            });
        }

        foreach ($storageDirs as $dir) {
            $this->assertDirectoryExists($dir);
        }

        $this->cleanUpTenants();

        $this->assertFalse($this->tenantDatabaseExists($a), 'Tenant A database left behind.');
        $this->assertFalse($this->tenantDatabaseExists($b), 'Tenant B database left behind.');
        foreach ($storageDirs as $dir) {
            $this->assertDirectoryDoesNotExist($dir, 'Tenant storage directory left behind.');
        }
        $this->assertFalse(tenancy()->initialized);

        // Idempotent: a second cleanup (the automatic one in tearDown) is a no-op.
        $this->cleanUpTenants();
        $this->assertFalse($this->tenantDatabaseExists($a));
    }

    public function test_provisioning_overhead_is_under_the_per_tenant_budget(): void
    {
        // Warm-up so one-off per-process costs (template build, autoload) are not counted.
        $this->createTenant();

        $count = 3;
        $start = hrtime(true);
        for ($i = 0; $i < $count; $i++) {
            $this->createTenant();
        }
        $secondsPerTenant = (hrtime(true) - $start) / 1e9 / $count;

        $this->assertLessThan(
            self::MAX_SECONDS_PER_TENANT,
            $secondsPerTenant,
            sprintf('Harness overhead %.3fs per tenant exceeds %.1fs.', $secondsPerTenant, self::MAX_SECONDS_PER_TENANT),
        );
    }

    private function makeItem(string $code, string $name, string $stock): Item
    {
        return Item::query()->create([
            'code' => $code,
            'name' => $name,
            'category' => 'coffee_beans',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'current_stock' => $stock,
            'min_stock_level' => '0.000',
            'is_active' => true,
        ]);
    }
}
