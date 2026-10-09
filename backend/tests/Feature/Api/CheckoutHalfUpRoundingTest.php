<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * SETG-13 (CTO Settings Q4): server checkout uses the same half-up rule as the POS
 * helper (resources/js/helpers/decimal.js), so the total the cashier sees is the total
 * that is stored. Existing invoices are never recomputed.
 */
final class CheckoutHalfUpRoundingTest extends TenantTestCase
{
    public function test_fractional_weight_line_is_rounded_half_up_like_the_pos(): void
    {
        $tenant = $this->createTenant();
        [$itemId, $customerId] = $this->seedSaleFixture($tenant, '12.345');

        // 0.255 kg x 12.345 = 3.147975 -> 3.148 (truncation gave 3.147).
        $response = $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $customerId,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $itemId, 'quantity' => '0.255', 'unit_price' => '12.345']],
        ], $this->tenantHeaders($tenant))->assertStatus(201);

        $this->inTenant($tenant, function () use ($response, $itemId): void {
            $invoice = Invoice::query()->with('items')->findOrFail((int) $response->json('data.id'));

            $this->assertSame($this->vector('mul', ['0.255', '12.345']), '3.148');
            $this->assertSame('3.148', (string) $invoice->items->first()?->total_price);
            $this->assertSame('3.148', (string) $invoice->subtotal);
            $this->assertSame('3.148', (string) $invoice->net_total);
            $this->assertSame('3.148', (string) $invoice->paid_amount);
            // Cost uses the same rule: 0.255 x 10.005 = 2.551275 -> 2.551.
            $this->assertSame(Decimal::mul('0.255', '10.005'), (string) $invoice->total_cost);
            $this->assertSame('9.745', (string) StoreStock::query()->where('item_id', $itemId)->value('quantity'));
        });
    }

    public function test_percentage_discount_is_rounded_half_up_like_the_pos(): void
    {
        $tenant = $this->createTenant();
        [$itemId, $customerId] = $this->seedSaleFixture($tenant, '550.500');

        // 0.250 x 550.500 = 137.625; 12.5% = 17.203125 -> 17.203; net = 120.422.
        $response = $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $customerId,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'discount_type' => 'percentage',
            'discount_value' => '12.5',
            'items' => [['item_id' => $itemId, 'quantity' => '0.250', 'unit_price' => '550.500']],
        ], $this->tenantHeaders($tenant))->assertStatus(201);

        $this->inTenant($tenant, function () use ($response): void {
            $invoice = Invoice::query()->findOrFail((int) $response->json('data.id'));

            $this->assertSame($this->vector('percent', ['137.625', '12.5']), (string) $invoice->discount_amount);
            $this->assertSame('17.203', (string) $invoice->discount_amount);
            $this->assertSame('120.422', (string) $invoice->net_total);
        });

        // A percentage whose 4th decimal is a 5 rounds up: 33.33266667 -> 33.333 (was 33.332).
        $this->assertSame('33.333', Decimal::percent('99.999', '33.333'));
    }

    public function test_existing_invoices_are_not_recomputed(): void
    {
        $tenant = $this->createTenant();

        $invoiceId = $this->inTenant($tenant, function () use ($tenant): int {
            $customerId = DB::table('customers')->insertGetId(['name' => 'عميل قديم', 'created_at' => now(), 'updated_at' => now()]);

            // Issued before SETG-13 with truncation: 3.147.
            return (int) DB::table('invoices')->insertGetId([
                'invoice_number' => 'OLD-TRUNC-1',
                'customer_id' => $customerId,
                'user_id' => $this->tenantAdmin($tenant)->getKey(),
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'invoice_date' => '2026-01-01',
                'payment_type' => 'cash',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'subtotal' => '3.147',
                'net_total' => '3.147',
                'paid_amount' => '3.147',
                'remaining_amount' => '0.000',
                'total_cost' => '0.000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->getJson("/api/v1/invoices/{$invoiceId}", $this->tenantHeaders($tenant))->assertStatus(200);

        $this->inTenant($tenant, function () use ($invoiceId): void {
            $invoice = Invoice::query()->findOrFail($invoiceId);
            $this->assertSame('3.147', (string) $invoice->net_total);
            $this->assertSame('3.147', (string) $invoice->subtotal);
        });
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedSaleFixture(Tenant $tenant, string $price): array
    {
        return $this->inTenant($tenant, function () use ($tenant, $price): array {
            $item = Item::create([
                'name' => 'بن بالوزن',
                'code' => 'HALF-UP-1',
                'unit' => 'كجم',
                'cost_price' => '10.005',
                'selling_price' => $price,
                'current_stock' => '10.000',
                'is_active' => true,
            ]);
            StoreStock::create([
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'item_id' => $item->id,
                'quantity' => '10.000',
            ]);
            $customer = Customer::create(['name' => 'عميل التقريب', 'phone' => '01000007530', 'current_balance' => '0.000', 'is_active' => true]);

            return [(int) $item->id, (int) $customer->id];
        });
    }

    /**
     * Expected result for an op from the shared vectors file (same file the JS test runs).
     *
     * @param  list<string>  $args
     */
    private function vector(string $op, array $args): string
    {
        $decoded = json_decode((string) file_get_contents(base_path('tests/Fixtures/rounding-vectors.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($decoded['vectors'] as $vector) {
            if ($vector['op'] === $op && $vector['args'] === $args) {
                return (string) $vector['expected'];
            }
        }

        $this->fail("No shared vector for {$op}(".implode(', ', $args).').');
    }
}
