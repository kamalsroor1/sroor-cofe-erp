<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

class InvoiceExportApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected Store $mainStore;

    protected Store $branchStore;

    protected ?Customer $defaultCustomer = null;

    protected User $admin;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->mainStore = $this->adoptMainStore(['name' => 'الرئيسي', 'code' => 'MAIN-001', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);
        $this->branchStore = Store::create(['name' => 'فرع المعادي', 'code' => 'MAADI-001', 'type' => 'branch', 'is_main' => false, 'is_active' => true]);

        $this->admin = $this->makeUser('01011111111', $this->mainStore);
        $this->admin->assignRole(Role::findByName('admin'));
        $this->adminToken = $this->admin->createToken('t')->plainTextToken;
    }

    private function makeUser(string $phone, Store $store): User
    {
        return User::factory()->create([
            'name' => 'مستخدم '.$phone,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $store->id,
        ]);
    }

    private function invoice(string $number, Store $store, array $overrides = []): Invoice
    {
        $this->defaultCustomer ??= Customer::create(['name' => 'عميل افتراضي', 'phone' => '01000000001', 'balance' => '0.000', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);

        return Invoice::create(array_merge([
            'customer_id' => $this->defaultCustomer->id,
            'invoice_number' => $number,
            'store_id' => $store->id,
            'user_id' => $this->admin->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '100.000',
            'net_total' => '100.000',
            'paid_amount' => '100.000',
            'remaining_amount' => '0.000',
            'status' => 'confirmed',
            'payment_type' => 'cash',
        ], $overrides));
    }

    private function export(string $token, string $query = '', array $headers = [])
    {
        return $this->withHeaders(array_merge(['Authorization' => 'Bearer '.$token], $headers))
            ->get('/api/v1/invoices/export'.$query);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/invoices/export')->assertStatus(401);
    }

    public function test_user_without_invoice_permission_is_forbidden(): void
    {
        $user = $this->makeUser('01022222222', $this->mainStore);

        $this->export($user->createToken('t')->plainTextToken)->assertStatus(403);
    }

    public function test_invalid_filters_return_422(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/invoices/export?from_date=not-a-date&ids[]=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_date', 'ids.0']);
    }

    public function test_streams_utf8_bom_csv_with_translated_headers_and_all_filtered_rows(): void
    {
        $customer = Customer::create(['name' => 'كافيه "الأهرام"', 'phone' => '01098765432', 'balance' => '0.000', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);
        $this->invoice('INV-1', $this->mainStore, ['customer_id' => $customer->id]);
        $this->invoice('INV-2', $this->mainStore, ['status' => 'cancelled']);
        $this->invoice('INV-3', $this->mainStore);

        $response = $this->export($this->adminToken, '', ['X-Locale' => 'en']);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('"Invoice number"', $body);
        $this->assertStringContainsString('"INV-1"', $body);
        $this->assertStringContainsString('"INV-3"', $body);
        $this->assertStringContainsString('"كافيه ""الأهرام"""', $body);
        $this->assertStringContainsString('"100.000"', $body);
    }

    public function test_applies_the_same_filters_as_the_index(): void
    {
        $this->invoice('INV-OK', $this->mainStore);
        $this->invoice('INV-CANCELLED', $this->mainStore, ['status' => 'cancelled']);

        $body = $this->export($this->adminToken, '?status=confirmed')->streamedContent();

        $this->assertStringContainsString('INV-OK', $body);
        $this->assertStringNotContainsString('INV-CANCELLED', $body);

        $index = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)->getJson('/api/v1/invoices?status=confirmed');
        $this->assertSame(1, $index->json('summary.total_count'));
    }

    public function test_ids_limit_the_export_to_the_selected_invoices(): void
    {
        $a = $this->invoice('INV-A', $this->mainStore);
        $this->invoice('INV-B', $this->mainStore);

        $body = $this->export($this->adminToken, '?ids[]='.$a->id)->streamedContent();

        $this->assertStringContainsString('INV-A', $body);
        $this->assertStringNotContainsString('INV-B', $body);
    }

    public function test_formula_values_are_neutralised(): void
    {
        $customer = Customer::create(['name' => '=cmd|calc', 'phone' => '+201', 'balance' => '0.000', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);
        $this->invoice('INV-F', $this->mainStore, ['customer_id' => $customer->id]);

        $body = $this->export($this->adminToken)->streamedContent();

        $this->assertStringContainsString('"\'=cmd|calc"', $body);
        $this->assertStringContainsString('"\'+201"', $body);
    }

    public function test_cashier_is_scoped_to_the_active_store_and_cannot_filter_another_branch(): void
    {
        $this->invoice('INV-MAIN', $this->mainStore);
        $this->invoice('INV-BRANCH', $this->branchStore);

        $cashier = $this->makeUser('01033333333', $this->mainStore);
        $cashier->givePermissionTo(Permission::findByName('invoices.view'));
        $token = $cashier->createToken('t')->plainTextToken;

        $body = $this->export($token, '', ['X-Store-Id' => (string) $this->mainStore->id])->streamedContent();
        $this->assertStringContainsString('INV-MAIN', $body);
        $this->assertStringNotContainsString('INV-BRANCH', $body);

        $this->export($token, '?store_id='.$this->branchStore->id)->assertStatus(403);
        $this->export($token, '?store_id=all')->assertStatus(403);
    }

    public function test_ids_cannot_leak_an_invoice_of_a_store_the_user_is_not_scoped_to(): void
    {
        $this->invoice('INV-MAIN', $this->mainStore);
        $foreign = $this->invoice('INV-BRANCH', $this->branchStore);

        $cashier = $this->makeUser('01044444444', $this->mainStore);
        $cashier->givePermissionTo(Permission::findByName('invoices.view'));

        $body = $this->export($cashier->createToken('t')->plainTextToken, '?ids[]='.$foreign->id, ['X-Store-Id' => (string) $this->mainStore->id])->streamedContent();

        $this->assertStringNotContainsString('INV-BRANCH', $body);
        $this->assertStringNotContainsString('INV-MAIN', $body);
    }

    public function test_user_with_only_pos_access_can_export(): void
    {
        $this->invoice('INV-POS', $this->mainStore);
        $cashier = $this->makeUser('01055555555', $this->mainStore);
        $cashier->givePermissionTo(Permission::findByName('pos.access'));

        $response = $this->export($cashier->createToken('t')->plainTextToken, '', ['X-Store-Id' => (string) $this->mainStore->id]);

        $response->assertStatus(200)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('INV-POS', $response->streamedContent());
    }

    public function test_export_is_rate_limited_to_six_per_minute(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->export($this->adminToken)->assertStatus(200);
        }

        $this->export($this->adminToken)->assertStatus(429);
    }
}
