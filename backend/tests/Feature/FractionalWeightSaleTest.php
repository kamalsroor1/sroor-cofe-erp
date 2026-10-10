<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StockService;
use Tests\TenantTestCase;

class FractionalWeightSaleTest extends TenantTestCase
{
    public function test_deposit_50kg_sack_and_sell_quarter_and_eighth_kg(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $stockService = app(StockService::class);
            $invoiceService = app(InvoiceService::class);

            // 1. Create Coffee Item in Kg
            $coffee = $this->createCoffee();

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
        });
    }

    public function test_fractional_sale_does_not_touch_another_tenants_stock(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();

        $otherItemId = $this->inTenant($other, function (): int {
            $coffee = $this->createCoffee();
            $coffee->update(['current_stock' => '50.000']);

            return (int) $coffee->id;
        });

        $this->inTenant($tenant, function (): void {
            $this->actingAs(User::factory()->create());
            $coffee = $this->createCoffee();
            app(StockService::class)->depositStock(item: $coffee, quantity: '50.000', costPrice: '400.000', depositType: 'manual_deposit', reason: 'توريد');
            $customer = Customer::create(['name' => 'عميل', 'current_balance' => '0.000', 'is_active' => true]);

            app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'payment_type' => 'cash',
                'discount_type' => 'fixed',
                'discount_value' => '0.000',
                'paid_amount' => '150.000',
                'items' => [['item_id' => $coffee->id, 'quantity' => '0.250', 'unit_price' => '600.000', 'discount_amount' => '0.000']],
            ]);

            $this->assertSame('49.750', (string) $coffee->fresh()?->current_stock);
        });

        $this->inTenant($other, function () use ($otherItemId): void {
            $this->assertSame('50.000', (string) Item::query()->findOrFail($otherItemId)->current_stock);
            $this->assertDatabaseCount('invoices', 0);
        });
    }

    private function createCoffee(): Item
    {
        return Item::create([
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
    }
}
