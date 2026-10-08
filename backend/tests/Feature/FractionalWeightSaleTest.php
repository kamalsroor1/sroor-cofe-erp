<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FractionalWeightSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_50kg_sack_and_sell_quarter_and_eighth_kg(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $stockService = app(StockService::class);
        $invoiceService = app(InvoiceService::class);

        // 1. Create Coffee Item in Kg
        $coffee = Item::create([
            'code' => 'COF-TEST-50',
            'name' => 'بن برازيلي خام شيكارة',
            'category' => 'بن وتوليفات',
            'unit' => 'كجم',
            'current_stock' => '0.000',
            'cost_price' => '400.000', // 400 ج.م للكيلو
            'weighted_avg_cost' => '400.000',
            'selling_price' => '600.000', // 600 ج.م للكيلو (ربع كيلو = 150 ج.م، ثمن كيلو = 75 ج.م)
            'min_stock_level' => '5.000',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'عميل تجزئة قطاعي',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        // 2. Deposit a 50 kg sack into warehouse
        $stockService->depositStock(
            item: $coffee,
            quantity: '50.000', // 50 كجم
            costPrice: '400.000',
            depositType: 'manual_deposit',
            reason: 'توريد شيكارة بن 50 كجم'
        );

        $coffee->refresh();
        $this->assertEquals('50.000', $coffee->current_stock);

        // 3. Sell 0.250 kg (ربع كيلو = 250 جم)
        $invoice1 = $invoiceService->confirmInvoice([
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'paid_amount' => '150.000', // 0.250 * 600 = 150
            'items' => [
                [
                    'item_id' => $coffee->id,
                    'quantity' => '0.250', // ربع كيلو
                    'unit_price' => $coffee->selling_price,
                    'discount_amount' => '0.000',
                ],
            ],
        ]);

        $this->assertEquals('150.000', $invoice1->net_total);
        $coffee->refresh();
        // 50.000 - 0.250 = 49.750 kg remaining
        $this->assertEquals('49.750', $coffee->current_stock);

        // 4. Sell 0.125 kg (ثمن كيلو = 125 جم)
        $invoice2 = $invoiceService->confirmInvoice([
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'paid_amount' => '75.000', // 0.125 * 600 = 75
            'items' => [
                [
                    'item_id' => $coffee->id,
                    'quantity' => '0.125', // ثمن كيلو
                    'unit_price' => $coffee->selling_price,
                    'discount_amount' => '0.000',
                ],
            ],
        ]);

        $this->assertEquals('75.000', $invoice2->net_total);
        $coffee->refresh();
        // 49.750 - 0.125 = 49.625 kg remaining
        $this->assertEquals('49.625', $coffee->current_stock);
    }
}
