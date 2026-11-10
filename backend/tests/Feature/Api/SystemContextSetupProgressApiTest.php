<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Tests\TenantTestCase;

/** FE-08: GET /system/context exposes the onboarding checklist progress to admins only. */
final class SystemContextSetupProgressApiTest extends TenantTestCase
{
    public function test_fresh_tenant_has_every_step_pending(): void
    {
        $tenant = $this->createTenant();

        $data = $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->json('data.setup_progress');

        $this->assertSame(
            ['company_info', 'categories', 'items', 'users', 'shift', 'first_sale', 'completed', 'total'],
            array_keys($data),
        );
        $this->assertFalse($data['company_info']);
        $this->assertFalse($data['categories']);
        $this->assertFalse($data['items']);
        $this->assertFalse($data['users']);
        $this->assertFalse($data['shift']);
        $this->assertFalse($data['first_sale']);
        $this->assertSame(0, $data['completed']);
        $this->assertSame(6, $data['total']);
    }

    public function test_steps_turn_true_once_data_exists(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            Setting::set('company_name', 'Shop');
            Setting::set('company_phone', '01000000500');
            Setting::set('company_address', 'Street 1');
            Category::create(['name' => 'Coffee']);
            Item::create(['name' => 'Latte', 'code' => 'LAT-1', 'category' => 'coffee_beans', 'selling_price' => '10.000', 'is_active' => true]);
        });
        $this->createTenantUser($tenant, 'cashier');

        $data = $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->json('data.setup_progress');

        $this->assertTrue($data['company_info']);
        $this->assertTrue($data['categories']);
        $this->assertTrue($data['items']);
        $this->assertTrue($data['users']);
        $this->assertFalse($data['shift']);
        $this->assertFalse($data['first_sale']);
        $this->assertSame(4, $data['completed']);
    }

    public function test_user_without_settings_permission_gets_no_progress(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, null, ['items.view']);

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant, $user))
            ->assertOk()
            ->assertJsonPath('data.setup_progress', null);
    }

    public function test_shift_and_confirmed_sale_count_but_soft_deleted_invoice_does_not(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->tenantHeaders($tenant);

        $invoice = $this->inTenant($tenant, function (): Invoice {
            $store = Store::query()->firstOrFail();
            $user = User::query()->firstOrFail();
            $customer = Customer::create(['name' => 'Walk-in', 'phone' => '01000000501']);
            CashShift::create(['user_id' => $user->id, 'store_id' => $store->id, 'shift_number' => 'SH-1', 'status' => 'open']);

            return Invoice::create([
                'invoice_number' => 'INV-SP-1', 'store_id' => $store->id, 'customer_id' => $customer->id, 'user_id' => $user->id,
                'invoice_date' => now()->toDateString(), 'subtotal' => '10.000', 'discount_amount' => '0.000',
                'tax_amount' => '0.000', 'net_total' => '10.000', 'paid_amount' => '10.000',
                'remaining_amount' => '0.000', 'status' => 'confirmed', 'payment_type' => 'cash',
            ]);
        });

        $this->getJson('/api/v1/system/setup-progress', $headers)
            ->assertOk()
            ->assertJsonPath('data.shift', true)
            ->assertJsonPath('data.first_sale', true);

        $this->inTenant($tenant, fn () => $invoice->delete());

        $this->getJson('/api/v1/system/setup-progress', $headers)
            ->assertOk()
            ->assertJsonPath('data.first_sale', false);
    }

    public function test_setup_progress_endpoint_returns_the_checklist_shape(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/system/setup-progress', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.total', 6)
            ->assertJsonPath('data.completed', 0)
            ->assertJsonStructure(['data' => ['company_info', 'categories', 'items', 'users', 'shift', 'first_sale', 'completed', 'total']]);
    }

    public function test_setup_progress_endpoint_requires_authentication(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/system/setup-progress', $this->tenantGuestHeaders($tenant))->assertStatus(401);
    }

    public function test_setup_progress_endpoint_is_forbidden_without_settings_permission(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, null, ['items.view']);

        $this->getJson('/api/v1/system/setup-progress', $this->tenantHeaders($tenant, $user))->assertStatus(403);
    }

    public function test_setup_progress_endpoint_allows_non_admin_with_settings_manage(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, null, ['settings.manage']);

        $this->getJson('/api/v1/system/setup-progress', $this->tenantHeaders($tenant, $user))
            ->assertOk()
            ->assertJsonPath('data.total', 6);
    }
}
