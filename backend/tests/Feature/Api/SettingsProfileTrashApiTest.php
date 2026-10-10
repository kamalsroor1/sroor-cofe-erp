<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

class SettingsProfileTrashApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->store = $this->tenantStore($this->tenant);

        $this->adminUser = $this->createTenantUser($this->tenant, 'admin', attributes: [
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'email' => 'kamal@sroor.com',
            'password' => Hash::make('password123'),
            'theme_preference' => 'dark',
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminHeaders = $this->tenantHeaders($this->tenant, $this->adminUser);
    }

    public function test_can_get_and_update_settings(): void
    {
        $getResponse = $this->getJson('/api/v1/settings', $this->adminHeaders);

        $getResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['settings', 'stores', 'users_count', 'system_info']);

        $payload = [
            'company_name' => 'محامص سرور العالمية',
            'company_subtitle' => 'أجود أنواع البن الفاخر',
            'company_phone' => '01000007005',
            'company_address' => 'القاهرة الجديدة',
            'invoice_footer_note' => 'أهلاً بكم في سرور كوفي',
            'show_print_company_name' => true,
            'show_print_subtitle' => true,
            'show_print_logo' => true,
            'thermal_show_customer_balance' => true,
            'print_show_qr' => true,
            'telegram_notifications_enabled' => false,
        ];

        $updateResponse = $this->postJson('/api/v1/settings', $payload, $this->adminHeaders);

        $updateResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, function (): void {
            $this->assertEquals('محامص سرور العالمية', Setting::get('company_name'));
            $this->assertEquals('أجود أنواع البن الفاخر', Setting::get('company_subtitle'));
        });
    }

    public function test_can_get_and_update_profile(): void
    {
        $getResponse = $this->getJson('/api/v1/profile', $this->adminHeaders);

        $getResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'كمال سرور',
                    'phone' => self::ADMIN_PHONE,
                ],
            ]);

        $updatePayload = [
            'name' => 'كمال سرور المطور',
            'phone' => self::ADMIN_PHONE,
            'email' => 'developer@sroor.com',
            'theme_preference' => 'light',
            'current_password' => 'password123',
            'new_password' => 'newsecret456',
            'new_password_confirmation' => 'newsecret456',
        ];

        $updateResponse = $this->putJson('/api/v1/profile', $updatePayload, $this->adminHeaders);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'كمال سرور المطور',
                    'theme_preference' => 'light',
                ],
            ]);

        $this->inTenant($this->tenant, function (): void {
            $this->assertTrue(Hash::check('newsecret456', (string) User::query()->findOrFail($this->adminUser->id)->password));
        });
    }

    public function test_can_get_trash_and_restore_and_force_delete(): void
    {
        $itemId = $this->inTenant($this->tenant, function (): int {
            $item = Item::create([
                'name' => 'صنف للتجربة بسلة المهملات',
                'code' => 'TRASH-ITEM-01',
                'category' => 'coffee_beans',
                'cost_price' => '200.000',
                'selling_price' => '300.000',
                'current_stock' => '10.000',
                'is_active' => true,
            ]);

            $item->delete(); // Soft deleted

            return (int) $item->id;
        });

        $trashResponse = $this->getJson('/api/v1/trash?tab=items', $this->adminHeaders);

        $trashResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'tab' => 'items',
            ])
            ->assertJsonStructure(['data', 'counts', 'pagination']);

        $this->assertEquals(1, $trashResponse->json('counts.items'));

        // Restore
        $restoreResponse = $this->postJson("/api/v1/trash/items/{$itemId}/restore", [], $this->adminHeaders);

        $restoreResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, function () use ($itemId): void {
            $this->assertNull(Item::withTrashed()->findOrFail($itemId)->deleted_at);

            // Delete again before the force delete
            Item::query()->findOrFail($itemId)->delete();
        });

        $forceResponse = $this->deleteJson("/api/v1/trash/items/{$itemId}/force", [], $this->adminHeaders);

        $forceResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseMissing('items', ['id' => $itemId]));
    }

    public function test_settings_profile_and_trash_are_isolated_between_tenants(): void
    {
        $other = $this->createTenant();
        $otherHeaders = $this->tenantHeaders($other);

        $foreignItemId = $this->inTenant($other, function (): int {
            Setting::set('company_name', 'شركة المستأجر الآخر');

            $item = Item::create([
                'name' => 'صنف محذوف لمستأجر آخر',
                'code' => 'TRASH-FOREIGN',
                'cost_price' => '1.000',
                'selling_price' => '2.000',
                'current_stock' => '3.000',
                'is_active' => true,
            ]);
            $item->delete();

            return (int) $item->id;
        });

        // Settings written by this tenant do not reach the other one.
        $this->postJson('/api/v1/settings', ['company_name' => 'محامص سرور العالمية'], $this->adminHeaders)
            ->assertStatus(200);
        $this->inTenant($other, fn () => $this->assertSame('شركة المستأجر الآخر', Setting::get('company_name')));

        // This tenant's trash does not list, restore or purge the other tenant's rows.
        $this->getJson('/api/v1/trash?tab=items', $this->adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('counts.items', 0);
        // TrashController maps "not found" to 422 today (see TrashApiTest); either way it must fail.
        $restore = $this->postJson("/api/v1/trash/items/{$foreignItemId}/restore", [], $this->adminHeaders);
        $this->assertContains($restore->status(), [404, 422]);
        $restore->assertJsonPath('success', false);
        $force = $this->deleteJson("/api/v1/trash/items/{$foreignItemId}/force", [], $this->adminHeaders);
        $this->assertContains($force->status(), [404, 422]);
        $force->assertJsonPath('success', false);

        // A token minted in this tenant is not valid in the other tenant.
        $this->getJson('/api/v1/profile', array_merge($this->adminHeaders, ['X-Tenant' => (string) $other->getTenantKey()]))
            ->assertStatus(401);
        $this->getJson('/api/v1/profile', $otherHeaders)->assertStatus(200)
            ->assertJsonMissing(['name' => 'كمال سرور']);

        $this->inTenant($other, function () use ($foreignItemId): void {
            $this->assertNotNull(Item::onlyTrashed()->find($foreignItemId));
        });
    }
}
