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

/**
 * ROLE-01 (owner 2026-11-12): without reports.view the invoices / customers / payments lists keep
 * working but carry no aggregate money figures (summary / debt totals).
 */
final class AggregateFiguresReportsViewApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private Store $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore(['name' => 'الرئيسي', 'code' => 'MAIN-001', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);
        $this->branch = Store::create(['name' => 'فرع', 'code' => 'BR-001', 'type' => 'branch', 'is_main' => false, 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'مدين', 'phone' => '01000000001', 'balance' => '0.000', 'current_balance' => '250.000', 'price_tier' => 'retail', 'is_active' => true]);
    }

    private function user(string $phone, ?string $role, array $permissions = []): string
    {
        $user = User::factory()->create([
            'name' => 'u'.$phone, 'phone' => $phone, 'password' => Hash::make('password'),
            'is_active' => true, 'default_store_id' => $this->store->id,
        ]);
        if ($role !== null) {
            $user->assignRole(Role::findByName($role));
        }
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findByName($permission));
        }

        return $user->createToken('t')->plainTextToken;
    }

    private function invoice(string $number, Store $store): Invoice
    {
        return Invoice::create([
            'customer_id' => $this->customer->id, 'invoice_number' => $number, 'store_id' => $store->id,
            'user_id' => User::factory()->create()->id, 'invoice_date' => now()->toDateString(),
            'subtotal' => '100.000', 'net_total' => '100.000', 'paid_amount' => '100.000',
            'remaining_amount' => '0.000', 'status' => 'confirmed', 'payment_type' => 'cash',
        ]);
    }

    private function fetch(string $token, string $url)
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Store-Id' => (string) $this->store->id])->getJson($url);
    }

    public function test_cashier_gets_the_invoice_list_without_summary(): void
    {
        $this->invoice('INV-1', $this->store);
        $token = $this->user('01000000011', null, ['invoices.view']);

        $res = $this->fetch($token, '/api/v1/invoices')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertArrayNotHasKey('summary', $res->json());
    }

    public function test_reports_viewer_and_admin_get_the_invoice_summary(): void
    {
        $this->invoice('INV-1', $this->store);
        $manager = $this->user('01000000012', null, ['invoices.view', 'reports.view']);
        $admin = $this->user('01000000013', 'admin');

        foreach ([$manager, $admin] as $token) {
            $this->fetch($token, '/api/v1/invoices')->assertOk()->assertJsonStructure(['summary' => ['total_sales', 'total_paid', 'total_due']]);
        }
    }

    public function test_invoice_export_has_no_totals_row_without_reports_view_and_stays_store_scoped(): void
    {
        $this->invoice('INV-MAIN', $this->store);
        $this->invoice('INV-BRANCH', $this->branch);
        $token = $this->user('01000000014', null, ['invoices.view']);

        $res = $this->fetch($token, '/api/v1/invoices/export')->assertOk();
        $body = $res->streamedContent();
        $this->assertStringContainsString('INV-MAIN', $body);
        $this->assertStringNotContainsString('INV-BRANCH', $body);
        // header + one invoice row only: no aggregate line.
        $this->assertCount(2, array_filter(preg_split('/\r?\n/', $body)));
        $this->fetch($token, '/api/v1/invoices/export?store_id='.$this->branch->id)->assertForbidden();
    }

    public function test_customers_list_hides_debt_totals_without_reports_view(): void
    {
        $cashier = $this->user('01000000015', null, ['customers.manage']);
        $res = $this->fetch($cashier, '/api/v1/customers')->assertOk();
        $this->assertNotEmpty($res->json('data'));
        $this->assertArrayNotHasKey('total_debt', $res->json('summary'));
        $this->assertArrayNotHasKey('debtors_count', $res->json('summary'));
        $this->assertArrayHasKey('total_customers', $res->json('summary'));

        $manager = $this->user('01000000016', null, ['customers.manage', 'reports.view']);
        $this->fetch($manager, '/api/v1/customers')->assertOk()
            ->assertJsonPath('summary.total_debt', '250.000')
            ->assertJsonPath('summary.debtors_count', 1);
    }

    public function test_payments_summary_is_omitted_without_reports_view(): void
    {
        $cashier = $this->user('01000000017', null, ['customers.manage']);
        $res = $this->fetch($cashier, '/api/v1/payments')->assertOk();
        $this->assertArrayNotHasKey('summary', $res->json());
        $this->assertArrayHasKey('data', $res->json());

        $manager = $this->user('01000000018', null, ['customers.manage', 'reports.view']);
        $this->fetch($manager, '/api/v1/payments')->assertOk()->assertJsonStructure(['summary' => ['total_collections', 'total_disbursements', 'total_refunds']]);
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/invoices')->assertUnauthorized();
        $this->getJson('/api/v1/customers')->assertUnauthorized();
        $this->getJson('/api/v1/payments')->assertUnauthorized();
    }
}
