<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * Golden rule 1 on the wire: money and quantities leave the API as 3-decimal strings
 * ('62.500'), never JSON numbers (62.5). Mirrors the money path of the SaaS onboarding
 * E2E flow (e2e/flows/saas-onboarding-flow.spec.js): a 0.250 kg sale at 250.000/kg,
 * then the item, invoice, invoice list summary and the shift Z report.
 */
class MoneyAsDecimalStringApiTest extends TenantTestCase
{
    private User $admin;

    private string $token;

    private Store $store;

    private Item $item;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useTenantForTest($this->createTenant());

        $this->store = $this->adoptMainStore([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN-001',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'مدير',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->admin->assignRole(Role::findByName('admin', 'web'));
        $this->token = $this->admin->createToken('test-spa')->plainTextToken;

        $this->item = Item::create([
            'name' => 'بن موزون',
            'code' => 'BN-WEIGHT',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '100.000',
            'selling_price' => '250.000',
            'price_retail' => '250.000',
            'current_stock' => '10.000',
            'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $this->store->id, 'item_id' => $this->item->id, 'quantity' => '10.000']);

        $this->shift = CashShift::create([
            'user_id' => $this->admin->id,
            'store_id' => $this->store->id,
            'shift_number' => 'SHF-261112-001',
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash_balance' => '100.000',
        ]);
    }

    public function test_weighted_sale_money_and_quantities_are_three_decimal_strings(): void
    {
        $customer = Customer::create(['name' => 'عميل نقدي', 'price_tier' => 'retail', 'is_active' => true]);

        $sale = $this->api()->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'paid_amount' => '62.500',
            'items' => [['item_id' => $this->item->id, 'quantity' => '0.250', 'unit_price' => '250.000']],
        ]);

        $sale->assertStatus(201);
        $invoice = $sale->json('data');
        $this->assertSame('62.500', $invoice['subtotal']);
        $this->assertSame('62.500', $invoice['net_total']);
        $this->assertSame('62.500', $invoice['net_amount']);
        $this->assertSame('62.500', $invoice['paid_amount']);
        $this->assertSame('0.000', $invoice['remaining_amount']);
        $this->assertSame('0.000', $invoice['discount_amount']);
        $this->assertNoFloats($invoice, 'POST /invoices');

        $shown = $this->api()->getJson('/api/v1/invoices/'.$invoice['id'])->assertOk();
        $line = $shown->json('data.items.0');
        $this->assertSame('0.250', $line['quantity']);
        $this->assertSame('250.000', $line['unit_price']);
        $this->assertSame('62.500', $line['total_price']);
        $this->assertNoFloats($shown->json('data'), 'GET /invoices/{id}');

        $list = $this->api()->getJson('/api/v1/invoices')->assertOk();
        $this->assertSame('62.500', $list->json('summary.total_sales'));
        $this->assertSame('62.500', $list->json('summary.total_paid'));
        $this->assertSame('0.000', $list->json('summary.total_due'));
        $this->assertSame('62.500', $list->json('data.0.net_total'));
        $this->assertNoFloats($list->json('data'), 'GET /invoices data');
        $this->assertNoFloats($list->json('summary'), 'GET /invoices summary');

        $item = $this->api()->getJson('/api/v1/items/'.$this->item->id)->assertOk();
        $this->assertSame('250.000', $item->json('data.selling_price'));
        $this->assertSame('100.000', $item->json('data.cost_price'));
        $this->assertSame('9.750', $item->json('data.current_stock'));
        $this->assertSame('9.750', $item->json('data.total_stock'));
        $this->assertNoFloats($item->json('data'), 'GET /items/{id}');

        $this->api()->postJson('/api/v1/shifts/close', [
            'shift_id' => $this->shift->id,
            'actual_cash_balance' => '162.500',
        ])->assertOk();

        $z = $this->api()->getJson('/api/v1/shifts/'.$this->shift->id.'/z-report')->assertOk();
        $report = $z->json('report');
        $this->assertSame('100.000', $report['opening_cash_balance']);
        $this->assertSame('62.500', $report['total_cash_sales']);
        $this->assertSame('0.000', $report['total_refunds']);
        $this->assertSame('162.500', $report['expected_cash_balance']);
        $this->assertSame('162.500', $report['actual_cash_balance']);
        $this->assertSame('0.000', $report['cash_difference']);
        $this->assertNoFloats($report, 'GET /shifts/{id}/z-report');
    }

    public function test_statements_reports_pos_and_list_summaries_carry_no_json_numbers_for_money(): void
    {
        $customer = Customer::create(['name' => 'عميل آجل', 'price_tier' => 'retail', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'مورد البن', 'current_balance' => '0.000', 'is_active' => true]);

        $this->api()->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'partial',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'paid_amount' => '50.000',
            'items' => [['item_id' => $this->item->id, 'quantity' => '0.250', 'unit_price' => '250.000']],
        ])->assertStatus(201);

        $statement = $this->api()->getJson('/api/v1/customers/'.$customer->id.'/statement')->assertOk();
        $this->assertNoFloats($statement->json(), 'GET /customers/{id}/statement');

        $endpoints = [
            '/api/v1/suppliers/'.$supplier->id.'/statement',
            '/api/v1/customers',
            '/api/v1/suppliers',
            '/api/v1/items',
            '/api/v1/purchases',
            '/api/v1/returns',
            '/api/v1/expenses',
            '/api/v1/payments',
            '/api/v1/daily-journal',
            '/api/v1/pos/bootstrap',
            '/api/v1/reports/summary',
            '/api/v1/reports/items',
            '/api/v1/reports/stores',
            '/api/v1/reports/customers',
            '/api/v1/reports/expenses',
            '/api/v1/reports/inventory',
        ];
        foreach ($endpoints as $url) {
            $response = $this->api()->getJson($url);
            $this->assertSame(200, $response->status(), "{$url}: ".$response->getContent());
            $this->assertNoFloats($response->json(), "GET {$url}");
        }

        // Percentages keep their computed scale as strings.
        $row = $this->api()->getJson('/api/v1/reports/stores')->assertOk()->json('data.0');
        $this->assertSame('62.500', $row['total_sales']);
        $this->assertSame('100.0', $row['share_pct']);
    }

    private function api(): self
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Store-Id' => (string) $this->store->id,
        ]);
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     */
    private function assertNoFloats(?array $payload, string $where, string $path = ''): void
    {
        foreach ($payload ?? [] as $key => $value) {
            $at = $path === '' ? (string) $key : $path.'.'.$key;
            if (is_array($value)) {
                $this->assertNoFloats($value, $where, $at);

                continue;
            }
            $this->assertIsNotFloat($value, "{$where}: {$at} is a JSON number (".var_export($value, true).'), expected a 3-decimal string');
        }
    }
}
