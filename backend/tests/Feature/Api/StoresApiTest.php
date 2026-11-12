<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class StoresApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $mainStoreId;

    protected string $mainStoreName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $mainStore = $this->tenantStore($this->tenant);
        $this->mainStoreId = (int) $mainStore->id;
        $this->mainStoreName = (string) $mainStore->name;

        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/stores', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_stores(): void
    {
        $response = $this->getJson('/api/v1/stores', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'active_store',
                'stores',
                'all_users',
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertNotEmpty($response->json('stores'));
    }

    public function test_admin_can_create_a_new_store(): void
    {
        $payload = [
            'name' => 'فرع مدينة نصر',
            'code' => 'NASR-01',
            'type' => 'retail_shop',
            'address' => 'شارع عباس العقاد',
            'phone' => '01000007021',
            'is_main' => false,
        ];

        $response = $this->postJson('/api/v1/stores', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'فرع مدينة نصر',
                    'code' => 'NASR-01',
                    'type' => 'retail_shop',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('stores', [
            'name' => 'فرع مدينة نصر',
            'code' => 'NASR-01',
        ]));
    }

    public function test_create_store_fails_validation_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/stores', [
            'name' => '',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type']);
    }

    public function test_can_view_single_store_details(): void
    {
        $response = $this->getJson('/api/v1/stores/'.$this->mainStoreId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->mainStoreId,
                    'name' => $this->mainStoreName,
                ],
            ]);
    }

    public function test_admin_can_update_store_details(): void
    {
        $branchId = $this->createBranch('فرع المعادي', 'MAADI-01', 'retail_shop');

        $payload = [
            'name' => 'فرع المعادي الجديد',
            'code' => 'MAADI-02',
            'type' => 'retail_shop',
            'address' => 'شارع 9',
        ];

        $response = $this->putJson('/api/v1/stores/'.$branchId, $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'فرع المعادي الجديد',
                    'code' => 'MAADI-02',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('stores', [
            'id' => $branchId,
            'name' => 'فرع المعادي الجديد',
            'code' => 'MAADI-02',
        ]));
    }

    public function test_cannot_disable_main_store(): void
    {
        $response = $this->patchJson('/api/v1/stores/'.$this->mainStoreId.'/toggle-active', [], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_can_toggle_active_status_of_regular_store(): void
    {
        $branchId = $this->createBranch('عربية توزيع 1', 'VAN-01', 'van');

        $response = $this->patchJson('/api/v1/stores/'.$branchId.'/toggle-active', [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => false,
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('stores', [
            'id' => $branchId,
            'is_active' => false,
        ]));
    }

    public function test_can_assign_users_to_store(): void
    {
        $staff = $this->createTenantUser($this->tenant, attributes: [
            'name' => 'أحمد كاشير',
            'phone' => '01000007022',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/stores/'.$this->mainStoreId.'/assign-users', [
            'user_ids' => [$staff->id],
        ], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('store_user', [
            'store_id' => $this->mainStoreId,
            'user_id' => $staff->id,
        ]));
    }

    public function test_can_fetch_store_stocks_with_valuation(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $item = Item::create([
                'name' => 'بن برازيلي كولومبي',
                'code' => 'COF-001',
                'category' => 'coffee_beans',
                'cost_price' => '200.000',
                'selling_price' => '280.000',
                'price_retail' => '280.000',
                'price_wholesale' => '250.000',
                'min_stock_level' => '10.000',
                'is_active' => true,
            ]);

            StoreStock::create([
                'store_id' => $this->mainStoreId,
                'item_id' => $item->id,
                'quantity' => '25.000',
            ]);
        });

        $response = $this->getJson('/api/v1/stores/stocks?store_id='.$this->mainStoreId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'item_id',
                        'item_name',
                        'item_code',
                        'quantity',
                        'cost_price',
                        'total_valuation',
                    ],
                ],
                'meta',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'item_name' => 'بن برازيلي كولومبي',
                        'quantity' => 25.000,
                        'total_valuation' => 5000.000, // 25 * 200
                    ],
                ],
            ]);
    }

    public function test_user_can_switch_active_store(): void
    {
        $branchId = $this->createBranch('فرع الإسكندرية', 'ALX-01', 'retail_shop');

        $response = $this->postJson('/api/v1/stores/switch', [
            'store_id' => $branchId,
        ], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'active_store' => [
                    'id' => $branchId,
                    'name' => 'فرع الإسكندرية',
                ],
            ]);

        $this->assertEquals($branchId, $this->inTenant($this->tenant, fn (): int => (int) User::findOrFail($this->adminUser->id)->default_store_id));
    }

    public function test_stores_of_another_tenant_are_invisible_and_unusable(): void
    {
        $other = $this->createTenant();
        $foreignStoreId = $this->inTenant($other, function (): int {
            $store = Store::create([
                'name' => 'فرع مستأجر آخر',
                'code' => 'OTHER-01',
                'type' => 'retail_shop',
                'is_main' => false,
                'is_active' => true,
            ]);
            $item = Item::create([
                'name' => 'صنف مستأجر آخر',
                'code' => 'OTHER-ITEM',
                'category' => 'coffee_beans',
                'cost_price' => '10.000',
                'selling_price' => '20.000',
                'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $store->id, 'item_id' => $item->id, 'quantity' => '7.000']);

            return $store->id;
        });

        $list = $this->getJson('/api/v1/stores', $this->adminHeaders)->assertOk();
        $list->assertJsonMissing(['name' => 'فرع مستأجر آخر']);
        $this->assertNotContains($foreignStoreId, array_column((array) $list->json('stores'), 'id'));

        $this->getJson('/api/v1/stores/'.$foreignStoreId, $this->adminHeaders)->assertNotFound();
        $this->putJson('/api/v1/stores/'.$foreignStoreId, [
            'name' => 'اختراق',
            'code' => 'HACK-01',
            'type' => 'retail_shop',
        ], $this->adminHeaders)->assertNotFound();
        $this->patchJson('/api/v1/stores/'.$foreignStoreId.'/toggle-active', [], $this->adminHeaders)->assertNotFound();

        $stocks = $this->getJson('/api/v1/stores/stocks?store_id='.$foreignStoreId, $this->adminHeaders);
        $stocks->assertJsonMissing(['item_name' => 'صنف مستأجر آخر']);

        // Switching to a store id that only exists in another tenant must not succeed.
        $switch = $this->postJson('/api/v1/stores/switch', ['store_id' => $foreignStoreId], $this->adminHeaders);
        $this->assertGreaterThanOrEqual(400, $switch->status());
        $this->assertLessThan(500, $switch->status());
        $this->assertSame($this->mainStoreId, $this->inTenant($this->tenant, fn (): int => (int) User::findOrFail($this->adminUser->id)->default_store_id));

        $this->inTenant($other, fn () => $this->assertDatabaseHas('stores', [
            'id' => $foreignStoreId,
            'name' => 'فرع مستأجر آخر',
            'is_active' => true,
        ]));
    }

    // ------------------------------------------------------------------ SEC-1 (W2 batch 6)

    public function test_show_answers_404_for_a_store_the_user_cannot_access(): void
    {
        // L1: StorePolicy::view, else "not found" (never the users and counters of another branch).
        $otherId = $this->createBranch('فرع مقفول', 'LOCKED-1', 'retail');
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $headers = $this->tenantHeaders($this->tenant, $cashier);

        $this->getJson("/api/v1/stores/{$otherId}", $headers)
            ->assertStatus(404)
            ->assertJsonMissing(['name' => 'فرع مقفول']);

        $this->getJson("/api/v1/stores/{$this->mainStoreId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $this->mainStoreId);

        $this->getJson("/api/v1/stores/{$otherId}", $this->adminHeaders)->assertOk();
    }

    public function test_index_scope_is_permission_based_and_the_user_list_is_for_store_managers_only(): void
    {
        // L2: no magic user id / legacy role. stores.view_all lists every branch (also inactive
        // ones) but only stores.manage gets the assignable users list.
        $inactiveId = $this->inTenant($this->tenant, fn (): int => (int) Store::create([
            'name' => 'فرع موقوف', 'code' => 'OFF-1', 'type' => 'retail', 'is_main' => false, 'is_active' => false,
        ])->id);

        $viewer = $this->createTenantUser($this->tenant, permissions: ['stores.view_all']);
        $viewerResponse = $this->getJson('/api/v1/stores', $this->tenantHeaders($this->tenant, $viewer))->assertOk();
        $this->assertContains($inactiveId, array_column($viewerResponse->json('stores'), 'id'));
        $this->assertSame([], $viewerResponse->json('all_users'));

        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $cashierResponse = $this->getJson('/api/v1/stores', $this->tenantHeaders($this->tenant, $cashier))->assertOk();
        $this->assertSame([$this->mainStoreId], array_column($cashierResponse->json('stores'), 'id'));
        $this->assertSame([], $cashierResponse->json('all_users'));

        $this->assertNotEmpty($this->getJson('/api/v1/stores', $this->adminHeaders)->assertOk()->json('all_users'));
    }

    /**
     * SEC-21 (5): exploit before the fix: POST /api/v1/stores/{id}/assign-users with an empty body
     * returned 200 and `assigned_user_ids: []`, silently removing every user from the branch
     * (user_ids was nullable and the action synced []).
     */
    public function test_assign_users_with_an_empty_body_is_rejected_and_keeps_the_assignments(): void
    {
        $staff = $this->createTenantUser($this->tenant, attributes: ['name' => 'كاشير مُعيّن']);
        $this->postJson('/api/v1/stores/'.$this->mainStoreId.'/assign-users', ['user_ids' => [$staff->id]], $this->adminHeaders)->assertOk();

        foreach ([[], ['user_ids' => null], ['user_ids' => 'all'], ['user_ids' => [$staff->id, $staff->id]], ['user_ids' => ['abc']]] as $payload) {
            $this->postJson('/api/v1/stores/'.$this->mainStoreId.'/assign-users', $payload, $this->adminHeaders)
                ->assertStatus(422)
                ->assertJsonValidationErrors(array_key_exists('user_ids', $payload) && is_array($payload['user_ids']) ? ['user_ids.0'] : ['user_ids']);
        }

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('store_user', ['store_id' => $this->mainStoreId, 'user_id' => $staff->id]));

        // Clearing the branch on purpose is an explicit empty list.
        $this->postJson('/api/v1/stores/'.$this->mainStoreId.'/assign-users', ['user_ids' => []], $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('data.assigned_user_ids', []);
        $this->inTenant($this->tenant, fn () => $this->assertDatabaseMissing('store_user', ['store_id' => $this->mainStoreId, 'user_id' => $staff->id]));
    }

    public function test_assign_users_requires_authentication_and_store_management(): void
    {
        $this->postJson('/api/v1/stores/'.$this->mainStoreId.'/assign-users', ['user_ids' => []], $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
        $this->postJson('/api/v1/stores/'.$this->mainStoreId.'/assign-users', ['user_ids' => []], $this->unauthorizedHeaders)->assertStatus(403);
    }

    public function test_assign_users_cannot_reach_a_store_or_user_of_another_tenant(): void
    {
        $other = $this->createTenant();
        $foreignStore = (int) $this->tenantStore($other)->id;
        $foreignUser = $this->createTenantUser($other);

        // A foreign user id does not exist in this tenant's DB.
        $this->postJson('/api/v1/stores/'.$this->mainStoreId.'/assign-users', ['user_ids' => [$foreignUser->id]], $this->adminHeaders)
            ->assertStatus(422)->assertJsonValidationErrors(['user_ids.0']);
        // A foreign store id is not found here.
        $this->postJson('/api/v1/stores/'.$foreignStore.'/assign-users', ['user_ids' => []], $this->adminHeaders)->assertStatus(404);
    }

    /**
     * SEC-21 (small item): exploit before the fix: GET /api/v1/stores as a user without any
     * branch assignment returned 200 with the main store's `deletion_blockers` (invoice/purchase
     * counters), its document counters and the assigned users with their e-mails.
     */
    public function test_store_list_hides_management_fields_from_users_who_do_not_manage_stores(): void
    {
        $staff = $this->createTenantUser($this->tenant, attributes: ['name' => 'موظف معيّن']);
        $this->postJson('/api/v1/stores/'.$this->mainStoreId.'/assign-users', ['user_ids' => [$staff->id]], $this->adminHeaders)->assertOk();

        $unassigned = $this->createTenantUser($this->tenant, 'cashier', attributes: ['default_store_id' => null]);

        foreach ([$this->tenantHeaders($this->tenant, $unassigned), $this->unauthorizedHeaders] as $headers) {
            $response = $this->getJson('/api/v1/stores', $headers)->assertOk();
            foreach (array_merge($response->json('stores'), [$response->json('active_store')]) as $store) {
                $this->assertIsArray($store);
                foreach (['deletion_blockers', 'can_be_deleted', 'assigned_users', 'assigned_user_ids', 'invoices_count', 'purchases_count', 'stocks_count'] as $field) {
                    $this->assertArrayNotHasKey($field, $store);
                }
            }
            $this->assertStringNotContainsString((string) $staff->email, (string) $response->getContent());
        }

        // A store manager still gets them.
        $admin = $this->getJson('/api/v1/stores', $this->adminHeaders)->assertOk()->json('stores.0');
        $this->assertArrayHasKey('deletion_blockers', $admin);
        $this->assertArrayHasKey('assigned_users', $admin);
        $this->assertArrayHasKey('invoices_count', $admin);
    }

    private function createBranch(string $name, string $code, string $type): int
    {
        return $this->inTenant($this->tenant, fn (): int => Store::create([
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'is_main' => false,
            'is_active' => true,
        ])->id);
    }
}
