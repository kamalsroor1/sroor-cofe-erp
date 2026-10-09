<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\ReturnService;
use Tests\TenantTestCase;

/**
 * SETG-13 (coordinator decision, lane 2H): sales-return line math rounds half-up at
 * scale 3 exactly like InvoiceService, so a full return of an invoice line refunds the
 * exact amount the line was sold for — including on a .0005 tie, where the old bcmul
 * truncation refunded 0.001 less than the customer paid.
 */
final class ReturnRoundingParityTest extends TenantTestCase
{
    public function test_full_return_of_a_half_up_tie_line_refunds_the_invoice_line_total(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $this->actingAs($this->tenantAdmin($tenant));
            [$itemId, $customerId] = $this->fixture($tenant);

            // 1.500 x 10.333 = 15.4995 -> half-up 15.500 (truncation would give 15.499).
            $invoice = app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $customerId,
                'payment_type' => 'credit',
                'payment_method' => 'cash',
                'paid_amount' => '0.000',
                'items' => [['item_id' => $itemId, 'quantity' => '1.500', 'unit_price' => '10.333']],
            ]);

            $invoiceLineTotal = (string) InvoiceItem::query()->where('invoice_id', $invoice->id)->value('total_price');
            $this->assertSame('15.500', $invoiceLineTotal);

            $return = app(ReturnService::class)->createSalesReturn([
                'customer_id' => $customerId,
                'invoice_id' => $invoice->id,
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'items' => [['item_id' => $itemId, 'quantity' => '1.500', 'unit_price' => '10.333']],
            ]);

            $this->assertSame($invoiceLineTotal, (string) ReturnDocument::query()->whereKey($return->id)->value('total_amount'));
            $this->assertSame($invoiceLineTotal, (string) $return->items()->value('total_price'));
            $this->assertSame('0.000', (string) Customer::query()->whereKey($customerId)->value('current_balance'));
        });
    }

    public function test_sales_return_line_rounds_half_up_like_an_invoice_line(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $this->actingAs($this->tenantAdmin($tenant));
            [$itemId, $customerId] = $this->fixture($tenant);

            // 0.255 x 12.345 = 3.147975 -> 3.148 (half-up; truncation gives 3.147).
            $return = app(ReturnService::class)->createSalesReturn([
                'customer_id' => $customerId,
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'items' => [['item_id' => $itemId, 'quantity' => '0.255', 'unit_price' => '12.345']],
            ]);

            $this->assertSame('3.148', (string) ReturnDocument::query()->whereKey($return->id)->value('total_amount'));
        });
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fixture(Tenant $tenant): array
    {
        $item = Item::create([
            'name' => 'بن بالوزن',
            'code' => 'RRP-'.uniqid(),
            'unit' => 'كجم',
            'cost_price' => '5.000',
            'selling_price' => '10.333',
            'current_stock' => '10.000',
            'is_active' => true,
        ]);
        StoreStock::create([
            'store_id' => $this->tenantStore($tenant)->getKey(),
            'item_id' => $item->id,
            'quantity' => '10.000',
        ]);
        $customer = Customer::create([
            'name' => 'عميل التقريب',
            'phone' => '01000007601',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        return [(int) $item->id, (int) $customer->id];
    }
}
