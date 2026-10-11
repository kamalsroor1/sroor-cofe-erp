<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class PermissionsAndContextApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        $storeId = $this->inTenant($this->tenant, function (): int {
            // The harness main store, renamed to this test's fixture.
            $store = Store::query()->where('is_main', true)->firstOrFail();
            $store->update(['name' => 'المخزن الرئيسي', 'code' => 'MAIN']);

            return (int) $store->id;
        });
        $this->store = $this->inTenant($this->tenant, fn (): Store => Store::query()->findOrFail($storeId));
    }

    public function test_guest_can_fetch_system_translations(): void
    {
        $response = $this->getJson('/api/v1/system/translations?locale=ar', $this->tenantGuestHeaders($this->tenant));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'locale',
                'data',
            ])
            ->assertJson([
                'success' => true,
                'locale' => 'ar',
            ]);
    }

    public function test_permissions_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/permissions', $this->tenantGuestHeaders($this->tenant));

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_authenticated_user_can_fetch_permissions_tree_and_own_roles(): void
    {
        // Every tenant is born with the seeded matrix; narrow `cashier` to pos.access only
        // so the response can be asserted exactly, as in the pre-harness fixture.
        $this->inTenant($this->tenant, function (): void {
            Role::findByName('cashier', 'web')->syncPermissions(['pos.access']);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'name' => 'محمد كاشير',
            'phone' => '01000007003',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/permissions', $this->tenantHeaders($this->tenant, $user));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_permissions',
                    'user_roles',
                    'is_admin',
                    'permission_modules' => [
                        'sales' => ['title', 'icon', 'permissions'],
                        'inventory',
                        'purchases',
                        'customers',
                        'suppliers',
                        'expenses',
                        'daily_journal',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_admin' => false,
                    'user_roles' => ['cashier'],
                    'user_permissions' => ['pos.access'],
                ],
            ]);
    }

    public function test_system_context_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/system/context', $this->tenantGuestHeaders($this->tenant));

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_authenticated_user_can_fetch_complete_system_bootstrap_context(): void
    {
        $user = $this->createTenantUser($this->tenant, 'admin', attributes: [
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);

        // Open a cash shift for testing
        $this->inTenant($this->tenant, fn () => CashShift::create([
            'store_id' => $this->store->id,
            'user_id' => $user->id,
            'shift_number' => 'SH-001',
            'opening_cash_balance' => 500.000,
            'opened_at' => now(),
            'status' => 'open',
        ]));

        $response = $this->getJson('/api/v1/system/context', $this->tenantHeaders($this->tenant, $user, $this->store));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'auth' => ['user', 'is_impersonating'],
                    'active_store' => ['id', 'name', 'code'],
                    'stores',
                    'active_shift' => ['id', 'shift_number', 'opening_cash_balance'],
                    'system' => ['company_name', 'company_subtitle', 'system_theme_color', 'server_time'],
                    'branding' => ['logo_light', 'logo_dark', 'logo'],
                    'notifications',
                    'locale',
                    'translations',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'auth' => [
                        'user' => [
                            'name' => 'كمال سرور',
                        ],
                    ],
                    'active_store' => [
                        'id' => $this->store->id,
                        'name' => 'المخزن الرئيسي',
                    ],
                ],
            ]);
    }

    public function test_permissions_and_context_never_cross_tenants(): void
    {
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;

        // The other tenant's narrowed cashier role does not change this tenant's matrix.
        $this->inTenant($other, function (): void {
            Role::findByName('cashier', 'web')->syncPermissions([]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->getJson('/api/v1/permissions', $this->tenantHeaders($this->tenant, $cashier))
            ->assertStatus(200)
            ->assertJsonPath('data.user_roles', ['cashier'])
            ->assertJsonFragment(['pos.access']);

        // A token from this tenant is rejected when the request selects the other tenant.
        $headers = $this->tenantHeaders($this->tenant);
        $this->getJson('/api/v1/system/context', array_merge($headers, ['X-Tenant' => (string) $other->getTenantKey()]))
            ->assertStatus(401);

        // The other tenant's context lists only its own stores.
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($other))
            ->assertStatus(200)
            ->assertJsonPath('data.active_store.id', $otherStoreId)
            ->assertJsonMissing(['name' => 'المخزن الرئيسي']);
    }
}
