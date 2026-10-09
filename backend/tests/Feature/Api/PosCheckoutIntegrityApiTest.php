<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AdditionalExpense;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for Phase 0 POS hotfixes:
 *  - P0-POS-1: checkout contract (partial / split / credit / bank_transfer / expenses / POS discount)
 *  - P0-POS-2: idempotent invoice creation via client_uuid / Idempotency-Key
 *
 * Fixture: one item priced 550.000 per kg, stock 50.000; selling 2.000 kg gives net 1100.000.
 */
class PosCheckoutIntegrityApiTest extends TestCase
{
    use RefreshDatabase;

    private const INVOICES_URL = '/api/v1/invoices';

    private const POS_URL = '/api/v1/pos/checkout';

    private const CLIENT_UUID_MIGRATION = 'database/migrations/tenant/2026_10_08_000001_add_client_uuid_to_invoices_table.php';

    protected User $adminUser;

    protected string $adminToken;

    protected User $unprivilegedUser;

    protected string $unprivilegedToken;

    protected Store $store;

    protected Store $otherStore;

    protected Customer $customer;

    protected Customer $otherCustomer;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN-INT',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->otherStore = Store::create([
            'name' => 'فرع المعادي',
            'code' => 'BR-INT-2',
            'type' => 'retail',
            'is_main' => false,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => '01000000002',
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole(Role::findByName('admin'));
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->unprivilegedUser = User::factory()->create([
            'name' => 'مستخدم بدون صلاحيات',
            'phone' => '01000000001',
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unprivilegedToken = $this->unprivilegedUser->createToken('unpriv-token')->plainTextToken;

        $this->customer = Customer::create([
            'name' => 'كافيه الأهرام',
            'phone' => '01098765432',
            'current_balance' => '0.000',
            'price_tier' => 'retail',
            'is_active' => true,
        ]);

        $this->otherCustomer = Customer::create([
            'name' => 'مقهى النيل',
            'phone' => '01000000003',
            'current_balance' => '0.000',
            'price_tier' => 'retail',
            'is_active' => true,
        ]);

        $this->item = Item::create([
            'name' => 'بن كولومبي وسط',
            'code' => 'BN-COL-INT',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '400.000',
            'selling_price' => '550.000',
            'price_retail' => '550.000',
            'price_wholesale' => '500.000',
            'current_stock' => '50.000',
            'min_stock' => '10.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id' => $this->item->id,
            'quantity' => '50.000',
        ]);

        StoreStock::create([
            'store_id' => $this->otherStore->id,
            'item_id' => $this->item->id,
            'quantity' => '0.000',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Net total of this payload = 2.000 kg * 550.000 = 1100.000 */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => '2.000',
                    'unit_price' => '550.000',
                ],
            ],
        ], $overrides);
    }

    private function postAs(string $url, array $payload, ?string $token = null, array $headers = []): TestResponse
    {
        $this->resetClient();

        return $this->withHeaders(array_merge([
            'Authorization' => 'Bearer '.($token ?? $this->adminToken),
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ], $headers))->postJson($url, $payload);
    }

    /** Every request starts as a fresh client: no carried-over headers, no cached authenticated user. */
    private function resetClient(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
    }

    private function storeStockQty(?int $storeId = null): string
    {
        return (string) StoreStock::where('store_id', $storeId ?? $this->store->id)
            ->where('item_id', $this->item->id)
            ->value('quantity');
    }

    private function itemStock(): string
    {
        return (string) Item::findOrFail($this->item->id)->current_stock;
    }

    private function customerBalance(?Customer $customer = null): string
    {
        return (string) Customer::findOrFail(($customer ?? $this->customer)->id)->current_balance;
    }

    /** Asserts that nothing at all was persisted for a rejected checkout. */
    private function assertNothingPersisted(): void
    {
        $this->assertSame(0, Invoice::count(), 'No invoice must be persisted');
        $this->assertSame(0, Payment::count(), 'No payment voucher must be persisted');
        $this->assertSame(0, AdditionalExpense::count(), 'No additional expense must be persisted');
        $this->assertSame(0, Expense::count(), 'No treasury expense must be persisted');
        $this->assertSame('50.000', $this->storeStockQty(), 'Store stock must be untouched');
        $this->assertSame('50.000', $this->itemStock(), 'Item global stock must be untouched');
        $this->assertSame('0.000', $this->customerBalance(), 'Customer balance must be untouched');
    }

    /** @return array<string,string> payment_method => amount */
    private function paymentsByMethod(int $invoiceId): array
    {
        $rows = Payment::where('invoice_id', $invoiceId)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->payment_method] = (string) $row->amount;
        }
        ksort($out);

        return $out;
    }

    /**
     * Payment rows in insertion order, so repeated methods (two cash lines) stay visible.
     *
     * @return list<array{0: string, 1: string}> [payment_method, amount]
     */
    private function paymentLines(int $invoiceId): array
    {
        return Payment::where('invoice_id', $invoiceId)
            ->orderBy('id')
            ->get()
            ->map(fn (Payment $p): array => [(string) $p->payment_method, (string) $p->amount])
            ->values()
            ->all();
    }

    private function paymentsSum(int $invoiceId): string
    {
        $sum = '0.000';
        foreach (Payment::where('invoice_id', $invoiceId)->pluck('amount') as $amount) {
            $sum = bcadd($sum, (string) $amount, 3);
        }

        return $sum;
    }

    /** A translation key must exist in BOTH ar and en (no fallback), never echo the raw key. */
    private function assertTranslationKeyExists(string $key): void
    {
        $this->assertTrue(__($key) !== $key && Lang::has($key, 'ar', false), "Missing ar translation for {$key}");
        $this->assertTrue(Lang::has($key, 'en', false), "Missing en translation for {$key}");
    }

    // ------------------------------------------------------------------
    // P0-POS-1: partial payments
    // ------------------------------------------------------------------

    public function test_partial_invoice_records_paid_and_remaining_amounts_exactly(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'partial',
            'paid_amount' => '300.000',
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'invoice_number', 'net_total', 'paid_amount', 'remaining_amount', 'payment_status']]);

        $invoice = Invoice::findOrFail($response->json('data.id'));
        $this->assertSame('1100.000', (string) $invoice->net_total);
        $this->assertSame('300.000', (string) $invoice->paid_amount);
        $this->assertSame('800.000', (string) $invoice->remaining_amount);
        $this->assertSame('partially_paid', $invoice->payment_status);

        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertSame('300.000', (string) Payment::where('invoice_id', $invoice->id)->value('amount'));

        $this->assertSame('800.000', $this->customerBalance());
        $this->assertSame('48.000', $this->storeStockQty());
        $this->assertSame('48.000', $this->itemStock());
    }

    public static function invalidPartialAmountProvider(): array
    {
        return [
            'paid equals net' => ['1100.000'],
            'paid greater than net' => ['1500.000'],
            'paid zero' => ['0'],
            'paid zero decimal' => ['0.000'],
            'paid missing' => [null],
            'paid negative' => ['-10.000'],
            'paid over precision' => ['300.0001'],
        ];
    }

    #[DataProvider('invalidPartialAmountProvider')]
    public function test_partial_invoice_rejects_invalid_paid_amount_and_persists_nothing(?string $paid): void
    {
        $payload = $this->payload(['payment_type' => 'partial']);
        if ($paid !== null) {
            $payload['paid_amount'] = $paid;
        }

        $this->postAs(self::INVOICES_URL, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paid_amount']);

        $this->assertNothingPersisted();
    }

    // ------------------------------------------------------------------
    // P0-POS-1: split payments
    // ------------------------------------------------------------------

    public function test_cash_split_payments_matching_net_create_one_voucher_per_method(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'paid_amount' => '1100.000',
            'payments' => [
                ['method' => 'cash', 'amount' => '600.000'],
                ['method' => 'instapay', 'amount' => '500.000'],
            ],
        ]));

        $response->assertStatus(201);
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame('1100.000', (string) $invoice->paid_amount);
        $this->assertSame('0.000', (string) $invoice->remaining_amount);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame(
            ['cash' => '600.000', 'instapay' => '500.000'],
            $this->paymentsByMethod($invoice->id)
        );
        $this->assertSame('0.000', $this->customerBalance());
    }

    /**
     * CTO decision (split-payment option A, 2026-10-08): a cash split whose total is ABOVE net
     * is now valid as long as the non-cash part does not exceed net; the surplus is change taken
     * from the cash lines. The former 'split above net' rejection case moved to
     * test_cash_split_above_net_with_card_within_due_is_accepted_and_change_comes_from_cash.
     * This is a contract change, not a weakened assertion.
     *
     * @return array<string, array{0: list<array{method: string, amount: string}>, 1: string}>
     */
    public static function belowNetCashSplitProvider(): array
    {
        return [
            'split below net' => [[['method' => 'cash', 'amount' => '600.000'], ['method' => 'instapay', 'amount' => '400.000']], '1000.000'],
            'off by 0.001' => [[['method' => 'cash', 'amount' => '600.000'], ['method' => 'visa', 'amount' => '499.999']], '1099.999'],
            'single cash short' => [[['method' => 'cash', 'amount' => '1099.999']], '1099.999'],
        ];
    }

    /** @param list<array{method: string, amount: string}> $payments */
    #[DataProvider('belowNetCashSplitProvider')]
    public function test_cash_split_payments_below_net_are_rejected(array $payments, string $expectedPaid): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'paid_amount' => '1100.000',
            'payments' => $payments,
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['payments']);

        $this->assertTranslationKeyExists('invoices.split_payment_below_net');
        $this->assertSame(
            __('invoices.split_payment_below_net', ['paid' => $expectedPaid, 'net' => '1100.000']),
            $response->json('errors.payments.0')
        );

        $this->assertNothingPersisted();
    }

    public function test_partial_split_payments_below_net_set_paid_to_sum_and_remaining_to_difference(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'partial',
            'paid_amount' => '300.000',
            'payments' => [
                ['method' => 'cash', 'amount' => '200.000'],
                ['method' => 'visa', 'amount' => '100.000'],
            ],
        ]));

        $response->assertStatus(201);
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame('300.000', (string) $invoice->paid_amount);
        $this->assertSame('800.000', (string) $invoice->remaining_amount);
        $this->assertSame('partially_paid', $invoice->payment_status);
        $this->assertSame(
            ['cash' => '200.000', 'visa' => '100.000'],
            $this->paymentsByMethod($invoice->id)
        );
        $this->assertSame('800.000', $this->customerBalance());
    }

    public function test_credit_invoice_with_payments_is_rejected(): void
    {
        $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'credit',
            'payments' => [
                ['method' => 'cash', 'amount' => '100.000'],
            ],
        ]))->assertStatus(422);

        $this->assertNothingPersisted();
    }

    public function test_split_payment_with_unknown_method_is_rejected(): void
    {
        $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'paid_amount' => '1100.000',
            'payments' => [
                ['method' => 'cash', 'amount' => '600.000'],
                ['method' => 'bitcoin', 'amount' => '500.000'],
            ],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payments.1.method']);

        $this->assertNothingPersisted();
    }

    public function test_credit_invoice_without_payments_has_full_remaining_and_no_voucher(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload(['payment_type' => 'credit']));

        $response->assertStatus(201);
        $invoice = Invoice::findOrFail($response->json('data.id'));
        $this->assertSame('0.000', (string) $invoice->paid_amount);
        $this->assertSame('1100.000', (string) $invoice->remaining_amount);
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertSame(0, Payment::count());
        $this->assertSame('1100.000', $this->customerBalance());
    }

    // ------------------------------------------------------------------
    // P0-POS-1: bank transfer
    // ------------------------------------------------------------------

    public function test_bank_transfer_invoice_is_fully_paid(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'bank_transfer',
            'payment_method' => 'bank_transfer',
        ]));

        $response->assertStatus(201);
        $invoice = Invoice::findOrFail($response->json('data.id'));
        $this->assertSame('1100.000', (string) $invoice->paid_amount);
        $this->assertSame('0.000', (string) $invoice->remaining_amount);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame(['bank_transfer' => '1100.000'], $this->paymentsByMethod($invoice->id));
        $this->assertSame('0.000', $this->customerBalance());
    }

    // ------------------------------------------------------------------
    // P0-POS-1: expenses[] (the key the SPA actually sends)
    // ------------------------------------------------------------------

    public function test_customer_account_expense_is_added_to_net_total(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'credit',
            'expenses' => [
                ['title' => 'مصاريف توصيل', 'amount' => '50.000', 'paid_by' => 'customer_account'],
            ],
        ]));

        $response->assertStatus(201);
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame('1150.000', (string) $invoice->net_total);
        $this->assertSame('50.000', (string) $invoice->shipping_cost);
        $this->assertSame('1150.000', (string) $invoice->remaining_amount);
        $this->assertDatabaseHas('additional_expenses', [
            'document_type' => Invoice::class,
            'document_id' => $invoice->id,
            'title' => 'مصاريف توصيل',
            'paid_by' => 'customer_account',
        ]);
        $this->assertSame('50.000', (string) AdditionalExpense::where('document_id', $invoice->id)->value('amount'));
        $this->assertSame(0, Expense::count(), 'customer_account expense must not hit the treasury');
        $this->assertSame('1150.000', $this->customerBalance());
    }

    public function test_treasury_paid_expense_creates_expense_and_is_excluded_from_net(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'expenses' => [
                ['title' => 'مصاريف شحن', 'amount' => '50.000', 'paid_by' => 'treasury_cash'],
            ],
        ]));

        $response->assertStatus(201);
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame('1100.000', (string) $invoice->net_total);
        $this->assertSame('0.000', (string) $invoice->shipping_cost);
        $this->assertSame(1, Expense::count());
        $expense = Expense::firstOrFail();
        $this->assertSame('50.000', (string) $expense->amount);
        $this->assertSame('cash', $expense->payment_method);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'store_id' => $this->store->id]);
        $this->assertSame(1, AdditionalExpense::where('document_id', $invoice->id)->count());
    }

    public static function invalidExpenseProvider(): array
    {
        return [
            'unknown paid_by' => [['title' => 'x', 'amount' => '50.000', 'paid_by' => 'treasury_hack'], 'expenses.0.paid_by'],
            'zero amount' => [['title' => 'x', 'amount' => '0', 'paid_by' => 'customer_account'], 'expenses.0.amount'],
            'negative amount' => [['title' => 'x', 'amount' => '-5', 'paid_by' => 'customer_account'], 'expenses.0.amount'],
            'missing title' => [['amount' => '50.000', 'paid_by' => 'customer_account'], 'expenses.0.title'],
        ];
    }

    #[DataProvider('invalidExpenseProvider')]
    public function test_invalid_expense_is_rejected_and_nothing_persisted(array $expense, string $errorKey): void
    {
        $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'expenses' => [$expense],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey]);

        $this->assertNothingPersisted();
    }

    // ------------------------------------------------------------------
    // P0-POS-1: POS checkout endpoint
    // ------------------------------------------------------------------

    public function test_pos_checkout_partial_applies_fixed_discount_and_remaining(): void
    {
        $response = $this->postAs(self::POS_URL, $this->payload([
            'payment_type' => 'partial',
            'paid_amount' => '300.000',
            'discount_type' => 'fixed',
            'discount_value' => '100.000',
        ]));

        $response->assertStatus(201);
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame('1100.000', (string) $invoice->subtotal);
        $this->assertSame('100.000', (string) $invoice->discount_amount);
        $this->assertSame('1000.000', (string) $invoice->net_total);
        $this->assertSame('300.000', (string) $invoice->paid_amount);
        $this->assertSame('700.000', (string) $invoice->remaining_amount);
        $this->assertSame('partially_paid', $invoice->payment_status);
        $this->assertSame('700.000', $this->customerBalance());
    }

    public function test_pos_checkout_percentage_discount_is_applied(): void
    {
        $response = $this->postAs(self::POS_URL, $this->payload([
            'payment_type' => 'cash',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]));

        $response->assertStatus(201);
        $invoice = Invoice::findOrFail($response->json('data.id'));
        $this->assertSame('110.000', (string) $invoice->discount_amount);
        $this->assertSame('990.000', (string) $invoice->net_total);
        $this->assertSame('990.000', (string) $invoice->paid_amount);
    }

    public function test_pos_checkout_partial_with_paid_not_below_net_is_rejected(): void
    {
        $this->postAs(self::POS_URL, $this->payload([
            'payment_type' => 'partial',
            'paid_amount' => '1100.000',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paid_amount']);

        $this->assertNothingPersisted();
    }

    // ------------------------------------------------------------------
    // P0-POS-1: rollback
    // ------------------------------------------------------------------

    public function test_split_checkout_failing_on_stock_rolls_back_everything(): void
    {
        // 60 kg > 50 kg available: stock deduction throws after the invoice row was created.
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'paid_amount' => '33000.000',
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => '60.000', 'unit_price' => '550.000'],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '33000.000'],
            ],
            'expenses' => [
                ['title' => 'شحن', 'amount' => '50.000', 'paid_by' => 'treasury_cash'],
            ],
        ]));

        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertNotSame(201, $response->status());
        $this->assertNothingPersisted();
    }

    // ------------------------------------------------------------------
    // P0-POS-2: idempotency
    // ------------------------------------------------------------------

    public function test_replaying_same_client_uuid_returns_the_original_invoice_without_side_effects(): void
    {
        $uuid = (string) Str::uuid();
        $payload = $this->payload([
            'client_uuid' => $uuid,
            'payment_type' => 'partial',
            'paid_amount' => '300.000',
        ]);

        $first = $this->postAs(self::INVOICES_URL, $payload);
        $first->assertStatus(201);
        $balanceAfterFirst = $this->customerBalance();

        $second = $this->postAs(self::INVOICES_URL, $payload);
        $second->assertStatus(200)->assertHeader('Idempotent-Replayed');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame($first->json('data.invoice_number'), $second->json('data.invoice_number'));

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame('48.000', $this->storeStockQty());
        $this->assertSame('48.000', $this->itemStock());
        $this->assertSame('800.000', $balanceAfterFirst);
        $this->assertSame($balanceAfterFirst, $this->customerBalance());
        $this->assertDatabaseHas('invoices', ['id' => $first->json('data.id'), 'client_uuid' => $uuid]);
    }

    public function test_idempotency_key_header_alone_deduplicates(): void
    {
        $uuid = (string) Str::uuid();
        $headers = ['Idempotency-Key' => $uuid];

        $first = $this->postAs(self::INVOICES_URL, $this->payload(), null, $headers);
        $first->assertStatus(201);

        $second = $this->postAs(self::INVOICES_URL, $this->payload(), null, $headers);
        $second->assertStatus(200)->assertHeader('Idempotent-Replayed');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame('48.000', $this->storeStockQty());
        $this->assertDatabaseHas('invoices', ['id' => $first->json('data.id'), 'client_uuid' => $uuid]);
    }

    public function test_reusing_client_uuid_with_a_different_customer_is_rejected(): void
    {
        $uuid = (string) Str::uuid();

        $this->postAs(self::INVOICES_URL, $this->payload(['client_uuid' => $uuid]))->assertStatus(201);

        $this->postAs(self::INVOICES_URL, $this->payload([
            'client_uuid' => $uuid,
            'customer_id' => $this->otherCustomer->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_uuid']);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame('48.000', $this->storeStockQty());
        $this->assertSame('0.000', $this->customerBalance($this->otherCustomer));
    }

    public static function malformedUuidProvider(): array
    {
        return [
            'plain text' => ['not-a-uuid'],
            'too long' => [str_repeat('a', 100)],
            'sql-ish' => ["' OR 1=1 --"],
        ];
    }

    #[DataProvider('malformedUuidProvider')]
    public function test_malformed_client_uuid_is_rejected(string $uuid): void
    {
        $this->postAs(self::INVOICES_URL, $this->payload(['client_uuid' => $uuid]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_uuid']);

        $this->assertNothingPersisted();
    }

    public function test_pos_checkout_replay_creates_a_single_invoice(): void
    {
        $uuid = (string) Str::uuid();
        $payload = $this->payload(['client_uuid' => $uuid]);

        $first = $this->postAs(self::POS_URL, $payload);
        $first->assertStatus(201);

        $second = $this->postAs(self::POS_URL, $payload);
        $second->assertStatus(200)->assertHeader('Idempotent-Replayed');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame('48.000', $this->storeStockQty());
    }

    public function test_requests_without_client_uuid_still_create_separate_invoices(): void
    {
        $first = $this->postAs(self::INVOICES_URL, $this->payload());
        $second = $this->postAs(self::INVOICES_URL, $this->payload());

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(2, Invoice::count());
        $this->assertSame('46.000', $this->storeStockQty());
    }

    public function test_replay_by_user_without_permission_is_forbidden_and_leaks_nothing(): void
    {
        $uuid = (string) Str::uuid();
        $first = $this->postAs(self::INVOICES_URL, $this->payload(['client_uuid' => $uuid]));
        $first->assertStatus(201);

        $replay = $this->postAs(self::INVOICES_URL, $this->payload(['client_uuid' => $uuid]), $this->unprivilegedToken);
        $replay->assertStatus(403);
        $this->assertNull($replay->json('data.id'));
        $this->assertSame(1, Invoice::count());
    }

    public function test_replay_without_token_is_unauthenticated(): void
    {
        $uuid = (string) Str::uuid();
        $this->postAs(self::INVOICES_URL, $this->payload(['client_uuid' => $uuid]))->assertStatus(201);

        $this->resetClient();
        $this->withHeaders(['Accept' => 'application/json', 'X-Store-Id' => (string) $this->store->id])
            ->postJson(self::INVOICES_URL, $this->payload(['client_uuid' => $uuid]))
            ->assertStatus(401);

        $this->assertSame(1, Invoice::count());
    }

    public function test_same_client_uuid_from_another_store_never_returns_the_first_stores_invoice(): void
    {
        $uuid = (string) Str::uuid();
        $first = $this->postAs(self::INVOICES_URL, $this->payload(['client_uuid' => $uuid]));
        $first->assertStatus(201);

        StoreStock::where('store_id', $this->otherStore->id)->where('item_id', $this->item->id)->update(['quantity' => '10.000']);

        $other = $this->postAs(
            self::INVOICES_URL,
            $this->payload(['client_uuid' => $uuid, 'store_id' => $this->otherStore->id]),
            null,
            ['X-Store-Id' => (string) $this->otherStore->id]
        );

        $this->assertContains($other->status(), [201, 422], 'A uuid collision across stores must create a new invoice or be rejected, never replay');
        if ($other->status() === 201) {
            $this->assertNotSame($first->json('data.id'), $other->json('data.id'));
        }
        $this->assertSame('48.000', $this->storeStockQty($this->store->id));
    }

    // ------------------------------------------------------------------
    // Split-payment option A (SP-1 / SP-2): cash may exceed net, the
    // server computes the change and trims the cash lines.
    // ------------------------------------------------------------------

    public static function checkoutUrlProvider(): array
    {
        return [
            'invoices endpoint' => [self::INVOICES_URL],
            'pos checkout endpoint' => [self::POS_URL],
        ];
    }

    #[DataProvider('checkoutUrlProvider')]
    public function test_cash_overpay_returns_change_and_records_net_only(string $url): void
    {
        $response = $this->postAs($url, $this->payload([
            'payment_type' => 'cash',
            'payments' => [
                ['method' => 'cash', 'amount' => '1200.000'],
            ],
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.change_amount', '100.000');

        $invoice = Invoice::findOrFail($response->json('data.id'));
        $this->assertSame('1100.000', (string) $invoice->net_total);
        $this->assertSame('1100.000', (string) $invoice->paid_amount);
        $this->assertSame('0.000', (string) $invoice->remaining_amount);
        $this->assertSame('100.000', (string) $invoice->change_amount);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'change_amount' => '100.000']);

        $this->assertSame(['cash' => '1100.000'], $this->paymentsByMethod($invoice->id));
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertSame('0.000', $this->customerBalance());
        $this->assertSame('48.000', $this->storeStockQty());
        $this->assertSame('48.000', $this->itemStock());
    }

    public function test_cash_split_above_net_with_card_within_due_is_accepted_and_change_comes_from_cash(): void
    {
        // Formerly the 'split above net' rejection case; valid under option A.
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'paid_amount' => '1100.000',
            'payments' => [
                ['method' => 'cash', 'amount' => '600.000'],
                ['method' => 'visa', 'amount' => '600.000'],
            ],
        ]));

        $response->assertStatus(201)->assertJsonPath('data.change_amount', '100.000');
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame('1100.000', (string) $invoice->paid_amount);
        $this->assertSame('0.000', (string) $invoice->remaining_amount);
        $this->assertSame('100.000', (string) $invoice->change_amount);
        $this->assertSame(['cash' => '500.000', 'visa' => '600.000'], $this->paymentsByMethod($invoice->id));
        $this->assertSame('1100.000', $this->paymentsSum($invoice->id));
        $this->assertSame('0.000', $this->customerBalance());
    }

    public function test_mixed_card_and_cash_change_is_taken_from_cash(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'payments' => [
                ['method' => 'visa', 'amount' => '500.000'],
                ['method' => 'cash', 'amount' => '700.000'],
            ],
        ]));

        $response->assertStatus(201)->assertJsonPath('data.change_amount', '100.000');
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame('100.000', (string) $invoice->change_amount);
        $this->assertSame('1100.000', (string) $invoice->paid_amount);
        $this->assertSame(['cash' => '600.000', 'visa' => '500.000'], $this->paymentsByMethod($invoice->id));
        $this->assertSame('1100.000', $this->paymentsSum($invoice->id));
        $this->assertSame('0.000', $this->customerBalance());
    }

    public function test_change_that_consumes_the_whole_cash_line_drops_it(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'payments' => [
                ['method' => 'cash', 'amount' => '100.000'],
                ['method' => 'visa', 'amount' => '1100.000'],
            ],
        ]));

        $response->assertStatus(201)->assertJsonPath('data.change_amount', '100.000');
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame([['visa', '1100.000']], $this->paymentLines($invoice->id), 'A cash line reduced to 0.000 must not be recorded');
        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->where('amount', '<=', 0)->count());
        $this->assertSame('1100.000', (string) $invoice->paid_amount);
        $this->assertSame('0.000', $this->customerBalance());
    }

    public function test_change_is_taken_from_the_last_cash_line_first_then_backwards(): void
    {
        // total 1180, net 1100 -> change 80: last cash (50) is dropped, the remaining 30 comes off the first cash (430 -> 400).
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'payments' => [
                ['method' => 'cash', 'amount' => '430.000'],
                ['method' => 'visa', 'amount' => '700.000'],
                ['method' => 'cash', 'amount' => '50.000'],
            ],
        ]));

        $response->assertStatus(201)->assertJsonPath('data.change_amount', '80.000');
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame([['cash', '400.000'], ['visa', '700.000']], $this->paymentLines($invoice->id));
        $this->assertSame('1100.000', $this->paymentsSum($invoice->id));
    }

    public function test_change_smaller_than_last_cash_line_only_reduces_that_line(): void
    {
        // total 1200, net 1100 -> change 100 comes entirely off the LAST cash line (400 -> 300).
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'payments' => [
                ['method' => 'cash', 'amount' => '300.000'],
                ['method' => 'visa', 'amount' => '500.000'],
                ['method' => 'cash', 'amount' => '400.000'],
            ],
        ]));

        $response->assertStatus(201)->assertJsonPath('data.change_amount', '100.000');
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame(
            [['cash', '300.000'], ['visa', '500.000'], ['cash', '300.000']],
            $this->paymentLines($invoice->id)
        );
        $this->assertSame('1100.000', $this->paymentsSum($invoice->id));
    }

    public function test_cash_split_exactly_equal_to_net_has_zero_change(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'payments' => [
                ['method' => 'cash', 'amount' => '600.000'],
                ['method' => 'instapay', 'amount' => '500.000'],
            ],
        ]));

        $response->assertStatus(201)->assertJsonPath('data.change_amount', '0.000');
        $this->assertDatabaseHas('invoices', ['id' => $response->json('data.id'), 'change_amount' => '0.000']);
    }

    public function test_cash_without_payments_has_zero_change_and_one_voucher(): void
    {
        $response = $this->postAs(self::POS_URL, $this->payload([
            'payment_type' => 'cash',
            'paid_amount' => '1100.000',
        ]));

        $response->assertStatus(201)->assertJsonPath('data.change_amount', '0.000');
        $invoice = Invoice::findOrFail($response->json('data.id'));
        $this->assertSame('0.000', (string) $invoice->change_amount);
        $this->assertSame(['cash' => '1100.000'], $this->paymentsByMethod($invoice->id));
    }

    public function test_credit_invoice_has_zero_change(): void
    {
        $response = $this->postAs(self::INVOICES_URL, $this->payload(['payment_type' => 'credit']));

        $response->assertStatus(201)->assertJsonPath('data.change_amount', '0.000');
        $this->assertDatabaseHas('invoices', ['id' => $response->json('data.id'), 'change_amount' => '0.000']);
    }

    /** @return array<string, array{0: list<array{method: string, amount: string}>, 1: string}> */
    public static function nonCashOverpayProvider(): array
    {
        return [
            'visa alone over net' => [[['method' => 'visa', 'amount' => '1200.000']], '1200.000'],
            'instapay + visa over net' => [[['method' => 'instapay', 'amount' => '600.000'], ['method' => 'visa', 'amount' => '600.000']], '1200.000'],
            'cash cannot cover card overpay' => [[['method' => 'cash', 'amount' => '50.000'], ['method' => 'visa', 'amount' => '1150.000']], '1150.000'],
            'card over by 0.001' => [[['method' => 'cash', 'amount' => '10.000'], ['method' => 'visa', 'amount' => '1100.001']], '1100.001'],
            'bank transfer over net' => [[['method' => 'bank_transfer', 'amount' => '1100.500']], '1100.500'],
        ];
    }

    /** @param list<array{method: string, amount: string}> $payments */
    #[DataProvider('nonCashOverpayProvider')]
    public function test_non_cash_overpay_is_rejected(array $payments, string $expectedNonCash): void
    {
        foreach ([self::INVOICES_URL, self::POS_URL] as $url) {
            $response = $this->postAs($url, $this->payload([
                'payment_type' => 'cash',
                'payments' => $payments,
            ]));

            $response->assertStatus(422)->assertJsonValidationErrors(['payments']);

            $this->assertTranslationKeyExists('invoices.split_payment_non_cash_exceeds_due');
            $this->assertSame(
                __('invoices.split_payment_non_cash_exceeds_due', ['non_cash' => $expectedNonCash, 'net' => '1100.000']),
                $response->json('errors.payments.0'),
                "Wrong error for {$url}"
            );

            $this->assertNothingPersisted();
        }
    }

    public function test_fractional_kilo_split_exactly_equal_to_net_is_accepted(): void
    {
        // Guards the fixture arithmetic used by the overpay test below (passes before and after option A).
        // SETG-13: net is 595.958 under half-up (it was 595.957 under truncation).
        $cardamom = $this->createFractionalItem();

        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'items' => $this->fractionalLines($cardamom),
            'payments' => [
                ['method' => 'cash', 'amount' => '595.958'],
            ],
        ]));

        $response->assertStatus(201);
        $invoice = Invoice::findOrFail($response->json('data.id'));
        $this->assertSame('595.958', (string) $invoice->net_total);
        $this->assertSame('595.958', $this->paymentsSum($invoice->id));
    }

    public function test_fractional_kilo_quantities_compute_exact_change(): void
    {
        $cardamom = $this->createFractionalItem();

        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'items' => $this->fractionalLines($cardamom),
            'payments' => [
                ['method' => 'visa', 'amount' => '95.958'],
                ['method' => 'cash', 'amount' => '600.000'],
            ],
        ]));

        // SETG-13 half-up: net = 0.250 * 550.500 (137.625) + 1.375 * 333.333 (458.332875 -> 458.333) = 595.958
        // paid 695.958 -> change 100.000; cash line 600.000 -> 500.000
        $response->assertStatus(201)->assertJsonPath('data.change_amount', '100.000');
        $invoice = Invoice::findOrFail($response->json('data.id'));

        $this->assertSame('595.958', (string) $invoice->net_total);
        $this->assertSame('595.958', (string) $invoice->paid_amount);
        $this->assertSame('0.000', (string) $invoice->remaining_amount);
        $this->assertSame('100.000', (string) $invoice->change_amount);
        $this->assertSame([['visa', '95.958'], ['cash', '500.000']], $this->paymentLines($invoice->id));
        $this->assertSame((string) $invoice->net_total, $this->paymentsSum($invoice->id));
        $this->assertSame('49.750', $this->storeStockQty());
        $this->assertSame('8.625', (string) StoreStock::where('store_id', $this->store->id)->where('item_id', $cardamom->id)->value('quantity'));
        $this->assertSame('0.000', $this->customerBalance());
    }

    public function test_fractional_cash_overpay_change_has_three_decimals(): void
    {
        $cardamom = $this->createFractionalItem();

        $response = $this->postAs(self::POS_URL, $this->payload([
            'payment_type' => 'cash',
            'items' => $this->fractionalLines($cardamom),
            'payments' => [
                ['method' => 'cash', 'amount' => '600.000'],
            ],
        ]));

        // SETG-13 half-up: 600.000 - 595.958 = 4.042 (was 4.043 under truncation).
        $response->assertStatus(201)->assertJsonPath('data.change_amount', '4.042');
        $invoice = Invoice::findOrFail($response->json('data.id'));
        $this->assertSame('4.042', (string) $invoice->change_amount);
        $this->assertSame([['cash', '595.958']], $this->paymentLines($invoice->id));
    }

    public function test_partial_with_payments_total_reaching_net_is_rejected_and_never_yields_change(): void
    {
        foreach ([
            [['method' => 'cash', 'amount' => '1100.000']],
            [['method' => 'cash', 'amount' => '700.000'], ['method' => 'visa', 'amount' => '500.000']],
        ] as $payments) {
            $response = $this->postAs(self::INVOICES_URL, $this->payload([
                'payment_type' => 'partial',
                'paid_amount' => '300.000',
                'payments' => $payments,
            ]));

            $response->assertStatus(422)->assertJsonValidationErrors(['paid_amount']);
            $this->assertSame(
                __('invoices.partial_paid_out_of_range', ['net' => '1100.000']),
                $response->json('errors.paid_amount.0')
            );
            $this->assertNothingPersisted();
        }
    }

    public function test_partial_split_still_records_paid_and_remaining_with_zero_change(): void
    {
        $response = $this->postAs(self::POS_URL, $this->payload([
            'payment_type' => 'partial',
            'payments' => [
                ['method' => 'cash', 'amount' => '250.000'],
                ['method' => 'instapay', 'amount' => '50.500'],
            ],
        ]));

        $response->assertStatus(201)->assertJsonPath('data.change_amount', '0.000');
        $invoice = Invoice::findOrFail($response->json('data.id'));
        $this->assertSame('300.500', (string) $invoice->paid_amount);
        $this->assertSame('799.500', (string) $invoice->remaining_amount);
        $this->assertSame('0.000', (string) $invoice->change_amount);
        $this->assertSame('799.500', $this->customerBalance());
    }

    #[DataProvider('checkoutUrlProvider')]
    public function test_replay_of_overpaid_cash_checkout_returns_same_invoice_and_change(string $url): void
    {
        $uuid = (string) Str::uuid();
        $payload = $this->payload([
            'client_uuid' => $uuid,
            'payment_type' => 'cash',
            'payments' => [
                ['method' => 'visa', 'amount' => '500.000'],
                ['method' => 'cash', 'amount' => '700.000'],
            ],
        ]);

        $first = $this->postAs($url, $payload);
        $first->assertStatus(201)->assertJsonPath('data.change_amount', '100.000');
        $paymentsAfterFirst = $this->paymentLines((int) $first->json('data.id'));

        $second = $this->postAs($url, $payload);
        $second->assertStatus(200)
            ->assertHeader('Idempotent-Replayed')
            ->assertJsonPath('data.change_amount', '100.000');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, Payment::count());
        $this->assertSame($paymentsAfterFirst, $this->paymentLines((int) $first->json('data.id')));
        $this->assertSame('48.000', $this->storeStockQty());
        $this->assertSame('0.000', $this->customerBalance());
    }

    public function test_overpaid_split_failing_on_stock_rolls_back_everything(): void
    {
        // net 33000 (60 kg > 50 kg available), cash overpaid by 1000: stock failure must leave nothing behind.
        $response = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => '60.000', 'unit_price' => '550.000'],
            ],
            'payments' => [
                ['method' => 'visa', 'amount' => '3000.000'],
                ['method' => 'cash', 'amount' => '31000.000'],
            ],
            'expenses' => [
                ['title' => 'شحن', 'amount' => '50.000', 'paid_by' => 'treasury_cash'],
            ],
        ]));

        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertNotSame(201, $response->status());
        $this->assertNothingPersisted();
    }

    public function test_overpay_payload_without_token_is_unauthenticated(): void
    {
        foreach ([self::INVOICES_URL, self::POS_URL] as $url) {
            $this->resetClient();
            $this->withHeaders(['Accept' => 'application/json', 'X-Store-Id' => (string) $this->store->id])
                ->postJson($url, $this->payload(['payments' => [['method' => 'cash', 'amount' => '1200.000']]]))
                ->assertStatus(401);
        }

        $this->assertNothingPersisted();
    }

    public function test_overpay_payload_without_permission_is_forbidden(): void
    {
        foreach ([self::INVOICES_URL, self::POS_URL] as $url) {
            $response = $this->postAs($url, $this->payload(['payments' => [['method' => 'cash', 'amount' => '1200.000']]]), $this->unprivilegedToken);
            $response->assertStatus(403);
            $this->assertNull($response->json('data.change_amount'));
        }

        $this->assertNothingPersisted();
    }

    public function test_split_vouchers_use_translated_notes(): void
    {
        $split = $this->postAs(self::INVOICES_URL, $this->payload([
            'payment_type' => 'cash',
            'payments' => [['method' => 'cash', 'amount' => '1200.000']],
        ]));
        $split->assertStatus(201);

        $single = $this->postAs(self::INVOICES_URL, $this->payload(['payment_type' => 'cash']));
        $single->assertStatus(201);

        $this->assertTranslationKeyExists('invoices.payment_note_split');
        $this->assertTranslationKeyExists('invoices.payment_note_on_issue');

        $this->assertSame(
            __('invoices.payment_note_split', ['number' => $split->json('data.invoice_number')]),
            Payment::where('invoice_id', $split->json('data.id'))->value('notes')
        );
        $this->assertSame(
            __('invoices.payment_note_on_issue', ['number' => $single->json('data.invoice_number')]),
            Payment::where('invoice_id', $single->json('data.id'))->value('notes')
        );
    }

    public function test_change_amount_migration_adds_column_and_is_reversible(): void
    {
        $files = glob(base_path('database/migrations/tenant/*_add_change_amount_to_invoices_table.php')) ?: [];
        $this->assertCount(1, $files, 'Exactly one tenant migration adding invoices.change_amount is expected');
        $this->assertTrue(Schema::hasColumn('invoices', 'change_amount'), 'invoices.change_amount column is missing');

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant'])->assertExitCode(0);

        $migration = require $files[0];
        $migration->up(); // guarded by hasColumn: must not throw
        $this->assertTrue(Schema::hasColumn('invoices', 'change_amount'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('invoices', 'change_amount'), 'down() must drop invoices.change_amount');
        $migration->down(); // guarded: second down must not throw

        $migration->up();
        $this->assertTrue(Schema::hasColumn('invoices', 'change_amount'));
    }

    private function createFractionalItem(): Item
    {
        $item = Item::create([
            'name' => 'حبهان مطحون',
            'code' => 'HB-FRAC-INT',
            'category' => 'spices',
            'unit' => 'كجم',
            'cost_price' => '250.000',
            'selling_price' => '333.333',
            'price_retail' => '333.333',
            'price_wholesale' => '300.000',
            'current_stock' => '10.000',
            'min_stock' => '1.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id' => $item->id,
            'quantity' => '10.000',
        ]);

        return $item;
    }

    /** @return list<array{item_id: int, quantity: string, unit_price: string}> */
    private function fractionalLines(Item $second): array
    {
        return [
            ['item_id' => $this->item->id, 'quantity' => '0.250', 'unit_price' => '550.500'],
            ['item_id' => $second->id, 'quantity' => '1.375', 'unit_price' => '333.333'],
        ];
    }

    // ------------------------------------------------------------------
    // P0-POS-2: migration
    // ------------------------------------------------------------------

    public function test_client_uuid_migration_adds_unique_column_and_is_rerunnable(): void
    {
        $this->assertFileExists(base_path(self::CLIENT_UUID_MIGRATION));
        $this->assertTrue(Schema::hasColumn('invoices', 'client_uuid'), 'invoices.client_uuid column is missing');

        $uniqueOnClientUuid = collect(Schema::getIndexes('invoices'))
            ->contains(fn (array $index) => $index['unique'] && in_array('client_uuid', $index['columns'], true));
        $this->assertTrue($uniqueOnClientUuid, 'invoices.client_uuid must be covered by a UNIQUE index');

        // Running the whole tenant path again must be a no-op.
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant'])->assertExitCode(0);

        // Running the migration's up() again directly must be guarded by hasColumn and not throw.
        $migration = require base_path(self::CLIENT_UUID_MIGRATION);
        $migration->up();

        $this->assertTrue(Schema::hasColumn('invoices', 'client_uuid'));
    }
}
