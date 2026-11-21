<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Exceptions\BusinessRuleException;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Services\ShiftService;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RunsConcurrencyWorkers;
use Tests\Concerns\SeedsStoreStock;
use Tests\TenantTestCase;

/**
 * DATE-01 / DEC-SHIFT on a real InnoDB server: sales of a branch that requires a shift race the
 * close of that shift in parallel processes. Every sale either committed BEFORE the close took the
 * shift row (it is stamped with the shift and counted in the closed totals) or saw the shift
 * closed and was refused (409 shift_closed_during_sale) leaving nothing behind. No sale may be
 * lost from the Z report, counted twice, or written unstamped after the close.
 *
 * `mysql-only`: real parallel processes on MySQL / MariaDB (phpunit.mysql.xml).
 */
#[Group('mysql')]
#[Group('mysql-only')]
#[Group('concurrency')]
final class MysqlShiftCloseRaceTest extends TenantTestCase
{
    use RunsConcurrencyWorkers;
    use SeedsStoreStock;

    private const SALES = 10;

    public function test_sales_racing_a_shift_close_are_either_counted_in_it_or_refused(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();

        [$itemId, $customerIds, $shiftId] = $this->inTenant($tenant, function () use ($storeId): array {
            StorePosSetting::query()->updateOrCreate(['store_id' => $storeId], ['require_shift_for_sales' => true]);

            $item = $this->stockInAllStores(Item::query()->create([
                'code' => 'QA-SHIFT-01', 'name' => 'صنف وردية', 'unit' => 'كجم', 'cost_price' => '100.000',
                'selling_price' => '150.000', 'current_stock' => '100.000', 'min_stock_level' => '0.000', 'is_active' => true,
            ]));
            $customerIds = [];
            for ($i = 1; $i <= self::SALES; $i++) {
                $customerIds[] = (int) Customer::query()->create(['name' => 'عميل وردية '.$i, 'current_balance' => '0.000', 'is_active' => true])->getKey();
            }
            $shift = app(ShiftService::class)->openShift('0.000', null, $storeId);

            return [(int) $item->getKey(), $customerIds, (int) $shift->getKey()];
        });

        $payloads = array_map(fn (int $customerId): array => [
            '__scenario' => 'shift_checkout',
            'client_uuid' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'payment_type' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'items' => [['item_id' => $itemId, 'quantity' => '1.000', 'unit_price' => '150.000', 'discount_amount' => '0.000']],
        ], $customerIds);
        // The close is released in the middle of the sales.
        array_splice($payloads, intdiv(self::SALES, 2), 0, [['__scenario' => 'close_shift', 'shift_id' => $shiftId, 'actual_cash' => '0.000']]);

        $results = $this->runWorkers('shift_checkout', $tenant, $payloads);
        $dump = (string) json_encode($results, JSON_UNESCAPED_UNICODE);

        $sold = 0;
        $closed = false;
        foreach ($results as $result) {
            if (array_key_exists('status', $result)) {
                $this->assertTrue($result['ok'], 'The close must succeed: '.$dump);
                $closed = true;

                continue;
            }
            if ($result['ok'] === true) {
                $sold++;

                continue;
            }
            // Refused by the in-transaction shift check, not by a deadlock, a timeout or a crash.
            $this->assertSame(BusinessRuleException::class, $result['error_class'], $dump);
        }
        $this->assertTrue($closed, $dump);

        $state = $this->inTenant($tenant, function () use ($shiftId, $itemId, $storeId): array {
            $shift = CashShift::query()->findOrFail($shiftId);

            return [
                'status' => (string) $shift->status,
                'invoices' => Invoice::query()->count(),
                'stamped' => Invoice::query()->where('cash_shift_id', $shiftId)->count(),
                'invoice_total' => (string) Invoice::query()->where('cash_shift_id', $shiftId)->sum('net_total'),
                'total_cash_sales' => (string) $shift->total_cash_sales,
                'expected_cash' => (string) $shift->expected_cash_balance,
                'recomputed' => app(ShiftService::class)->calculateShiftTotals($shift, $shift->closed_at)['total_cash_sales'],
                'stock' => (string) Item::query()->whereKey($itemId)->value('current_stock'),
                'store_stock' => (string) StoreStock::query()->where('store_id', $storeId)->where('item_id', $itemId)->value('quantity'),
                'movements' => StockMovement::query()->where('item_id', $itemId)->count(),
            ];
        });

        $amount = bcmul('150.000', (string) $sold, 3);
        $left = bcsub('100.000', (string) $sold, 3);

        $this->assertSame('closed', $state['status'], $dump);
        $this->assertSame($sold, $state['invoices'], 'A refused sale left an invoice behind: '.$dump);
        $this->assertSame($sold, $state['stamped'], 'A committed sale is not stamped with the shift: '.$dump);
        $this->assertSame($amount, bcadd($state['invoice_total'], '0', 3));
        $this->assertSame($amount, $state['total_cash_sales'], 'The Z report lost or double counted a sale: '.$dump);
        $this->assertSame($amount, $state['expected_cash']);
        $this->assertSame($amount, $state['recomputed']);
        $this->assertSame($left, $state['stock']);
        $this->assertSame($left, $state['store_stock']);
        $this->assertSame($sold, $state['movements']);
    }
}
