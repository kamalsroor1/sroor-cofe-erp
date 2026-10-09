<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\StockDeposit;
use App\Models\StockMovement;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\ShiftService;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Tests\TenantTestCase;

/**
 * W2 lane 2B defect: services wrote `'user_id' => Auth::id() ?? 1`. In a job or command
 * (no logged-in user) every document was silently credited to user #1 — or the write
 * failed on the users FK when #1 did not exist (harness tenants hand out offset ids, as
 * production tenants with deleted users do).
 *
 * Now: the authenticated user, else the explicit actor the caller passes, else NULL
 * ("system"). Never a made-up id.
 */
final class ServiceActorWithoutAuthTest extends TenantTestCase
{
    public function test_harness_tenant_has_no_user_1_so_the_old_fallback_could_not_work(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, fn () => $this->assertNull(User::query()->find(1)));
    }

    public function test_pos_invoice_from_a_job_without_actor_is_attributed_to_nobody(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $this->assertNull(Auth::id());
            $fixture = $this->fixture($tenant);

            $invoice = app(InvoiceService::class)->confirmInvoice($this->invoicePayload($fixture));

            $this->assertNull(Invoice::query()->whereKey($invoice->id)->value('user_id'));
            $this->assertSame([null], $this->movementActors($invoice->invoice_number));
            $this->assertSame([null], Payment::query()->where('invoice_id', $invoice->id)->pluck('user_id')->all());
            $this->assertNull($this->auditActor('invoice_confirmed', Invoice::class, (int) $invoice->id));
            $this->assertSame('8.000', (string) StoreStock::query()->where('item_id', $fixture['item_id'])->value('quantity'));
        });
    }

    public function test_pos_invoice_from_a_job_with_an_explicit_actor_is_attributed_to_that_user(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $fixture = $this->fixture($tenant);
            $actorId = (int) $this->tenantAdmin($tenant)->getKey();

            $invoice = app(InvoiceService::class)->confirmInvoice($this->invoicePayload($fixture) + ['user_id' => $actorId]);

            $this->assertSame($actorId, (int) Invoice::query()->whereKey($invoice->id)->value('user_id'));
            $this->assertSame([$actorId], array_map('intval', $this->movementActors($invoice->invoice_number)));
            $this->assertSame([$actorId], Payment::query()->where('invoice_id', $invoice->id)->pluck('user_id')->map(fn ($id) => (int) $id)->all());
            $this->assertSame($actorId, (int) $this->auditActor('invoice_confirmed', Invoice::class, (int) $invoice->id));
        });
    }

    public function test_the_authenticated_user_always_wins_over_a_payload_user_id(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $fixture = $this->fixture($tenant);
            $cashier = User::factory()->create(['phone' => '01000007521', 'is_active' => true]);
            $admin = $this->tenantAdmin($tenant);

            Auth::login($cashier);
            try {
                $invoice = app(InvoiceService::class)->confirmInvoice($this->invoicePayload($fixture) + ['user_id' => $admin->getKey()]);
            } finally {
                Auth::logout();
            }

            $this->assertSame((int) $cashier->id, (int) Invoice::query()->whereKey($invoice->id)->value('user_id'));
        });
    }

    public function test_shift_payment_and_stock_deposit_without_auth_write_null_or_the_explicit_actor(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $fixture = $this->fixture($tenant);
            $storeId = (int) $this->tenantStore($tenant)->getKey();
            $actorId = (int) $this->tenantAdmin($tenant)->getKey();

            $shift = app(ShiftService::class)->openShift('0.000', null, $storeId);
            $this->assertNull(CashShift::query()->whereKey($shift->id)->value('user_id'));
            app(ShiftService::class)->closeShift($shift, '0.000');

            $actorShift = app(ShiftService::class)->openShift('0.000', null, $storeId, $actorId);
            $this->assertSame($actorId, (int) CashShift::query()->whereKey($actorShift->id)->value('user_id'));

            $payment = app(PaymentService::class)->recordCustomerPayment([
                'customer_id' => $fixture['customer_id'],
                'amount' => '5.000',
            ]);
            $this->assertNull(Payment::query()->whereKey($payment->id)->value('user_id'));
            $this->assertNull($this->auditActor('customer_payment_recorded', Payment::class, (int) $payment->id));

            $deposit = app(StockService::class)->depositStock(
                item: Item::query()->findOrFail($fixture['item_id']),
                quantity: '1.000',
                costPrice: '40.000',
                storeId: $storeId,
            );
            $this->assertNull(StockDeposit::query()->whereKey($deposit->id)->value('user_id'));

            $actorDeposit = app(StockService::class)->depositStock(
                item: Item::query()->findOrFail($fixture['item_id']),
                quantity: '1.000',
                costPrice: '40.000',
                storeId: $storeId,
                actorId: $actorId,
            );
            $this->assertSame($actorId, (int) StockDeposit::query()->whereKey($actorDeposit->id)->value('user_id'));
            $this->assertSame([$actorId], array_map('intval', $this->movementActors("DEP-{$actorDeposit->id}")));
        });
    }

    public function test_purchase_from_a_command_with_explicit_actor(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $fixture = $this->fixture($tenant);
            $actorId = (int) $this->tenantAdmin($tenant)->getKey();
            $supplier = Supplier::create(['name' => 'مورد بدون جلسة', 'current_balance' => '0.000', 'is_active' => true]);

            $purchase = app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplier->id,
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'user_id' => $actorId,
                'paid_amount' => '40.000',
                'items' => [['item_id' => $fixture['item_id'], 'quantity' => '1.000', 'cost_price' => '40.000']],
            ]);

            $this->assertSame($actorId, (int) Purchase::query()->whereKey($purchase->id)->value('user_id'));
            $this->assertSame([$actorId], Payment::query()->where('purchase_id', $purchase->id)->pluck('user_id')->map(fn ($id) => (int) $id)->all());
            $this->assertSame([$actorId], array_map('intval', $this->movementActors($purchase->purchase_number)));
        });
    }

    /**
     * @return array{item_id: int, customer_id: int}
     */
    private function fixture(Tenant $tenant): array
    {
        $item = Item::create([
            'name' => 'بن مهمة مجدولة',
            'code' => 'JOB-ITEM-'.uniqid(),
            'unit' => 'كجم',
            'cost_price' => '40.000',
            'selling_price' => '55.000',
            'current_stock' => '10.000',
            'is_active' => true,
        ]);
        StoreStock::create([
            'store_id' => $this->tenantStore($tenant)->getKey(),
            'item_id' => $item->id,
            'quantity' => '10.000',
        ]);
        $customer = Customer::create([
            'name' => 'عميل مهمة مجدولة',
            'phone' => '01000007520',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        return ['item_id' => (int) $item->id, 'customer_id' => (int) $customer->id];
    }

    /**
     * @param  array{item_id: int, customer_id: int}  $fixture
     * @return array<string, mixed>
     */
    private function invoicePayload(array $fixture): array
    {
        return [
            'customer_id' => $fixture['customer_id'],
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $fixture['item_id'], 'quantity' => '2.000', 'unit_price' => '55.000']],
        ];
    }

    /**
     * @return list<int|null>
     */
    private function movementActors(string $documentNumber): array
    {
        return StockMovement::query()->where('document_number', $documentNumber)->pluck('user_id')->all();
    }

    private function auditActor(string $action, string $type, int $id): mixed
    {
        return AuditLog::query()
            ->where('action_type', $action)
            ->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->firstOrFail()
            ->user_id;
    }
}
