<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/** POS-02: credit / partial sales are rejected on the generic walk-in (cash) customer. */
class WalkInCustomerPaymentApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected Store $store;

    protected string $token;

    protected Customer $named;

    protected Customer $walkIn;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore([
            'name' => 'Main', 'code' => 'MAIN-001', 'type' => 'retail', 'is_main' => true, 'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin', 'phone' => self::ADMIN_PHONE, 'password' => Hash::make('password'),
            'is_active' => true, 'default_store_id' => $this->store->id,
        ]);
        $admin->assignRole(Role::findByName('admin', 'web'));
        $this->token = $admin->createToken('t')->plainTextToken;

        $this->named = Customer::create([
            'name' => 'Named', 'phone' => '01000000002', 'balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true,
        ]);
        $this->walkIn = Customer::create([
            'name' => 'عميل نقدي', 'phone' => '0000000000', 'balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true,
        ]);

        $this->item = Item::create([
            'name' => 'Beans', 'code' => 'B-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
            'cost_price' => '400.000', 'selling_price' => '550.000', 'price_retail' => '550.000',
            'price_wholesale' => '500.000', 'current_stock' => '50.000', 'min_stock' => '1.000', 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $this->store->id, 'item_id' => $this->item->id, 'quantity' => '50.000']);
    }

    /** @return array<string, mixed> */
    private function payload(?int $customerId, string $type, ?string $paid = null): array
    {
        return array_filter([
            'customer_id' => $customerId,
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => $type,
            'payment_method' => 'cash',
            'paid_amount' => $paid,
            'items' => [['item_id' => $this->item->id, 'quantity' => 2, 'unit_price' => 550]],
        ], static fn ($v) => $v !== null);
    }

    private function send(string $url, array $payload): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->postJson($url, $payload);
    }

    public function test_walk_in_partial_is_rejected_on_invoices_and_pos(): void
    {
        foreach (['/api/v1/invoices', '/api/v1/pos/checkout'] as $url) {
            $this->send($url, $this->payload($this->walkIn->id, 'partial', '500.000'))
                ->assertStatus(422)
                ->assertJsonValidationErrors('payment_type');
        }
        $this->assertSame(0, Invoice::count());
    }

    public function test_no_customer_credit_is_rejected_as_walk_in(): void
    {
        foreach (['/api/v1/invoices', '/api/v1/pos/checkout'] as $url) {
            $this->send($url, $this->payload(null, 'credit'))
                ->assertStatus(422)
                ->assertJsonValidationErrors('payment_type');
        }
        $this->assertSame(0, Invoice::count());
    }

    public function test_walk_in_full_cash_is_created(): void
    {
        $this->send('/api/v1/pos/checkout', $this->payload(null, 'cash'))->assertStatus(201);
        $this->send('/api/v1/invoices', $this->payload($this->walkIn->id, 'cash'))->assertStatus(201);
        $this->assertSame(2, Invoice::count());
    }

    public function test_named_customer_partial_and_credit_are_allowed(): void
    {
        $this->send('/api/v1/invoices', $this->payload($this->named->id, 'partial', '500.000'))->assertStatus(201);
        $this->send('/api/v1/pos/checkout', $this->payload($this->named->id, 'credit'))->assertStatus(201);
    }

    public function test_service_update_to_walk_in_with_credit_is_rejected(): void
    {
        // No HTTP route updates invoices today; the service guard stays in place for any future caller.
        $id = (int) $this->send('/api/v1/invoices', $this->payload($this->named->id, 'credit'))
            ->assertStatus(201)->json('data.id');

        $this->expectException(ValidationException::class);
        app(InvoiceService::class)->updateInvoice(Invoice::findOrFail($id), $this->payload($this->walkIn->id, 'credit'));
    }

    public function test_walk_in_blender_checkout_is_created(): void
    {
        $this->send('/api/v1/coffee-blender/invoice', [
            'blend_name' => 'Blend',
            'components' => [['item_id' => $this->item->id, 'grams' => 500, 'unit_price' => 550]],
        ])->assertStatus(201);
    }

    public function test_walk_in_bank_transfer_paid_in_full_is_created(): void
    {
        $payload = $this->payload($this->walkIn->id, 'cash');
        $payload['payment_method'] = 'bank_transfer';

        $this->send('/api/v1/pos/checkout', $payload)->assertStatus(201);
    }

    public function test_second_walk_in_like_row_cannot_bypass_the_guard(): void
    {
        $second = Customer::create([
            'name' => 'نقدي عام', 'phone' => '01000000004', 'balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true,
        ]);

        $this->send('/api/v1/invoices', $this->payload($second->id, 'credit'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_type');
    }

    public function test_offline_retry_of_a_saved_invoice_replays_instead_of_422(): void
    {
        $payload = $this->payload($this->walkIn->id, 'cash');
        $payload['client_uuid'] = '5f1d3c2e-8a4b-4c6d-9e7f-1a2b3c4d5e6f';

        $first = $this->send('/api/v1/invoices', $payload)->assertStatus(201)->json('data.id');
        $payload['payment_type'] = 'credit';
        $this->send('/api/v1/invoices', $payload)->assertSuccessful();
        $this->assertSame(1, Invoice::count());
        $this->assertNotNull($first);
    }

    public function test_requires_authentication_and_permission(): void
    {
        $this->postJson('/api/v1/pos/checkout', $this->payload(null, 'cash'))->assertStatus(401);

        $user = User::factory()->create([
            'name' => 'NoPerm', 'phone' => '01000000003', 'password' => Hash::make('password'),
            'is_active' => true, 'default_store_id' => $this->store->id,
        ]);
        $this->withHeader('Authorization', 'Bearer '.$user->createToken('t')->plainTextToken)
            ->postJson('/api/v1/pos/checkout', $this->payload(null, 'cash'))
            ->assertStatus(403);
    }
}
