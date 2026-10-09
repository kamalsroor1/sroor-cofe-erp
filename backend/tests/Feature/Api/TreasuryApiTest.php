<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class TreasuryApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        // tenantHeaders() sends X-Store-Id = the tenant's main store.
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/treasury/summary', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_treasury_summary(): void
    {
        $response = $this->getJson('/api/v1/treasury/summary', $this->unauthorizedHeaders);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_get_treasury_summary(): void
    {
        $response = $this->getJson('/api/v1/treasury/summary', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'store_id',
                'today' => [
                    'date',
                    'sales_total',
                    'cash_collected',
                    'customer_receipts',
                    'total_inflow',
                    'supplier_paid',
                    'expenses_total',
                    'total_outflow',
                    'net_cash',
                ],
                'balances' => [
                    'total_receivable',
                    'total_payable',
                    'accounts',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_treasury_summary_reflects_sales_expenses_and_net_cash(): void
    {
        $this->inTenant($this->tenant, fn () => $this->seedTreasuryDay($this->storeId, $this->adminUser->id));

        $response = $this->getJson('/api/v1/treasury/summary', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'today' => [
                    'sales_total' => 1000.0,
                    'cash_collected' => 1000.0,
                    'expenses_total' => 200.0,
                    'net_cash' => 800.0,
                ],
                'balances' => [
                    'total_receivable' => 500.0,
                    'total_payable' => 1200.0,
                ],
            ]);
    }

    public function test_treasury_summary_includes_active_shift(): void
    {
        $shiftId = $this->inTenant($this->tenant, fn (): int => CashShift::create([
            'store_id' => $this->storeId,
            'user_id' => $this->adminUser->id,
            'shift_number' => 'SH-101',
            'opened_at' => now(),
            'opening_cash_balance' => '500.000',
            'status' => 'open',
        ])->id);

        $response = $this->getJson('/api/v1/treasury/summary', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'active_shift' => [
                    'id' => $shiftId,
                    'shift_number' => 'SH-101',
                    'opening_cash_balance' => 500.0,
                ],
            ]);
    }

    public function test_treasury_summary_never_includes_another_tenants_money_or_shift(): void
    {
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        $this->inTenant($other, function () use ($otherStoreId, $otherAdminId): void {
            $this->seedTreasuryDay($otherStoreId, $otherAdminId);
            CashShift::create([
                'store_id' => $otherStoreId,
                'user_id' => $otherAdminId,
                'shift_number' => 'SH-OTHER',
                'opened_at' => now(),
                'opening_cash_balance' => '999.000',
                'status' => 'open',
            ]);
        });

        $response = $this->getJson('/api/v1/treasury/summary', $this->adminHeaders)->assertOk();

        $this->assertEquals(0.0, (float) $response->json('today.sales_total'));
        $this->assertEquals(0.0, (float) $response->json('today.expenses_total'));
        $this->assertEquals(0.0, (float) $response->json('today.net_cash'));
        $this->assertEquals(0.0, (float) $response->json('balances.total_receivable'));
        $this->assertEquals(0.0, (float) $response->json('balances.total_payable'));
        $this->assertNull($response->json('active_shift'));
        $response->assertJsonMissing(['shift_number' => 'SH-OTHER']);

        // B still sees its own day.
        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($other))
            ->assertOk()
            ->assertJsonPath('active_shift.shift_number', 'SH-OTHER');
    }

    /** Runs inside the tenant: one paid cash sale of 1000, a 200 expense, 500 receivable, 1200 payable. */
    private function seedTreasuryDay(int $storeId, int $userId): void
    {
        $customer = Customer::create([
            'name' => 'عميل تجربة خزينة',
            'phone' => '01000007002',
            'current_balance' => '500.000',
            'is_active' => true,
        ]);

        Supplier::create([
            'name' => 'مورد تجربة خزينة',
            'phone' => '01000007011',
            'current_balance' => '1200.000',
            'is_active' => true,
        ]);

        Invoice::create([
            'store_id' => $storeId,
            'customer_id' => $customer->id,
            'user_id' => $userId,
            'invoice_number' => 'INV-100',
            'invoice_date' => now()->toDateString(),
            'subtotal' => '1000.000',
            'discount_amount' => '0.000',
            'tax_amount' => '0.000',
            'net_total' => '1000.000',
            'paid_amount' => '1000.000',
            'remaining_amount' => '0.000',
            'payment_type' => 'cash',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        Expense::create([
            'store_id' => $storeId,
            'user_id' => $userId,
            'expense_number' => 'EXP-100',
            'title' => 'مصروف بوفيه',
            'amount' => '200.000',
            'category' => 'hospitality',
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
        ]);
    }
}
