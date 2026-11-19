<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Customers\CustomerCreditLimitGuard;
use App\Services\InvoiceService;
use App\Services\Settings\TenantSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * SHOP-03: a customer's credit limit (customers.credit_limit, NULL = no limit).
 *
 *  - the limit is set on the customer form (create / update) and a new customer without one gets the shop
 *    default (customers_default_credit_limit), never retroactively;
 *  - a credit / partial sale (POS checkout, POST /invoices, invoice edit) that takes the balance above the
 *    limit is refused with 422 `customer.credit_limit_exceeded` + an `override` hint, unless the seller holds
 *    `customers.override_credit_limit` or a manager approves with their PIN (activity-logged);
 *  - a cash sale paid in full never checks the limit.
 *
 * Fixture: ITEM price 100.000, no floor; the customer starts with a balance of 0.
 */
final class CustomerCreditLimitApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private User $cashier;

    private User $creditManager;

    private User $floorManager;

    private User $admin;

    private Customer $customer;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);
        $this->store = $this->adoptMainStore(['name' => 'Main', 'code' => 'MAIN-CL', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);

        $values = ['max_discount_percent' => '10.000'];
        if (Schema::hasColumn('store_pos_settings', 'require_shift_for_sales')) {
            $values['require_shift_for_sales'] = false;
        }
        StorePosSetting::query()->updateOrCreate(['store_id' => $this->store->id], $values);

        $this->cashier = $this->makeUser('01000000031', 'cashier');
        $this->creditManager = $this->makeUser('01000000032', 'cashier');
        $this->creditManager->givePermissionTo(Permission::findOrCreate(CustomerCreditLimitGuard::PERMISSION, 'web'));
        $this->creditManager->forceFill(['pos_pin' => Hash::make('4321')])->save();
        // Holds another approval permission only: must never approve a credit-limit breach.
        $this->floorManager = $this->makeUser('01000000033', 'cashier');
        $this->floorManager->givePermissionTo(Permission::findOrCreate('pos.price.below_min', 'web'));
        $this->floorManager->forceFill(['pos_pin' => Hash::make('8765')])->save();
        $this->admin = $this->makeUser('01000000034', 'admin');

        $this->customer = Customer::create(['name' => 'Credit', 'phone' => '01100000031', 'current_balance' => '0.000', 'credit_limit' => '250.000', 'price_tier' => 'retail', 'is_active' => true]);

        $this->item = Item::create([
            'name' => 'CL-1', 'code' => 'CL-1', 'unit' => 'كجم', 'cost_price' => '50.000', 'selling_price' => '100.000',
            'min_selling_price' => '0.000', 'current_stock' => '1000.000', 'min_stock_level' => '1.000', 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $this->store->id, 'item_id' => $this->item->id, 'quantity' => '1000.000']);
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

    /** @return array<string, string> */
    private function headers(?User $as): array
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return array_filter([
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => $as instanceof User ? 'Bearer '.$as->createToken('t')->plainTextToken : null,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function sell(User $as, string $quantity, array $extra = [], string $url = '/api/v1/pos/checkout'): TestResponse
    {
        return $this->withHeaders($this->headers($as))->postJson($url, array_merge([
            'customer_id' => $this->customer->id,
            'payment_type' => 'credit',
            'payment_method' => 'cash',
            'items' => [['item_id' => $this->item->id, 'quantity' => $quantity]],
        ], $extra));
    }

    private function balance(): string
    {
        return (string) Customer::query()->findOrFail($this->customer->id)->current_balance;
    }

    private function assertRefused(TestResponse $response, int $invoicesBefore = 0): void
    {
        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'customer.credit_limit_exceeded')
            ->assertJsonValidationErrors(['customer_id', 'override']);
        $this->assertSame($invoicesBefore, Invoice::count());
    }

    // ------------------------------------------------------------- enforcement

    public function test_a_credit_sale_exactly_at_the_limit_passes_and_one_milli_above_is_refused(): void
    {
        $this->sell($this->cashier, '2.500')->assertCreated();
        $this->assertSame('250.000', $this->balance());

        $this->assertRefused($this->sell($this->cashier, '0.001'), 1);
        $this->assertSame('250.000', $this->balance());
    }

    public function test_a_single_sale_above_the_limit_rolls_back_entirely(): void
    {
        $this->assertRefused($this->sell($this->cashier, '2.501'));
        $this->assertSame('0.000', $this->balance());
        $this->assertSame('1000.000', (string) StoreStock::query()->where('item_id', $this->item->id)->value('quantity'));
    }

    public function test_the_message_names_the_limit_the_balance_and_the_new_credit(): void
    {
        $this->sell($this->cashier, '2.000')->assertCreated();

        $response = $this->sell($this->cashier, '1.000');
        $this->assertRefused($response, 1);
        $message = (string) $response->json('errors.customer_id.0');
        $this->assertStringContainsString('250', $message);
        $this->assertStringContainsString('200', $message);
        $this->assertStringContainsString('100', $message);
    }

    public function test_a_customer_without_a_limit_is_never_refused(): void
    {
        $this->customer->update(['credit_limit' => null]);

        $this->sell($this->cashier, '50.000')->assertCreated();
        $this->assertSame('5000.000', $this->balance());
    }

    public function test_a_cash_sale_paid_in_full_never_checks_the_limit(): void
    {
        Customer::query()->whereKey($this->customer->id)->update(['credit_limit' => '0.000']);

        $this->sell($this->cashier, '10.000', ['payment_type' => 'cash'])->assertCreated();
        $this->assertSame('0.000', $this->balance());
    }

    public function test_a_partial_sale_only_counts_the_unpaid_part(): void
    {
        // 500 sold, 260 paid now: 240 on credit, within 250.
        $this->sell($this->cashier, '5.000', ['payment_type' => 'partial', 'paid_amount' => '260.000'])->assertCreated();
        $this->assertSame('240.000', $this->balance());

        // 500 sold, 240 paid: 260 on credit on a balance of 240 -> refused.
        $this->assertRefused($this->sell($this->cashier, '5.000', ['payment_type' => 'partial', 'paid_amount' => '240.000']), 1);
    }

    public function test_a_customer_already_over_the_limit_can_still_pay_cash(): void
    {
        $this->sell($this->admin, '5.000')->assertCreated();
        $this->assertSame('500.000', $this->balance());

        $this->sell($this->cashier, '1.000', ['payment_type' => 'cash'])->assertCreated();
        $this->assertRefused($this->sell($this->cashier, '0.001'), 2);
    }

    public function test_the_same_limit_applies_to_the_generic_invoices_endpoint(): void
    {
        $this->assertRefused($this->sell($this->cashier, '3.000', ['invoice_date' => now()->toDateString()], '/api/v1/invoices'));
    }

    // ------------------------------------------------------------- approvals

    public function test_a_user_with_the_override_permission_passes_and_the_breach_is_activity_logged(): void
    {
        $this->sell($this->creditManager, '3.000')->assertCreated();
        $this->assertSame('300.000', $this->balance());

        $log = ActivityLog::query()->where('action', CustomerCreditLimitGuard::ACTIVITY_ACTION)->latest('id')->firstOrFail();
        $this->assertSame('customers', $log->module);
        $this->assertSame($this->creditManager->id, (int) $log->properties['override_approved_by']);
        $this->assertSame('250.000', $log->properties['credit_limit']);
        $this->assertSame('300.000', $log->properties['balance_after']);
    }

    public function test_admin_may_sell_above_the_limit(): void
    {
        $this->sell($this->admin, '4.000')->assertCreated();
        $this->assertSame('400.000', $this->balance());
    }

    public function test_a_sale_within_the_limit_writes_no_override_log(): void
    {
        $this->sell($this->creditManager, '1.000')->assertCreated();
        $this->assertSame(0, ActivityLog::query()->where('action', CustomerCreditLimitGuard::ACTIVITY_ACTION)->count());
    }

    public function test_a_valid_manager_pin_approves_a_sale_above_the_limit(): void
    {
        $this->sell($this->cashier, '3.000', ['override' => ['user_id' => $this->creditManager->id, 'pin' => '4321']])->assertCreated();

        $invoice = Invoice::query()->latest('id')->firstOrFail();
        $this->assertSame($this->cashier->id, (int) $invoice->user_id);
        $log = ActivityLog::query()->where('action', CustomerCreditLimitGuard::ACTIVITY_ACTION)->latest('id')->firstOrFail();
        $this->assertSame($this->creditManager->id, (int) $log->properties['override_approved_by']);
        $this->assertSame((int) $invoice->id, (int) $log->properties['invoice_id']);
    }

    public function test_a_wrong_pin_or_a_manager_without_the_credit_permission_is_rejected(): void
    {
        $this->sell($this->cashier, '3.000', ['override' => ['user_id' => $this->creditManager->id, 'pin' => '1111']])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);

        $this->sell($this->cashier, '3.000', ['override' => ['user_id' => $this->floorManager->id, 'pin' => '8765']])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);

        $this->assertSame(0, Invoice::count());
    }

    public function test_an_unneeded_pin_is_not_checked(): void
    {
        // Well within the limit: the (wrong) PIN is never tested.
        $this->sell($this->cashier, '1.000', ['override' => ['user_id' => $this->creditManager->id, 'pin' => '0000']])->assertCreated();
    }

    // ------------------------------------------------------------- invoice edit

    public function test_editing_an_invoice_above_the_limit_is_refused_and_editing_it_down_is_allowed(): void
    {
        $this->actingAs($this->cashier);
        $invoice = app(InvoiceService::class)->confirmInvoice([
            'customer_id' => $this->customer->id, 'store_id' => $this->store->id,
            'payment_type' => 'credit', 'payment_method' => 'cash',
            'items' => [['item_id' => $this->item->id, 'quantity' => '2.000']],
        ]);
        $this->assertSame('200.000', $this->balance());

        $edit = fn (string $quantity) => app(InvoiceService::class)->updateInvoice($invoice, [
            'customer_id' => $this->customer->id, 'payment_type' => 'credit', 'payment_method' => 'cash',
            'items' => [['item_id' => $this->item->id, 'quantity' => $quantity]],
        ]);

        // The old 200 is not counted twice: 250 is still within.
        $edit('2.500');
        $this->assertSame('250.000', $this->balance());

        try {
            $edit('2.600');
            $this->fail('An edit above the credit limit must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer_id', $e->errors());
        }
        $this->assertSame('250.000', $this->balance());

        // Lower the limit below the balance: editing the invoice down still works.
        $this->customer->update(['credit_limit' => '100.000']);
        $edit('2.000');
        $this->assertSame('200.000', $this->balance());
    }

    // ------------------------------------------------------------- the customer form

    public function test_the_limit_is_saved_on_create_and_update_and_returned_by_the_api(): void
    {
        $created = $this->withHeaders($this->headers($this->admin))->postJson('/api/v1/customers', ['name' => 'A', 'credit_limit' => '1500.5'])
            ->assertCreated();
        $id = (int) $created->json('data.id');
        $this->assertSame('1500.500', (string) Customer::query()->findOrFail($id)->credit_limit);

        $this->withHeaders($this->headers($this->admin))->getJson('/api/v1/customers/'.$id)
            ->assertOk()->assertJsonPath('data.credit_limit', '1500.500');

        $this->withHeaders($this->headers($this->admin))->putJson('/api/v1/customers/'.$id, ['name' => 'A', 'credit_limit' => '700'])->assertOk();
        $this->assertSame('700.000', (string) Customer::query()->findOrFail($id)->credit_limit);

        // Omitted: kept (older clients). Null: no limit.
        $this->withHeaders($this->headers($this->admin))->putJson('/api/v1/customers/'.$id, ['name' => 'A2'])->assertOk();
        $this->assertSame('700.000', (string) Customer::query()->findOrFail($id)->credit_limit);
        $this->withHeaders($this->headers($this->admin))->putJson('/api/v1/customers/'.$id, ['name' => 'A2', 'credit_limit' => null])->assertOk();
        $this->assertNull(Customer::query()->findOrFail($id)->credit_limit);
        $this->withHeaders($this->headers($this->admin))->getJson('/api/v1/customers/'.$id)->assertJsonPath('data.credit_limit', null);
    }

    public function test_an_invalid_limit_is_rejected(): void
    {
        foreach (['-1', 'abc', '1.0001'] as $bad) {
            $this->withHeaders($this->headers($this->admin))->postJson('/api/v1/customers', ['name' => 'Bad', 'credit_limit' => $bad])
                ->assertStatus(422)->assertJsonValidationErrors(['credit_limit']);
            $this->withHeaders($this->headers($this->admin))->putJson('/api/v1/customers/'.$this->customer->id, ['name' => 'Credit', 'credit_limit' => $bad])
                ->assertStatus(422)->assertJsonValidationErrors(['credit_limit']);
        }
        $this->assertSame('250.000', (string) $this->customer->fresh()->credit_limit);
    }

    public function test_a_new_customer_without_a_limit_gets_the_shop_default_and_changing_it_is_not_retroactive(): void
    {
        Setting::set(TenantSettings::KEY_CUSTOMERS_DEFAULT_CREDIT_LIMIT, '1000');

        $id = (int) $this->withHeaders($this->headers($this->admin))->postJson('/api/v1/customers', ['name' => 'Default'])->assertCreated()->json('data.id');
        $this->assertSame('1000.000', (string) Customer::query()->findOrFail($id)->credit_limit);

        // Sent explicitly empty: no limit, the default is not forced.
        $none = (int) $this->withHeaders($this->headers($this->admin))->postJson('/api/v1/customers', ['name' => 'None', 'credit_limit' => ''])->assertCreated()->json('data.id');
        $this->assertNull(Customer::query()->findOrFail($none)->credit_limit);

        // POS quick-add uses the default too.
        $quick = (int) $this->withHeaders($this->headers($this->cashier))->postJson('/api/v1/pos/quick-customer', ['name' => 'Quick', 'phone' => '01100000039'])
            ->assertCreated()->assertJsonPath('customer.credit_limit', '1000.000')->json('customer.id');
        $this->assertSame('1000.000', (string) Customer::query()->findOrFail($quick)->credit_limit);

        $this->withHeaders($this->headers($this->admin))->postJson('/api/v1/settings', ['company_name' => 'Shop', TenantSettings::KEY_CUSTOMERS_DEFAULT_CREDIT_LIMIT => '50'])->assertOk();
        // Setting::clearCache() forgets after commit; the test transaction never commits.
        Cache::flush();
        $this->assertSame('50.000', app(TenantSettings::class)->customersDefaultCreditLimit());
        $this->assertSame('1000.000', (string) Customer::query()->findOrFail($id)->credit_limit);
        $this->assertSame('250.000', (string) $this->customer->fresh()->credit_limit);

        $this->withHeaders($this->headers($this->admin))->getJson('/api/v1/system/context')
            ->assertOk()->assertJsonPath('data.system.customers_default_credit_limit', '50.000');

        $this->withHeaders($this->headers($this->admin))->postJson('/api/v1/settings', ['company_name' => 'Shop', TenantSettings::KEY_CUSTOMERS_DEFAULT_CREDIT_LIMIT => null])->assertOk();
        Cache::flush();
        $this->assertNull(app(TenantSettings::class)->customersDefaultCreditLimit());

        $this->withHeaders($this->headers($this->admin))->postJson('/api/v1/settings', ['company_name' => 'Shop', TenantSettings::KEY_CUSTOMERS_DEFAULT_CREDIT_LIMIT => '-5'])
            ->assertStatus(422)->assertJsonValidationErrors([TenantSettings::KEY_CUSTOMERS_DEFAULT_CREDIT_LIMIT]);
    }

    // ------------------------------------------------------------- 401 / 403 / isolation

    public function test_editing_the_limit_needs_authentication_and_customers_manage(): void
    {
        $this->withHeaders($this->headers(null))->putJson('/api/v1/customers/'.$this->customer->id, ['name' => 'Credit', 'credit_limit' => '9999'])
            ->assertStatus(401);

        $nobody = $this->makeUser('01000000035', 'storekeeper');
        $this->withHeaders($this->headers($nobody))->putJson('/api/v1/customers/'.$this->customer->id, ['name' => 'Credit', 'credit_limit' => '9999'])
            ->assertStatus(403);

        $this->assertSame('250.000', (string) $this->customer->fresh()->credit_limit);
    }

    public function test_the_limit_of_another_tenants_customer_cannot_be_read_or_changed(): void
    {
        $other = $this->createTenant();
        $foreignId = $this->inTenant($other, fn (): int => (int) Customer::create(['name' => 'Foreign', 'current_balance' => '0.000', 'credit_limit' => '10.000', 'is_active' => true])->id);
        $this->useTenantForTest($this->tenant);

        $this->withHeaders($this->headers($this->admin))->getJson('/api/v1/customers/'.$foreignId)->assertNotFound();
        $this->withHeaders($this->headers($this->admin))->putJson('/api/v1/customers/'.$foreignId, ['name' => 'X', 'credit_limit' => '99999'])->assertNotFound();

        $this->assertSame('10.000', $this->inTenant($other, fn (): string => (string) Customer::query()->findOrFail($foreignId)->credit_limit));
    }
}
