<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\ReturnDocument;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * DEC-SHIFT (owner decision 4): a sales return (and its cash refund voucher) is stamped with the
 * shift open in its store when it is created, and the cashier's own-shift returns figure counts
 * the returns stamped with the shift instead of the clock window. A purchase return is not
 * stamped. Unstamped history keeps the window.
 *
 * Egypt 2026: summer time ends 2026-10-30 00:00 EEST -> 2026-10-29 23:00 EET (21:00 UTC).
 */
final class ReturnShiftStampApiTest extends TenantTestCase
{
    private const TZ = 'Africa/Cairo';

    private Tenant $tenant;

    private int $storeId;

    private int $customerId;

    private int $itemId;

    private User $cashier;

    /** @var array<string, string> */
    private array $cashierHeaders;

    private string $previousTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousTimezone = date_default_timezone_get();
        config(['app.timezone' => self::TZ]);
        date_default_timezone_set(self::TZ);

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->cashierHeaders = $this->tenantHeaders($this->tenant, $this->cashier);

        $this->inTenant($this->tenant, function (): void {
            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000008301', 'price_tier' => 'retail', 'is_active' => true])->id;
            $item = Item::create([
                'name' => 'بن', 'code' => 'RS-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
                'price_retail' => '20.000', 'price_wholesale' => '20.000',
                'current_stock' => '500.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            $this->itemId = (int) $item->id;
            StoreStock::create(['store_id' => $this->storeId, 'item_id' => $item->id, 'quantity' => '500.000']);
            // SHIFT-01: the admin opens the branch's shift and the cashier sells in it (shared drawer).
            StorePosSetting::query()->updateOrCreate(['store_id' => $this->storeId], ['shift_mode' => 'shared']);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->previousTimezone);

        parent::tearDown();
    }

    public function test_a_return_on_the_fall_back_night_counts_in_the_cashiers_own_shift(): void
    {
        $this->at('2026-10-29 20:30:00');  // 23:30 EEST: the store's shift (opened by the admin)
        $shiftId = $this->openShift();
        $this->at('2026-10-29 20:45:00');  // 23:45 EEST
        $invoiceId = $this->sale('10.000'); // 200.000

        $this->at('2026-10-29 21:10:00');  // 23:10 EET: the wall clock is before opened_at
        $returnId = $this->salesReturn($invoiceId, '2.500'); // 50.000 refunded in cash

        $this->inTenant($this->tenant, function () use ($shiftId, $returnId): void {
            $this->assertSame($shiftId, (int) ReturnDocument::query()->whereKey($returnId)->value('cash_shift_id'));
            $refund = Payment::query()->where('payment_number', 'like', 'PAY-RET-%')->firstOrFail();
            $this->assertSame($shiftId, (int) $refund->cash_shift_id);
            $this->assertSame('-50.000', (string) $refund->amount);
        });

        $this->at('2026-10-29 21:20:00');
        $this->getJson('/api/v1/dashboard', $this->cashierHeaders)->assertOk()
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.active_shift.id', $shiftId)
            ->assertJsonPath('data.metrics.today_returns', '50.000')
            ->assertJsonPath('data.metrics.today_returns_count', 1);
        $this->getJson('/api/v1/treasury/summary', $this->cashierHeaders)->assertOk()
            ->assertJsonPath('scope', 'own_shift')
            ->assertJsonPath('today.returns_total', '50.000')
            ->assertJsonPath('today.returns_count', 1);

        // The drawer: 100 + 200 - 50 refunded.
        $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('metrics.total_refunds', '50.000')
            ->assertJsonPath('metrics.expected_cash_balance', '250.000');
    }

    public function test_a_return_belongs_to_the_shift_open_when_it_is_made_not_to_the_invoices_shift(): void
    {
        $this->at('2026-11-08 06:00:00');
        $first = $this->openShift();
        $this->at('2026-11-08 07:00:00');
        $invoiceId = $this->sale('5.000');
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '200.000'], $this->tenantHeaders($this->tenant))->assertOk();

        $this->at('2026-11-08 08:00:00');
        $second = $this->openShift();
        $this->at('2026-11-08 09:00:00');
        $returnId = $this->salesReturn($invoiceId, '0.250'); // 5.000

        $stamp = $this->inTenant($this->tenant, fn (): int => (int) ReturnDocument::query()->whereKey($returnId)->value('cash_shift_id'));
        $this->assertSame($second, $stamp);
        $this->assertNotSame($first, $stamp);
        $this->getJson('/api/v1/dashboard', $this->cashierHeaders)->assertOk()
            ->assertJsonPath('data.metrics.today_returns', '5.000')
            ->assertJsonPath('data.metrics.today_returns_count', 1);
    }

    public function test_a_return_without_an_open_shift_and_a_purchase_return_stay_unstamped(): void
    {
        $this->at('2026-11-09 06:00:00');
        $this->openShift();
        $this->at('2026-11-09 07:00:00');
        $invoiceId = $this->sale('5.000');
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '200.000'], $this->tenantHeaders($this->tenant))->assertOk();

        $this->at('2026-11-09 08:00:00');
        $returnId = $this->salesReturn($invoiceId, '1.000', 'balance');

        $this->inTenant($this->tenant, function () use ($returnId): void {
            $this->assertNull(ReturnDocument::query()->whereKey($returnId)->value('cash_shift_id'));

            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            app(ShiftService::class)->openShift('100.000', null, $this->storeId);
            $supplierId = (int) Supplier::create(['name' => 'مورد', 'phone' => '01000008302', 'current_balance' => '0.000', 'is_active' => true])->id;
            $purchaseReturn = ReturnDocument::create([
                'return_number' => 'PRET-RS-1', 'return_type' => 'purchase_return', 'supplier_id' => $supplierId,
                'store_id' => $this->storeId, 'total_amount' => '10.000', 'return_date' => now()->toDateString(),
            ]);
            $this->assertNull($purchaseReturn->fresh()?->cash_shift_id);
        });
    }

    public function test_unstamped_history_keeps_the_time_window(): void
    {
        $this->at('2026-11-10 06:00:00');
        $this->openShift();
        $this->at('2026-11-10 07:00:00');
        $invoiceId = $this->sale('5.000');
        $this->salesReturn($invoiceId, '0.500'); // 10.000

        $this->inTenant($this->tenant, fn () => DB::table('returns')->update(['cash_shift_id' => null]));

        $this->at('2026-11-10 08:00:00');
        $this->getJson('/api/v1/dashboard', $this->cashierHeaders)->assertOk()
            ->assertJsonPath('data.metrics.today_returns', '10.000')
            ->assertJsonPath('data.metrics.today_returns_count', 1);
    }

    public function test_a_client_supplied_cash_shift_id_on_a_return_is_ignored(): void
    {
        $this->at('2026-11-11 06:00:00');
        $shiftId = $this->openShift();
        $this->at('2026-11-11 07:00:00');
        $invoiceId = $this->sale('5.000');
        $lineId = $this->inTenant($this->tenant, fn () => (int) InvoiceItem::query()->where('invoice_id', $invoiceId)->value('id'));

        $returnId = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => 'balance', 'cash_shift_id' => 999999,
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '1.000']],
        ], $this->cashierHeaders)->assertCreated()->json('data.id');

        $this->assertSame($shiftId, $this->inTenant($this->tenant, fn (): int => (int) ReturnDocument::query()->whereKey($returnId)->value('cash_shift_id')));
    }

    // ------------------------------------------------------------------ helpers

    private function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC')->setTimezone(self::TZ));
    }

    private function openShift(): int
    {
        return $this->inTenant($this->tenant, function (): int {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());

            return (int) app(ShiftService::class)->openShift('100.000', null, $this->storeId)->id;
        });
    }

    private function sale(string $quantity): int
    {
        return (int) $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $this->customerId, 'payment_type' => 'cash', 'payment_method' => 'cash',
            'items' => [['item_id' => $this->itemId, 'quantity' => $quantity, 'unit_price' => '20.000']],
        ], $this->cashierHeaders)->assertCreated()->json('data.id');
    }

    private function salesReturn(int $invoiceId, string $quantity, string $refund = 'cash'): int
    {
        $lineId = $this->inTenant($this->tenant, fn () => (int) InvoiceItem::query()->where('invoice_id', $invoiceId)->value('id'));

        return (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => $refund,
            'items' => [['invoice_item_id' => $lineId, 'quantity' => $quantity]],
        ], $this->cashierHeaders)->assertCreated()->json('data.id');
    }
}
