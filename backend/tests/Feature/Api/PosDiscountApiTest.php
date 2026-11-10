<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * POS-07: discounts as a percent or an amount, on a line and/or on the invoice, with a per-store cap
 * (`max_discount_percent`, default 10) that a cashier cannot exceed without `pos.discount.override` or a
 * manager PIN. The BE-08 price floor still applies after the discounts.
 *
 * Fixtures: PLAIN = price 100.000, no floor. FLOORED = price 100.000, floor 70.000.
 */
final class PosDiscountApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private User $cashier;

    private User $discountManager;

    private User $floorManager;

    private User $admin;

    private Customer $customer;

    private Item $plain;

    private Item $floored;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);
        $this->store = $this->adoptMainStore(['name' => 'Main', 'code' => 'MAIN-DS', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);

        $this->cashier = $this->makeUser('01000000021', 'cashier');
        $this->discountManager = $this->makeUser('01000000022', 'cashier');
        $this->discountManager->givePermissionTo(Permission::findOrCreate('pos.discount.override', 'web'));
        $this->discountManager->forceFill(['pos_pin' => Hash::make('4321')])->save();
        // Holds the OTHER approval permission only: must never approve a discount.
        $this->floorManager = $this->makeUser('01000000023', 'cashier');
        $this->floorManager->givePermissionTo(Permission::findOrCreate('pos.price.below_min', 'web'));
        $this->floorManager->forceFill(['pos_pin' => Hash::make('8765')])->save();
        $this->admin = $this->makeUser('01000000024', 'admin');

        $this->customer = Customer::create(['name' => 'Retail', 'phone' => '01100000021', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);
        $this->plain = $this->makeItem('DS-1', ['min_selling_price' => '0.000']);
        $this->floored = $this->makeItem('DS-2', ['min_selling_price' => '70.000']);

        $this->setStoreSettings(['max_discount_percent' => null]);
    }

    private function makeUser(string $phone, string $role): User
    {
        $user = User::factory()->create([
            'name' => $role.$phone,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    /** @param array<string, mixed> $attrs */
    private function makeItem(string $code, array $attrs): Item
    {
        $item = Item::create(array_merge([
            'name' => $code,
            'code' => $code,
            'unit' => 'كجم',
            'cost_price' => '50.000',
            'selling_price' => '100.000',
            'current_stock' => '100.000',
            'min_stock_level' => '1.000',
            'is_active' => true,
        ], $attrs));
        StoreStock::create(['store_id' => $this->store->id, 'item_id' => $item->id, 'quantity' => '100.000']);

        return $item;
    }

    /** @param array<string, mixed> $values */
    private function setStoreSettings(array $values): void
    {
        // POS-10 may require an open shift to sell; these tests are about discounts, not shifts.
        if (Schema::hasColumn('store_pos_settings', 'require_shift_for_sales')) {
            $values['require_shift_for_sales'] = false;
        }

        StorePosSetting::query()->updateOrCreate(['store_id' => $this->store->id], $values);
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $extra
     */
    private function checkout(User $as, array $line = [], array $extra = [], string $url = '/api/v1/pos/checkout'): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $line = array_merge(['item_id' => $this->plain->id, 'quantity' => '1.000'], $line);

        return $this->withHeaders([
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$as->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ])->postJson($url, array_merge([
            'customer_id' => $this->customer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [$line],
        ], $extra));
    }

    private function lastInvoice(): Invoice
    {
        return Invoice::query()->latest('id')->firstOrFail();
    }

    private function assertCapRejected(TestResponse $response, int $invoicesBefore = 0): void
    {
        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'pos.discount_cap_exceeded')
            ->assertJsonValidationErrors(['discount', 'override']);
        $this->assertSame($invoicesBefore, Invoice::count());
        $this->assertSame(bcsub('100.000', (string) $invoicesBefore, 3), (string) StoreStock::where('store_id', $this->store->id)->where('item_id', $this->plain->id)->value('quantity'));
    }

    // ------------------------------------------------------------- the cap

    public function test_cashier_is_held_to_the_default_cap_of_ten_percent(): void
    {
        $this->checkout($this->cashier, [], ['discount_type' => 'percentage', 'discount_value' => '10'])->assertCreated();
        $this->assertSame('10.000', (string) $this->lastInvoice()->discount_amount);
        $this->assertNull($this->lastInvoice()->discount_override_by);
    }

    public function test_cashier_over_the_cap_gets_422_with_error_code_and_override_hint(): void
    {
        $response = $this->checkout($this->cashier, [], ['discount_type' => 'percentage', 'discount_value' => '10.001']);

        $this->assertCapRejected($response);
        $this->assertStringContainsString('10', (string) $response->json('errors.discount.0'));
    }

    public function test_an_amount_over_the_cap_is_rejected_and_the_exact_cap_amount_passes(): void
    {
        $this->assertCapRejected($this->checkout($this->cashier, [], ['discount_type' => 'fixed', 'discount_value' => '10.001']));

        $this->checkout($this->cashier, [], ['discount_type' => 'fixed', 'discount_value' => '10.000'])->assertCreated();
    }

    public function test_percent_and_amount_are_equivalent(): void
    {
        $this->checkout($this->cashier, [], ['discount_type' => 'percentage', 'discount_value' => '7.5'])->assertCreated();
        $byPercent = $this->lastInvoice();

        $this->checkout($this->cashier, [], ['discount_type' => 'fixed', 'discount_value' => '7.5'])->assertCreated();
        $byAmount = $this->lastInvoice();

        $this->assertNotSame($byPercent->id, $byAmount->id);
        $this->assertSame('7.500', (string) $byPercent->discount_amount);
        $this->assertSame((string) $byPercent->discount_amount, (string) $byAmount->discount_amount);
        $this->assertSame((string) $byPercent->net_total, (string) $byAmount->net_total);
        $this->assertSame('92.500', (string) $byAmount->net_total);
    }

    public function test_line_percent_and_line_amount_are_equivalent_and_stored_as_money(): void
    {
        $this->checkout($this->cashier, ['quantity' => '2.000', 'discount_percent' => '5'])->assertCreated();
        $percentLine = InvoiceItem::query()->latest('id')->firstOrFail();

        $this->checkout($this->cashier, ['quantity' => '2.000', 'discount' => '10.000'])->assertCreated();
        $amountLine = InvoiceItem::query()->latest('id')->firstOrFail();

        $this->assertSame('10.000', (string) $percentLine->discount_amount);
        $this->assertSame((string) $percentLine->discount_amount, (string) $amountLine->discount_amount);
        $this->assertSame('190.000', (string) $percentLine->total_price);
        $this->assertSame('190.000', (string) $amountLine->total_price);
    }

    public function test_line_percent_is_taken_from_the_server_price_when_none_is_sent(): void
    {
        $this->checkout($this->cashier, ['discount_percent' => '8'])->assertCreated();

        $this->assertSame('8.000', (string) InvoiceItem::query()->latest('id')->firstOrFail()->discount_amount);
    }

    public function test_line_and_invoice_discounts_are_added_together_against_the_cap(): void
    {
        // 6 on the line, then 5 % of the remaining 94 = 4.70: 10.70 of 100 gross > 10 %.
        $this->assertCapRejected($this->checkout($this->cashier, ['discount' => '6.000'], ['discount_type' => 'percentage', 'discount_value' => '5']));

        // 5 on the line + 5 % of 95 = 4.75: 9.75 of 100 gross.
        $this->checkout($this->cashier, ['discount' => '5.000'], ['discount_type' => 'percentage', 'discount_value' => '5'])->assertCreated();
        $invoice = $this->lastInvoice();
        $this->assertSame('4.750', (string) $invoice->discount_amount);
        $this->assertSame('90.250', (string) $invoice->net_total);
    }

    public function test_the_cap_is_measured_on_the_whole_invoice_not_per_line(): void
    {
        // Line A (100) discounted 15 and line B (100) not: 15 of 200 gross = 7.5 %.
        $this->checkout($this->cashier, [], [
            'items' => [
                ['item_id' => $this->plain->id, 'quantity' => '1.000', 'discount' => '15.000'],
                ['item_id' => $this->plain->id, 'quantity' => '1.000'],
            ],
        ])->assertCreated();
    }

    public function test_the_cap_comes_from_the_store_setting(): void
    {
        $this->setStoreSettings(['max_discount_percent' => '20.000']);
        $this->checkout($this->cashier, [], ['discount_type' => 'percentage', 'discount_value' => '15'])->assertCreated();

        $this->setStoreSettings(['max_discount_percent' => '5.000']);
        $this->assertCapRejected($this->checkout($this->cashier, [], ['discount_type' => 'percentage', 'discount_value' => '5.5']), 1);
    }

    public function test_a_zero_cap_blocks_every_discount_and_one_hundred_allows_everything(): void
    {
        $this->setStoreSettings(['max_discount_percent' => '0.000']);
        $this->assertCapRejected($this->checkout($this->cashier, [], ['discount_type' => 'fixed', 'discount_value' => '0.001']));
        $this->checkout($this->cashier)->assertCreated();

        $this->setStoreSettings(['max_discount_percent' => '100.000']);
        $this->checkout($this->cashier, [], ['discount_type' => 'percentage', 'discount_value' => '100'])->assertCreated();
        $this->assertSame('0.000', (string) $this->lastInvoice()->net_total);
    }

    public function test_the_same_cap_applies_to_the_generic_invoices_endpoint(): void
    {
        $generic = fn (array $extra) => $this->checkout($this->cashier, [], $extra + ['invoice_date' => now()->toDateString()], '/api/v1/invoices');

        $generic(['discount_type' => 'percentage', 'discount_value' => '12'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'pos.discount_cap_exceeded');
        $this->assertSame(0, Invoice::count());
    }

    // --------------------------------------------------------- approvals

    public function test_a_user_with_the_override_permission_may_exceed_the_cap_and_is_recorded(): void
    {
        $this->checkout($this->discountManager, [], ['discount_type' => 'percentage', 'discount_value' => '30'])->assertCreated();

        $invoice = $this->lastInvoice();
        $this->assertSame('30.000', (string) $invoice->discount_amount);
        $this->assertSame($this->discountManager->id, (int) $invoice->discount_override_by);
    }

    public function test_admin_may_exceed_the_cap(): void
    {
        $this->checkout($this->admin, ['discount_percent' => '50'])->assertCreated();

        $this->assertSame('50.000', (string) InvoiceItem::query()->latest('id')->firstOrFail()->discount_amount);
        $this->assertSame($this->admin->id, (int) $this->lastInvoice()->discount_override_by);
    }

    public function test_a_valid_manager_pin_approves_an_over_cap_discount(): void
    {
        $this->checkout($this->cashier, [], [
            'discount_type' => 'percentage',
            'discount_value' => '25',
            'override' => ['user_id' => $this->discountManager->id, 'pin' => '4321'],
        ])->assertCreated();

        $invoice = $this->lastInvoice();
        $this->assertSame($this->discountManager->id, (int) $invoice->discount_override_by);
        $this->assertSame($this->cashier->id, (int) $invoice->user_id);
    }

    public function test_an_unneeded_pin_is_ignored_and_records_no_approver(): void
    {
        $this->checkout($this->cashier, [], [
            'discount_type' => 'percentage',
            'discount_value' => '5',
            'override' => ['user_id' => $this->discountManager->id, 'pin' => '0000'],
        ])->assertCreated();

        $this->assertNull($this->lastInvoice()->discount_override_by);
    }

    public function test_a_wrong_pin_or_an_approver_without_the_discount_permission_is_rejected(): void
    {
        $over = ['discount_type' => 'percentage', 'discount_value' => '25'];

        $this->checkout($this->cashier, [], $over + ['override' => ['user_id' => $this->discountManager->id, 'pin' => '1111']])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);

        // A correct PIN of a manager who may only sell below the floor cannot approve a discount.
        $this->checkout($this->cashier, [], $over + ['override' => ['user_id' => $this->floorManager->id, 'pin' => '8765']])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);

        $this->assertSame(0, Invoice::count());
    }

    public function test_one_pin_covers_both_a_discount_and_a_below_floor_price_when_the_manager_holds_both(): void
    {
        $this->discountManager->givePermissionTo(Permission::findOrCreate('pos.price.below_min', 'web'));

        $this->checkout($this->cashier, ['item_id' => $this->floored->id, 'unit_price' => '60.000'], [
            'discount_type' => 'percentage',
            'discount_value' => '20',
            'override' => ['user_id' => $this->discountManager->id, 'pin' => '4321'],
        ])->assertCreated();

        $this->assertSame($this->discountManager->id, (int) $this->lastInvoice()->discount_override_by);
        $this->assertSame($this->discountManager->id, (int) InvoiceItem::query()->latest('id')->firstOrFail()->override_approved_by);
    }

    public function test_the_below_floor_permission_does_not_lift_the_discount_cap(): void
    {
        $this->assertCapRejected($this->checkout($this->floorManager, [], ['discount_type' => 'percentage', 'discount_value' => '25']));
    }

    // ------------------------------------------------------- floor rule

    public function test_the_floor_still_applies_after_a_line_percent_discount(): void
    {
        // 100 - 10 % = 90 >= floor 70: fine within the cap.
        $this->checkout($this->cashier, ['item_id' => $this->floored->id, 'discount_percent' => '10'])->assertCreated();

        // Priced at 72, a 10 % line discount nets 64.80 < 70: needs the floor approval, not the discount one.
        $response = $this->checkout($this->cashier, ['item_id' => $this->floored->id, 'unit_price' => '72.000', 'discount_percent' => '10']);
        $response->assertStatus(422)->assertJsonValidationErrors(['items.0.unit_price', 'override']);
        $this->assertStringNotContainsString('70', (string) $response->getContent());
        $this->assertSame(1, Invoice::count());
    }

    public function test_the_floor_still_applies_after_the_invoice_discount(): void
    {
        // 75 is above the floor, but 75 - 10 % = 67.50 is not.
        $line = ['item_id' => $this->floored->id, 'unit_price' => '75.000'];
        $discount = ['discount_type' => 'percentage', 'discount_value' => '10'];

        $this->checkout($this->cashier, $line, $discount)
            ->assertStatus(422)->assertJsonValidationErrors(['items.0.unit_price', 'override']);
        $this->assertSame(0, Invoice::count());

        // The floor permission alone (inside the cap) is enough.
        $this->checkout($this->floorManager, $line, $discount)->assertCreated();
        // The floor approver is audited on the invoice, since no line carries it.
        $this->assertSame($this->floorManager->id, (int) $this->lastInvoice()->discount_override_by);
    }

    public function test_a_line_discount_above_the_line_total_is_still_rejected_for_everyone(): void
    {
        $this->checkout($this->admin, ['discount' => '100.001'])->assertStatus(422)->assertJsonValidationErrors(['items.0.discount']);
        $this->assertSame(0, Invoice::count());
    }

    // -------------------------------------------------------- validation

    public function test_line_percent_must_be_between_zero_and_one_hundred(): void
    {
        $this->checkout($this->admin, ['discount_percent' => '100.001'])->assertStatus(422)->assertJsonValidationErrors(['items.0.discount_percent']);
        $this->checkout($this->admin, ['discount_percent' => '-1'])->assertStatus(422)->assertJsonValidationErrors(['items.0.discount_percent']);
        $this->checkout($this->admin, ['discount_percent' => '1.2345'])->assertStatus(422)->assertJsonValidationErrors(['items.0.discount_percent']);
    }

    public function test_checkout_still_requires_authentication(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->withHeaders(['X-Tenant' => (string) $this->tenant->getTenantKey(), 'Accept' => 'application/json'])
            ->postJson('/api/v1/pos/checkout', [])
            ->assertStatus(401);
    }
}
