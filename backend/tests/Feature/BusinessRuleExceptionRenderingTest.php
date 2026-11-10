<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * BE-07: a refused business rule is a translated 422/409 with a stable error_code, never a 500.
 */
class BusinessRuleExceptionRenderingTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private Customer $customer;

    private Item $item;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore([
            'name' => 'Main',
            'code' => 'MAIN-BRE',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'phone' => '01000000002',
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $admin->assignRole(Role::findByName('admin'));
        $this->token = $admin->createToken('bre')->plainTextToken;

        $this->customer = Customer::create([
            'name' => 'Customer',
            'phone' => '01000000003',
            'current_balance' => '0.000',
            'price_tier' => 'retail',
            'is_active' => true,
        ]);

        $this->item = Item::create([
            'name' => 'Beans',
            'code' => 'BN-BRE',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '400.000',
            'selling_price' => '550.000',
            'price_retail' => '550.000',
            'price_wholesale' => '500.000',
            'current_stock' => '5.000',
            'min_stock' => '1.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id' => $this->item->id,
            'quantity' => '5.000',
        ]);
    }

    private function send(string $method, string $url, array $payload, string $locale): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withHeaders([
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->token,
            'X-Store-Id' => (string) $this->store->id,
            'X-Locale' => $locale,
            'Accept' => 'application/json',
        ])->json($method, $url, $payload);
    }

    private function sale(string $quantity): array
    {
        return [
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => $quantity, 'unit_price' => '550.000'],
            ],
        ];
    }

    public function test_cancelling_an_already_cancelled_invoice_is_a_translated_409(): void
    {
        $id = $this->send('POST', '/api/v1/invoices', $this->sale('1.000'), 'ar')
            ->assertStatus(201)->json('data.id');

        $this->send('POST', "/api/v1/invoices/{$id}/cancel", ['reason' => 'first'], 'ar')->assertStatus(200);

        $this->send('POST', "/api/v1/invoices/{$id}/cancel", ['reason' => 'again'], 'ar')
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'هذه الفاتورة ملغاة بالفعل مسبقاً.',
                'error_code' => 'invoices.already_cancelled',
            ]);

        $this->send('POST', "/api/v1/invoices/{$id}/cancel", ['reason' => 'again'], 'en')
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'This invoice has already been cancelled.',
                'error_code' => 'invoices.already_cancelled',
            ]);
    }

    public function test_opening_a_second_shift_is_a_translated_409(): void
    {
        $this->send('POST', '/api/v1/shifts/open', ['opening_cash_balance' => '100.000'], 'en')
            ->assertStatus(201);

        $en = $this->send('POST', '/api/v1/shifts/open', ['opening_cash_balance' => '100.000'], 'en')
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'treasury.shift_already_open');
        $this->assertStringContainsString('is already open', (string) $en->json('message'));

        $ar = $this->send('POST', '/api/v1/shifts/open', ['opening_cash_balance' => '100.000'], 'ar')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'treasury.shift_already_open');
        $this->assertStringContainsString('مفتوحة', (string) $ar->json('message'));
        $this->assertNotSame($en->json('message'), $ar->json('message'));
    }

    public function test_insufficient_stock_checkout_is_a_translated_422_and_rolls_back(): void
    {
        $en = $this->send('POST', '/api/v1/pos/checkout', $this->sale('9.000'), 'en');

        $en->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', InsufficientStockException::ERROR_CODE);
        $this->assertStringContainsString('Beans', (string) $en->json('message'));
        $this->assertStringContainsString('insufficient', strtolower((string) $en->json('message')));

        $ar = $this->send('POST', '/api/v1/pos/checkout', $this->sale('9.000'), 'ar');
        $ar->assertStatus(422)->assertJsonPath('error_code', InsufficientStockException::ERROR_CODE);
        $this->assertStringContainsString('غير كافٍ', (string) $ar->json('message'));

        $this->assertSame(0, Invoice::count());
        $this->assertSame('5.000', (string) StoreStock::where('item_id', $this->item->id)->value('quantity'));
        $this->assertSame('5.000', (string) Item::findOrFail($this->item->id)->current_stock);
        $this->assertSame('0.000', (string) Customer::findOrFail($this->customer->id)->current_balance);
    }

    public function test_business_rule_exception_carries_status_key_and_is_not_reportable(): void
    {
        $rule = BusinessRuleException::make('invoices.already_cancelled');
        $conflict = BusinessRuleException::conflict('invoices.already_cancelled');

        $this->assertSame(422, $rule->status());
        $this->assertSame(409, $conflict->status());
        $this->assertSame('invoices.already_cancelled', $rule->errorCode());
        $this->assertFalse($rule->report());
    }
}
