<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\DiscountCapExceededException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\RateLimitKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * BE-08 / POS-07 on invoice UPDATE: InvoiceService::updateInvoice() applies the same price floor,
 * tier list price, discount cap and manager-PIN override as confirmInvoice(). (There is no HTTP update
 * route today; the service is the single entry point.)
 *
 * Fixture item: retail 100.000, wholesale 80.000, floor 70.000, cost 50.000.
 */
final class InvoiceUpdatePriceGuardsTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private User $cashier;

    private User $manager;

    private User $discountManager;

    private Customer $retail;

    private Customer $wholesale;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);
        $this->store = $this->adoptMainStore(['name' => 'Main', 'code' => 'MAIN-UP', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);

        $this->cashier = $this->makeUser('01000000021', 'cashier');
        $this->manager = $this->makeUser('01000000022', 'cashier');
        $this->manager->givePermissionTo(Permission::findOrCreate('pos.price.below_min', 'web'));
        $this->manager->forceFill(['pos_pin' => Hash::make('4321')])->save();
        $this->discountManager = $this->makeUser('01000000023', 'cashier');
        $this->discountManager->givePermissionTo(Permission::findOrCreate('pos.discount.override', 'web'));
        $this->discountManager->forceFill(['pos_pin' => Hash::make('4321')])->save();

        $this->retail = Customer::create(['name' => 'Retail', 'phone' => '01100000011', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);
        $this->wholesale = Customer::create(['name' => 'Wholesale', 'phone' => '01100000012', 'current_balance' => '0.000', 'price_tier' => 'wholesale', 'is_active' => true]);

        $this->item = Item::create([
            'name' => 'UP-1', 'code' => 'UP-1', 'unit' => 'كجم', 'cost_price' => '50.000', 'selling_price' => '100.000',
            'wholesale_price' => '80.000', 'min_selling_price' => '70.000', 'current_stock' => '100.000',
            'min_stock_level' => '1.000', 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $this->store->id, 'item_id' => $this->item->id, 'quantity' => '100.000']);
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

    /** A confirmed cash invoice of 1 x 100 sold by a cashier at list price. */
    private function invoice(Customer $customer): Invoice
    {
        $this->actingAs($this->cashier);

        return app(InvoiceService::class)->confirmInvoice([
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $this->item->id, 'quantity' => '1.000']],
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function update(Invoice $invoice, User $as, array $line, array $extra = [], ?Customer $customer = null): Invoice
    {
        $this->actingAs($as);

        return app(InvoiceService::class)->updateInvoice($invoice, array_merge([
            'customer_id' => ($customer ?? $this->retail)->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [array_merge(['item_id' => $this->item->id, 'quantity' => '1.000'], $line)],
        ], $extra));
    }

    /** @return array<string, mixed> */
    private function snapshot(Invoice $invoice): array
    {
        $fresh = Invoice::query()->with('items')->findOrFail($invoice->id);

        return [
            'invoice' => $fresh->only(['subtotal', 'discount_amount', 'net_total', 'paid_amount', 'remaining_amount', 'total_cost', 'discount_override_by']),
            'lines' => $fresh->items->map(fn ($l): array => $l->only(['id', 'quantity', 'unit_price', 'discount_amount', 'total_price', 'override_approved_by']))->all(),
            'payments' => Payment::query()->where('invoice_id', $invoice->id)->pluck('amount', 'id')->all(),
            'stock' => (string) StoreStock::query()->where('item_id', $this->item->id)->value('quantity'),
            'balance' => (string) Customer::query()->findOrFail($invoice->customer_id)->current_balance,
        ];
    }

    #[Test]
    public function below_floor_update_without_permission_is_rejected_and_changes_nothing(): void
    {
        $invoice = $this->invoice($this->retail);
        $before = $this->snapshot($invoice);

        try {
            $this->update($invoice, $this->cashier, ['unit_price' => '60.000']);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.unit_price', $e->errors());
            $this->assertArrayHasKey('override', $e->errors());
            $this->assertStringNotContainsString('70', json_encode($e->errors(), JSON_THROW_ON_ERROR));
        }

        $this->assertSame($before, $this->snapshot($invoice));
    }

    #[Test]
    public function below_floor_update_with_the_permission_is_applied_and_audited(): void
    {
        $invoice = $this->invoice($this->retail);

        $this->update($invoice, $this->manager, ['unit_price' => '60.000']);

        $after = $this->snapshot($invoice);
        $this->assertSame('60.000', $after['invoice']['net_total']);
        $this->assertSame((int) $this->manager->id, $after['lines'][0]['override_approved_by']);
    }

    #[Test]
    public function below_floor_update_with_a_valid_manager_pin_is_applied(): void
    {
        $invoice = $this->invoice($this->retail);

        $this->update($invoice, $this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '4321']]);

        $after = $this->snapshot($invoice);
        $this->assertSame('60.000', $after['invoice']['net_total']);
        $this->assertSame((int) $this->manager->id, $after['lines'][0]['override_approved_by']);
    }

    #[Test]
    public function a_wrong_pin_is_rejected_before_the_transaction_and_counts_against_the_limiter(): void
    {
        $invoice = $this->invoice($this->retail);
        $before = $this->snapshot($invoice);
        RateLimiter::clear('manager-pin:'.RateLimitKey::scope().':approver:'.$this->manager->id);

        try {
            $this->update($invoice, $this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '0000']]);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('override.pin', $e->errors());
        }

        $this->assertSame($before, $this->snapshot($invoice));
        $this->assertSame(1, RateLimiter::attempts('manager-pin:'.RateLimitKey::scope().':approver:'.$this->manager->id));
    }

    #[Test]
    public function an_update_without_a_unit_price_uses_the_tier_list_price(): void
    {
        $invoice = $this->invoice($this->wholesale);

        $this->update($invoice, $this->cashier, [], [], $this->wholesale);

        $line = $this->snapshot($invoice)['lines'][0];
        $this->assertSame('80.000', $line['unit_price']);
    }

    #[Test]
    public function raising_the_price_above_list_is_allowed_on_update(): void
    {
        $invoice = $this->invoice($this->retail);

        $this->update($invoice, $this->cashier, ['unit_price' => '130.000']);

        $this->assertSame('130.000', $this->snapshot($invoice)['invoice']['net_total']);
    }

    #[Test]
    public function a_discount_above_the_cap_is_rejected_on_update_and_changes_nothing(): void
    {
        $invoice = $this->invoice($this->retail);
        $before = $this->snapshot($invoice);

        try {
            $this->update($invoice, $this->cashier, ['unit_price' => '100.000'], ['discount_type' => 'percentage', 'discount_value' => '10.001']);
            $this->fail('Expected the discount cap error.');
        } catch (DiscountCapExceededException $e) {
            $this->assertSame('pos.discount_cap_exceeded', DiscountCapExceededException::ERROR_CODE);
            $this->assertArrayHasKey('discount', $e->errors());
            $this->assertArrayHasKey('override', $e->errors());
        }

        $this->assertSame($before, $this->snapshot($invoice));
    }

    #[Test]
    public function a_line_percent_discount_above_the_cap_is_rejected_on_update(): void
    {
        $invoice = $this->invoice($this->retail);
        $before = $this->snapshot($invoice);

        $this->expectException(DiscountCapExceededException::class);
        try {
            $this->update($invoice, $this->cashier, ['discount_percent' => '20']);
        } finally {
            $this->assertSame($before, $this->snapshot($invoice));
        }
    }

    #[Test]
    public function a_discount_above_the_cap_passes_with_a_manager_pin_and_is_recorded(): void
    {
        $invoice = $this->invoice($this->retail);

        $this->update($invoice, $this->cashier, [], [
            'discount_type' => 'percentage', 'discount_value' => '20',
            'override' => ['user_id' => $this->discountManager->id, 'pin' => '4321'],
        ]);

        $after = $this->snapshot($invoice);
        $this->assertSame('20.000', $after['invoice']['discount_amount']);
        $this->assertSame((int) $this->discountManager->id, $after['invoice']['discount_override_by']);
    }

    #[Test]
    public function a_discount_above_the_cap_passes_for_a_user_with_the_override_permission(): void
    {
        $invoice = $this->invoice($this->retail);

        $this->update($invoice, $this->discountManager, [], ['discount_type' => 'percentage', 'discount_value' => '20']);

        $this->assertSame('80.000', $this->snapshot($invoice)['invoice']['net_total']);
    }

    #[Test]
    public function an_update_within_the_cap_needs_no_approval(): void
    {
        $invoice = $this->invoice($this->retail);

        $this->update($invoice, $this->cashier, [], ['discount_type' => 'percentage', 'discount_value' => '10']);

        $after = $this->snapshot($invoice);
        $this->assertSame('90.000', $after['invoice']['net_total']);
        $this->assertNull($after['invoice']['discount_override_by']);
    }
}
