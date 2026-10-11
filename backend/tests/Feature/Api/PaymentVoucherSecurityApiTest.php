<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use Mockery;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * SEC-1 (W2 batch 6) — payment vouchers (POST /api/v1/payments/customer-receipt and
 * /supplier-voucher):
 *  - M4: the linked invoice/purchase must belong to the customer/supplier (422) and to a branch
 *    the user may read (404); nothing changes when refused.
 *  - M5: daily_journal.view no longer authorizes a voucher; customer receipt = customers.manage
 *    or pos.access, supplier voucher = suppliers.manage.
 *  - L4: an unexpected failure is logged and answered with a translated generic message, never
 *    the raw exception text.
 */
final class PaymentVoucherSecurityApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeAId;

    private int $storeBId;

    private int $customerId;

    private int $otherCustomerId;

    private int $supplierId;

    private int $otherSupplierId;

    private int $invoiceAId;

    private int $invoiceBId;

    private int $purchaseAId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeAId = (int) $this->tenantStore($this->tenant)->id;

        [$this->storeBId, $this->customerId, $this->otherCustomerId, $this->supplierId, $this->otherSupplierId, $this->invoiceAId, $this->invoiceBId, $this->purchaseAId]
            = $this->inTenant($this->tenant, function (): array {
                $storeB = Store::create(['name' => 'فرع التجمع', 'code' => 'BR-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);

                $customer = Customer::create(['name' => 'عميل السندات', 'phone' => '01000007101', 'current_balance' => '0.000', 'is_active' => true]);
                $otherCustomer = Customer::create(['name' => 'عميل آخر', 'phone' => '01000007102', 'current_balance' => '0.000', 'is_active' => true]);
                $supplier = Supplier::create(['name' => 'مورد البن', 'phone' => '01000007103', 'current_balance' => '0.000', 'is_active' => true]);
                $otherSupplier = Supplier::create(['name' => 'مورد آخر', 'phone' => '01000007104', 'current_balance' => '0.000', 'is_active' => true]);

                $invoiceA = $this->makeInvoice('INV-SEC1-A', $this->storeAId, (int) $customer->id);
                $invoiceB = $this->makeInvoice('INV-SEC1-B', (int) $storeB->id, (int) $customer->id);

                $purchaseA = Purchase::create([
                    'purchase_number' => 'PUR-SEC1-A',
                    'supplier_id' => $supplier->id,
                    'store_id' => $this->storeAId,
                    'purchase_date' => now()->toDateString(),
                    'status' => 'received',
                    'payment_status' => 'unpaid',
                    'subtotal' => '800.000',
                    'discount_amount' => '0.000',
                    'net_total' => '800.000',
                    'paid_amount' => '0.000',
                    'remaining_amount' => '800.000',
                ]);

                return [(int) $storeB->id, (int) $customer->id, (int) $otherCustomer->id, (int) $supplier->id, (int) $otherSupplier->id, (int) $invoiceA->id, (int) $invoiceB->id, (int) $purchaseA->id];
            });
    }

    // ------------------------------------------------------------------ M4

    public function test_receipt_for_an_invoice_of_another_customer_is_422_and_changes_nothing(): void
    {
        $this->postJson('/api/v1/payments/customer-receipt', [
            'customer_id' => $this->otherCustomerId,
            'invoice_id' => $this->invoiceAId,
            'amount' => '100.000',
        ], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_id']);

        $this->assertInvoiceUntouched($this->invoiceAId);
        $this->assertSame(0, $this->paymentsCount());
    }

    public function test_receipt_for_an_invoice_of_a_branch_the_cashier_cannot_read_is_404_and_changes_nothing(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->postJson('/api/v1/payments/customer-receipt', [
            'customer_id' => $this->customerId,
            'invoice_id' => $this->invoiceBId,
            'amount' => '100.000',
        ], $this->tenantHeaders($this->tenant, $cashier))
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertInvoiceUntouched($this->invoiceBId);
        $this->assertSame(0, $this->paymentsCount());
    }

    public function test_cashier_collects_an_invoice_of_their_own_branch(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->postJson('/api/v1/payments/customer-receipt', [
            'customer_id' => $this->customerId,
            'invoice_id' => $this->invoiceAId,
            'amount' => '250.500',
        ], $this->tenantHeaders($this->tenant, $cashier))
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $invoice = $this->inTenant($this->tenant, fn (): Invoice => Invoice::query()->findOrFail($this->invoiceAId));
        $this->assertSame('250.500', (string) $invoice->paid_amount);
        $this->assertSame('749.500', (string) $invoice->remaining_amount);
        $this->assertSame(1, $this->paymentsCount());
    }

    public function test_voucher_for_a_purchase_of_another_supplier_is_422_and_changes_nothing(): void
    {
        $this->postJson('/api/v1/payments/supplier-voucher', [
            'supplier_id' => $this->otherSupplierId,
            'purchase_id' => $this->purchaseAId,
            'amount' => '100.000',
        ], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_id']);

        $purchase = $this->inTenant($this->tenant, fn (): Purchase => Purchase::query()->findOrFail($this->purchaseAId));
        $this->assertSame('0.000', (string) $purchase->paid_amount);
        $this->assertSame('800.000', (string) $purchase->remaining_amount);
        $this->assertSame(0, $this->paymentsCount());
    }

    public function test_voucher_for_a_purchase_of_a_branch_the_user_cannot_read_is_404(): void
    {
        $storekeeper = $this->createTenantUser($this->tenant, 'storekeeper', attributes: ['default_store_id' => $this->storeBId]);

        // The storekeeper works on branch B only; the purchase belongs to branch A.
        $this->postJson('/api/v1/payments/supplier-voucher', [
            'supplier_id' => $this->supplierId,
            'purchase_id' => $this->purchaseAId,
            'amount' => '100.000',
        ], $this->tenantHeaders($this->tenant, $storekeeper, $this->storeBId))
            ->assertStatus(404);

        $this->assertSame(0, $this->paymentsCount());
    }

    // ------------------------------------------------------------------ M5

    public function test_daily_journal_view_alone_no_longer_authorizes_a_voucher(): void
    {
        $viewer = $this->createTenantUser($this->tenant, permissions: ['daily_journal.view']);
        $headers = $this->tenantHeaders($this->tenant, $viewer);

        $this->postJson('/api/v1/payments/customer-receipt', ['customer_id' => $this->customerId, 'amount' => '10.000'], $headers)
            ->assertStatus(403);
        $this->postJson('/api/v1/payments/supplier-voucher', ['supplier_id' => $this->supplierId, 'amount' => '10.000'], $headers)
            ->assertStatus(403);

        $this->assertSame(0, $this->paymentsCount());
    }

    public function test_pos_access_alone_authorizes_a_customer_receipt_but_not_a_supplier_voucher(): void
    {
        $counter = $this->createTenantUser($this->tenant, permissions: ['pos.access']);
        $headers = $this->tenantHeaders($this->tenant, $counter);

        $this->postJson('/api/v1/payments/customer-receipt', ['customer_id' => $this->customerId, 'amount' => '10.000'], $headers)
            ->assertStatus(201);
        $this->postJson('/api/v1/payments/supplier-voucher', ['supplier_id' => $this->supplierId, 'amount' => '10.000'], $headers)
            ->assertStatus(403);

        $this->assertSame(1, $this->paymentsCount());
    }

    public function test_suppliers_manage_authorizes_a_supplier_voucher(): void
    {
        $storekeeper = $this->createTenantUser($this->tenant, 'storekeeper');

        $this->postJson('/api/v1/payments/supplier-voucher', ['supplier_id' => $this->supplierId, 'amount' => '75.250'], $this->tenantHeaders($this->tenant, $storekeeper))
            ->assertStatus(201);

        $this->assertSame('75.250', $this->inTenant($this->tenant, fn (): string => (string) Payment::query()->where('supplier_id', $this->supplierId)->value('amount')));
    }

    // ------------------------------------------------------------------ L4

    public function test_an_unexpected_failure_is_answered_with_a_generic_message(): void
    {
        $service = Mockery::mock(PaymentService::class);
        $service->shouldReceive('recordCustomerPayment')->andThrow(new RuntimeException('SQLSTATE[42S02]: secret table tenant_qa.payments'));
        $this->app->instance(PaymentService::class, $service);

        $response = $this->postJson('/api/v1/payments/customer-receipt', ['customer_id' => $this->customerId, 'amount' => '10.000'], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('contacts.receipt_record_failed_generic'));

        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    // ------------------------------------------------------------------ helpers

    /** Runs inside the tenant. */
    private function makeInvoice(string $number, int $storeId, int $customerId): Invoice
    {
        return Invoice::create([
            'invoice_number' => $number,
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '1000.000',
            'discount_amount' => '0.000',
            'net_total' => '1000.000',
            'paid_amount' => '0.000',
            'remaining_amount' => '1000.000',
            'total_cost' => '600.000',
            'status' => 'confirmed',
            'payment_type' => 'credit',
            'payment_method' => 'cash',
        ]);
    }

    private function assertInvoiceUntouched(int $invoiceId): void
    {
        $invoice = $this->inTenant($this->tenant, fn (): Invoice => Invoice::query()->findOrFail($invoiceId));

        $this->assertSame('0.000', (string) $invoice->paid_amount);
        $this->assertSame('1000.000', (string) $invoice->remaining_amount);
    }

    private function paymentsCount(): int
    {
        return $this->inTenant($this->tenant, fn (): int => Payment::query()->count());
    }
}
