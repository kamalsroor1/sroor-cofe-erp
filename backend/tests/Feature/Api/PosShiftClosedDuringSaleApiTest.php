<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Exceptions\BusinessRuleException;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * DEC-SHIFT (owner decision 3): when the store's shift is closed between the "shift required"
 * check (SaleShiftGuard::assertCanSell, before the transaction) and the write, the sale is
 * REFUSED with 409 `pos.shift_closed_during_sale` from inside the sale's transaction, and
 * nothing of it remains: no invoice, payment or stock movement, stock untouched.
 *
 * The race is reproduced deterministically: a query listener closes the shift (committed, outside
 * any transaction) right after the pre-check's "is a shift open" query returned.
 */
final class PosShiftClosedDuringSaleApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private User $cashier;

    private Customer $customer;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite default is OFF (phpunit.xml); the rule is ON in production.
        config(['pos.require_shift_by_default' => true]);

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore(['name' => 'Main', 'code' => 'MAIN-RC', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);
        $this->cashier = User::factory()->create([
            'name' => 'cashier', 'phone' => '01000000031', 'password' => Hash::make('password'),
            'is_active' => true, 'default_store_id' => $this->store->id,
        ]);
        $this->cashier->assignRole(Role::findByName('cashier'));

        $this->customer = Customer::create(['name' => 'Walk', 'phone' => '01100000031', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);
        $this->item = Item::create([
            'name' => 'RC-1', 'code' => 'RC-1', 'unit' => 'كجم', 'cost_price' => '50.000', 'selling_price' => '100.000',
            'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $this->store->id, 'item_id' => $this->item->id, 'quantity' => '100.000']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function saleEndpoints(): array
    {
        return [
            'POS checkout' => ['/api/v1/pos/checkout'],
            'sales invoice' => ['/api/v1/invoices'],
        ];
    }

    #[DataProvider('saleEndpoints')]
    public function test_a_shift_closed_after_the_check_refuses_the_sale_with_409_and_rolls_everything_back(string $url): void
    {
        $shift = $this->openShift();
        $this->closeShiftRightAfterThePreCheck($shift);

        $this->sell($url, '0.250')
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('pos.shift_closed_during_sale'));

        $this->assertSame('closed', (string) CashShift::query()->whereKey($shift->id)->value('status'), 'fixture: the shift was closed mid-sale');
        $this->assertSame(0, Invoice::withTrashed()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame('100.000', (string) StoreStock::query()->where('store_id', $this->store->id)->where('item_id', $this->item->id)->value('quantity'));
        $this->assertSame('0.000', (string) Customer::query()->whereKey($this->customer->id)->value('current_balance'));
    }

    public function test_the_blender_invoice_is_refused_the_same_way(): void
    {
        $shift = $this->openShift();
        $this->closeShiftRightAfterThePreCheck($shift);

        $this->as()->postJson('/api/v1/coffee-blender/invoice', [
            'blend_name' => 'Mix', 'customer_id' => $this->customer->id, 'target_weight_grams' => 250,
            'components' => [['item_id' => $this->item->id, 'grams' => 250, 'unit_price' => '100.000']],
        ], $this->adminHeaders())->assertStatus(409)->assertJsonPath('message', __('pos.shift_closed_during_sale'));

        $this->assertSame(0, Invoice::withTrashed()->count());
        $this->assertSame('100.000', (string) StoreStock::query()->where('store_id', $this->store->id)->where('item_id', $this->item->id)->value('quantity'));
    }

    public function test_the_next_sale_after_a_new_shift_opens_goes_through_and_is_stamped_with_it(): void
    {
        $first = $this->openShift();
        $this->closeShiftRightAfterThePreCheck($first);
        $this->sell('/api/v1/pos/checkout', '1.000')->assertStatus(409);

        $second = $this->openShift('2');
        $invoiceId = (int) $this->sell('/api/v1/pos/checkout', '1.000')->assertCreated()->json('data.id');

        $this->assertSame((int) $second->id, (int) Invoice::query()->whereKey($invoiceId)->value('cash_shift_id'));
        $this->assertSame('99.000', (string) StoreStock::query()->where('store_id', $this->store->id)->where('item_id', $this->item->id)->value('quantity'));
    }

    public function test_the_in_transaction_check_refuses_when_no_shift_is_open_and_keeps_stock(): void
    {
        $this->actingAs($this->cashier);

        try {
            app(InvoiceService::class)->confirmInvoice($this->payload('1.000'), requireOpenShift: true);
            $this->fail('A sale without an open shift must be refused inside the transaction.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(409, $e->status());
            $this->assertSame('pos.shift_closed_during_sale', $e->errorCode());
        }

        $this->assertSame(0, Invoice::withTrashed()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_the_in_transaction_check_follows_the_branch_rule(): void
    {
        $setting = StorePosSetting::forStore((int) $this->store->id);
        $setting->require_shift_for_sales = false;
        $setting->save();
        $this->actingAs($this->cashier);

        $invoice = app(InvoiceService::class)->confirmInvoice($this->payload('1.000'), requireOpenShift: true);

        $this->assertNull($invoice->cash_shift_id);
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_internal_callers_that_do_not_ask_for_the_check_are_unchanged(): void
    {
        $this->actingAs($this->cashier);

        app(InvoiceService::class)->confirmInvoice($this->payload('1.000'));

        $this->assertSame(1, Invoice::query()->count());
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Closes $shift (committed) right after SaleShiftGuard::assertCanSell() read it as open: the
     * first "exists" query on cash_shifts outside a transaction.
     */
    private function closeShiftRightAfterThePreCheck(CashShift $shift): void
    {
        $done = false;
        DB::listen(function (QueryExecuted $query) use (&$done, $shift): void {
            if ($done || $query->connection->transactionLevel() > 0) {
                return;
            }
            if (str_contains($query->sql, 'cash_shifts') && str_contains(strtolower($query->sql), 'exists')) {
                $done = true;
                $query->connection->table('cash_shifts')->where('id', $shift->id)->update(['status' => 'closed', 'closed_at' => now()]);
            }
        });
    }

    private function openShift(string $suffix = '1'): CashShift
    {
        return CashShift::create([
            'user_id' => $this->cashier->id, 'store_id' => $this->store->id, 'shift_number' => 'SHIFT-RC-'.$suffix,
            'status' => 'open', 'opened_at' => now(), 'opening_cash_balance' => '100.000',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $quantity): array
    {
        return [
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $this->item->id, 'quantity' => $quantity]],
        ];
    }

    private function sell(string $url, string $quantity): TestResponse
    {
        return $this->as()->postJson($url, $this->payload($quantity));
    }

    private function as(?User $user = null): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withHeaders([
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.($user ?? $this->cashier)->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        $admin = User::factory()->create([
            'name' => 'admin', 'phone' => '01000000032', 'password' => Hash::make('password'),
            'is_active' => true, 'default_store_id' => $this->store->id,
        ]);
        $admin->assignRole(Role::findByName('admin'));

        return ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];
    }
}
