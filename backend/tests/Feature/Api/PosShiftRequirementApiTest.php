<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * POS-10: a branch with require_shift_for_sales (default ON) refuses sales without an open cash
 * shift in THAT branch: POS checkout, /invoices and the blender invoice; plus the setting itself.
 */
final class PosShiftRequirementApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private Store $otherStore;

    private User $cashier;

    private User $admin;

    private Customer $customer;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite default is OFF (phpunit.xml); this test exercises the production default.
        config(['pos.require_shift_by_default' => true]);

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore(['name' => 'Main', 'code' => 'MAIN-SH', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);
        $this->otherStore = Store::create(['name' => 'Other', 'code' => 'OTH-SH', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);

        $this->cashier = $this->makeUser('01000000021', 'cashier');
        $this->admin = $this->makeUser('01000000022', 'admin');

        $this->customer = Customer::create(['name' => 'Walk', 'phone' => '01100000011', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);
        $this->item = Item::create([
            'name' => 'SH-1', 'code' => 'SH-1', 'unit' => 'كجم', 'cost_price' => '50.000', 'selling_price' => '100.000',
            'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true,
        ]);
        foreach ([$this->store, $this->otherStore] as $store) {
            StoreStock::create(['store_id' => $store->id, 'item_id' => $this->item->id, 'quantity' => '100.000']);
        }
    }

    private function makeUser(string $phone, string $role): User
    {
        $user = User::factory()->create([
            'name' => $role.$phone, 'phone' => $phone, 'password' => Hash::make('password'),
            'is_active' => true, 'default_store_id' => $this->store->id,
        ]);
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function as(User $user, ?Store $store = null): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withHeaders([
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) ($store ?? $this->store)->id,
            'Accept' => 'application/json',
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function sell(User $user, string $url = '/api/v1/pos/checkout', array $extra = [], ?Store $store = null): TestResponse
    {
        return $this->as($user, $store)->postJson($url, array_merge([
            'store_id' => ($store ?? $this->store)->id,
            'customer_id' => $this->customer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $this->item->id, 'quantity' => '1.000']],
        ], $extra));
    }

    private function openShift(Store $store, string $status = 'open'): CashShift
    {
        return CashShift::create([
            'user_id' => $this->admin->id, 'store_id' => $store->id, 'shift_number' => 'SHIFT-T-'.$store->id.$status,
            'status' => $status, 'opened_at' => now(), 'opening_cash_balance' => '100.000',
        ]);
    }

    private function setRequired(Store $store, bool $required): void
    {
        $setting = StorePosSetting::forStore((int) $store->id);
        $setting->require_shift_for_sales = $required;
        $setting->save();
    }

    // ------------------------------------------------------------ enforcement

    public function test_checkout_is_refused_without_an_open_shift_by_default(): void
    {
        $response = $this->sell($this->cashier);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shift'])
            ->assertJsonPath('errors.shift.0', __('pos.shift_required'));
        $this->assertSame(0, Invoice::count());
        $this->assertSame('100.000', (string) StoreStock::where('store_id', $this->store->id)->where('item_id', $this->item->id)->value('quantity'));
    }

    public function test_generic_invoices_endpoint_is_refused_without_an_open_shift(): void
    {
        $this->sell($this->cashier, '/api/v1/invoices')->assertStatus(422)->assertJsonValidationErrors(['shift']);
        $this->assertSame(0, Invoice::count());
    }

    public function test_blender_invoice_is_refused_without_an_open_shift(): void
    {
        $this->as($this->admin)->postJson('/api/v1/coffee-blender/invoice', [
            'blend_name' => 'Mix', 'customer_id' => $this->customer->id, 'target_weight_grams' => 250,
            'components' => [['item_id' => $this->item->id, 'grams' => 250, 'unit_price' => 200.000]],
        ])->assertStatus(422)->assertJsonValidationErrors(['shift']);
        $this->assertSame(0, Invoice::count());
    }

    public function test_checkout_is_allowed_when_the_branch_does_not_require_a_shift(): void
    {
        $this->setRequired($this->store, false);

        $this->sell($this->cashier)->assertCreated();
        $this->sell($this->cashier, '/api/v1/invoices')->assertCreated();
        $this->assertSame(2, Invoice::count());
    }

    public function test_checkout_is_allowed_with_an_open_shift(): void
    {
        $this->openShift($this->store);

        $this->sell($this->cashier)->assertCreated();
        $this->sell($this->cashier, '/api/v1/invoices')->assertCreated();
    }

    public function test_a_shift_of_another_store_does_not_count(): void
    {
        $this->openShift($this->otherStore);

        $this->sell($this->cashier)->assertStatus(422)->assertJsonValidationErrors(['shift']);
        // ...and the other store, which has its own open shift, sells fine.
        $this->sell($this->admin, '/api/v1/pos/checkout', [], $this->otherStore)->assertCreated();
    }

    public function test_a_closed_shift_does_not_count(): void
    {
        $this->openShift($this->store, 'closed');

        $this->sell($this->cashier)->assertStatus(422)->assertJsonValidationErrors(['shift']);
    }

    public function test_requirement_is_per_branch(): void
    {
        $this->setRequired($this->otherStore, false);

        $this->sell($this->cashier)->assertStatus(422);
        $this->sell($this->admin, '/api/v1/pos/checkout', [], $this->otherStore)->assertCreated();
    }

    public function test_an_idempotent_replay_still_succeeds_after_the_shift_was_closed(): void
    {
        $shift = $this->openShift($this->store);
        $uuid = '3f1c9a52-6b7e-4d1a-9c55-0a1b2c3d4e5f';

        $this->sell($this->cashier, '/api/v1/pos/checkout', ['client_uuid' => $uuid])->assertCreated();
        $shift->update(['status' => 'closed']);

        $this->sell($this->cashier, '/api/v1/pos/checkout', ['client_uuid' => $uuid])->assertOk();
        $this->assertSame(1, Invoice::count());
    }

    // ----------------------------------------------------------- shift status

    public function test_current_shift_reports_whether_the_branch_requires_a_shift(): void
    {
        $this->as($this->cashier)->getJson('/api/v1/shifts/current')
            ->assertOk()->assertJsonPath('has_active', false)->assertJsonPath('requires_shift', true);

        $this->setRequired($this->store, false);
        $this->as($this->cashier)->getJson('/api/v1/shifts/current')->assertJsonPath('requires_shift', false);

        $this->openShift($this->store);
        $this->as($this->cashier)->getJson('/api/v1/shifts/current')->assertJsonPath('has_active', true);
    }

    // ---------------------------------------------------------------- setting

    private function url(Store $store): string
    {
        return '/api/v1/stores/'.$store->id.'/pos-settings';
    }

    public function test_setting_defaults_to_on_and_can_be_switched_off_and_on(): void
    {
        $this->as($this->admin)->getJson($this->url($this->store))->assertOk()->assertJsonPath('data.require_shift_for_sales', true);

        $this->as($this->admin)->putJson($this->url($this->store), ['require_shift_for_sales' => false])
            ->assertOk()->assertJsonPath('data.require_shift_for_sales', false);
        $this->assertFalse((bool) StorePosSetting::forStore((int) $this->store->id)->require_shift_for_sales);

        $this->as($this->admin)->putJson($this->url($this->store), ['require_shift_for_sales' => true])
            ->assertOk()->assertJsonPath('data.require_shift_for_sales', true);
    }

    public function test_setting_rejects_a_non_boolean(): void
    {
        $this->as($this->admin)->putJson($this->url($this->store), ['require_shift_for_sales' => 'maybe'])
            ->assertStatus(422)->assertJsonValidationErrors(['require_shift_for_sales']);
    }

    private function branchManager(string $phone, string $permission): User
    {
        $manager = $this->makeUser($phone, 'cashier');
        $manager->givePermissionTo(Permission::findOrCreate($permission, 'web'));

        return $manager;
    }

    private function shiftRuleUrl(Store $store): string
    {
        return $this->url($store).'/shift-rule';
    }

    public function test_stores_manage_toggles_only_the_shift_rule_through_the_dedicated_endpoint(): void
    {
        $manager = $this->branchManager('01000000023', 'stores.manage');

        $this->as($manager)->getJson($this->shiftRuleUrl($this->store))
            ->assertOk()->assertJsonPath('data.require_shift_for_sales', true);
        $this->as($manager)->putJson($this->shiftRuleUrl($this->store), ['require_shift_for_sales' => false, 'max_discount_percent' => '99'])
            ->assertOk()->assertJsonPath('data.require_shift_for_sales', false);

        $setting = StorePosSetting::forStore((int) $this->store->id);
        $this->assertFalse((bool) $setting->require_shift_for_sales);
        $this->assertNull($setting->max_discount_percent, 'extra keys must not reach other settings');

        $this->as($manager)->putJson($this->shiftRuleUrl($this->store), ['require_shift_for_sales' => 'maybe'])
            ->assertStatus(422)->assertJsonValidationErrors(['require_shift_for_sales']);
    }

    public function test_stores_manage_cannot_use_the_general_pos_settings_endpoints(): void
    {
        $manager = $this->branchManager('01000000024', 'stores.manage');

        $this->as($manager)->putJson($this->url($this->store), ['max_discount_percent' => '50'])->assertStatus(403);
        $this->as($manager)->putJson($this->url($this->store), ['require_shift_for_sales' => false])->assertStatus(403);
        $this->as($manager)->getJson($this->url($this->store))->assertStatus(403);
        $this->as($manager)->putJson('/api/v1/stores/'.$this->store->id.'/pos/quick-keys', ['keys' => []])->assertStatus(403);
        $this->assertSame(0, StorePosSetting::query()->count());
    }

    public function test_settings_manage_can_use_the_shift_rule_endpoint_and_a_cashier_cannot(): void
    {
        $settingsManager = $this->branchManager('01000000025', 'settings.manage');
        $settingsManager->update(['default_store_id' => $this->store->id]);

        $this->as($settingsManager)->putJson($this->shiftRuleUrl($this->store), ['require_shift_for_sales' => false])->assertOk();
        $this->as($this->cashier)->putJson($this->shiftRuleUrl($this->store), ['require_shift_for_sales' => true])->assertStatus(403);
        $this->as($this->cashier)->getJson($this->shiftRuleUrl($this->store))->assertStatus(403);
        $this->flushHeaders();
        $this->withHeaders(['X-Tenant' => (string) $this->tenant->getTenantKey(), 'Accept' => 'application/json'])
            ->putJson($this->shiftRuleUrl($this->store), ['require_shift_for_sales' => true])->assertStatus(401);
    }

    public function test_cashier_cannot_edit_the_setting_and_guests_are_rejected(): void
    {
        $this->as($this->cashier)->putJson($this->url($this->store), ['require_shift_for_sales' => false])->assertStatus(403);
        $this->assertTrue((bool) StorePosSetting::forStore((int) $this->store->id)->require_shift_for_sales);

        $this->flushHeaders();
        $this->withHeaders(['X-Tenant' => (string) $this->tenant->getTenantKey(), 'Accept' => 'application/json'])
            ->putJson($this->url($this->store), ['require_shift_for_sales' => false])->assertStatus(401);
        $this->withHeaders(['X-Tenant' => (string) $this->tenant->getTenantKey(), 'Accept' => 'application/json'])
            ->postJson('/api/v1/pos/checkout', [])->assertStatus(401);
    }

    public function test_the_setting_is_isolated_between_tenants(): void
    {
        $this->setRequired($this->store, false);

        $other = $this->createTenant();
        $this->inTenant($other, function (): void {
            $store = Store::query()->firstOrFail();
            $this->assertTrue((bool) StorePosSetting::forStore((int) $store->id)->require_shift_for_sales);
        });
    }
}
