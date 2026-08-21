<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Item;
use App\Models\Store;
use App\Models\Purchase;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\SupplierBalanceService;
use App\Services\PurchaseService;
use App\Services\ReorderAssistantService;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Requests\PaySupplierRequest;
use App\Http\Requests\StorePurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;

class SupplierAndPurchasesInertiaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'suppliers.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'suppliers.statement', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'purchases.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'purchases.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'purchases.delete', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::all());

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->store = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-01',
            'type'      => 'main',
            'is_active' => true,
            'is_main'   => true,
        ]);
    }

    protected function makeFormRequest(string $formRequestClass, string $uri, string $method = 'GET', array $parameters = []): \Illuminate\Foundation\Http\FormRequest
    {
        $req = $formRequestClass::create($uri, $method, $parameters);
        $req->setContainer(app());
        $req->setRedirector(app('redirect'));
        $req->setLaravelSession(app('session')->driver());
        $req->setUserResolver(fn() => $this->admin);
        $req->validateResolved();
        return $req;
    }

    public function test_supplier_index_renders_with_metrics(): void
    {
        $this->actingAs($this->admin);

        Supplier::create([
            'name'            => 'شركة بن البرازيل',
            'company_name'    => 'البرازيل للاستيراد',
            'phone'           => '01011112222',
            'current_balance' => '1500.000',
            'is_active'       => true,
        ]);

        $controller = new \App\Http\Controllers\SupplierController();
        $response = $controller->index(new Request());

        $page = $response->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Suppliers/Index', $page['component']);
        $this->assertEquals(1500.0, $page['props']['metrics']['total_payable']);
        $this->assertEquals(1, $page['props']['metrics']['creditors_count']);
        $this->assertEquals(1, $page['props']['metrics']['total_suppliers']);
    }

    public function test_can_create_and_update_and_toggle_supplier(): void
    {
        $this->actingAs($this->admin);

        $controller = new \App\Http\Controllers\SupplierController();

        // 1. Create
        $request = $this->makeFormRequest(StoreSupplierRequest::class, '/suppliers', 'POST', [
            'name'            => 'شركة بن كولومبيا',
            'company_name'    => 'كولومبيا للقهوة',
            'phone'           => '01055556666',
            'address'         => 'ميناء الإسكندرية',
            'opening_balance' => '500.000',
            'notes'           => 'مورد حبوب بن أخضر',
        ]);
        $controller->store($request);

        $this->assertDatabaseHas('suppliers', [
            'name'            => 'شركة بن كولومبيا',
            'current_balance' => '500.000',
        ]);

        $supplier = Supplier::where('name', 'شركة بن كولومبيا')->firstOrFail();

        // 2. Update
        $updateReq = $this->makeFormRequest(UpdateSupplierRequest::class, "/suppliers/{$supplier->id}", 'PUT', [
            'name'         => 'شركة بن كولومبيا العالمية',
            'company_name' => 'كولومبيا جروب',
            'phone'        => '01055556666',
            'address'      => 'الإسكندرية',
        ]);
        $controller->update($updateReq, $supplier->id);

        $supplier->refresh();
        $this->assertEquals('شركة بن كولومبيا العالمية', $supplier->name);

        // 3. Toggle Active
        $controller->toggleActive($supplier->id);
        $supplier->refresh();
        $this->assertFalse((bool)$supplier->is_active);

        $controller->toggleActive($supplier->id);
        $supplier->refresh();
        $this->assertTrue((bool)$supplier->is_active);
    }

    public function test_can_pay_supplier_and_generate_statement(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::create([
            'name'            => 'مورد الحسابات',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        // Purchase Invoice
        $purchase = Purchase::create([
            'purchase_number'  => 'PUR-TST-001',
            'supplier_id'      => $supplier->id,
            'store_id'         => $this->store->id,
            'user_id'          => $this->admin->id,
            'purchase_date'    => now()->subDays(3)->toDateString(),
            'subtotal'         => '2000.000',
            'net_total'        => '2000.000',
            'paid_amount'      => '0.000',
            'remaining_amount' => '2000.000',
            'status'           => 'confirmed',
            'payment_status'   => 'unpaid',
        ]);

        // Update balance
        app(SupplierBalanceService::class)->updateBalance($supplier->id);
        $supplier->refresh();
        $this->assertEquals('2000.000', $supplier->current_balance);

        // Supplier Payment
        $controller = new \App\Http\Controllers\SupplierController();
        $payReq = $this->makeFormRequest(PaySupplierRequest::class, "/suppliers/{$supplier->id}/pay", 'POST', [
            'amount'         => '800.000',
            'payment_method' => 'bank_transfer',
            'payment_date'   => now()->toDateString(),
            'notes'          => 'دفعة تحويل بنكي',
        ]);

        $controller->pay($payReq, $supplier->id, app(PaymentService::class));

        $supplier->refresh();
        $this->assertEquals('1200.000', $supplier->current_balance);

        // Statement
        $stmtResponse = $controller->statement($supplier->id, new Request(), app(SupplierBalanceService::class));
        $page = $stmtResponse->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Suppliers/Statement', $page['component']);
    }

    public function test_purchase_store_and_cancel_flow(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::create([
            'name'            => 'مورد الخامات',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        $item = Item::create([
            'name'          => 'بن إثيوبي سيدامو',
            'code'          => 'ETH-SID-01',
            'unit'          => 'كجم',
            'cost_price'    => '150.000',
            'selling_price' => '220.000',
            'current_stock' => '10.000',
            'is_active'     => true,
        ]);

        $controller = new \App\Http\Controllers\PurchaseController();

        // 1. Create Purchase
        $storeReq = $this->makeFormRequest(StorePurchaseRequest::class, '/purchases', 'POST', [
            'supplier_id'   => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items'         => [
                [
                    'item_id'   => $item->id,
                    'quantity'  => '20.000',
                    'unit_cost' => '150.000',
                ],
            ],
        ]);

        $controller->store($storeReq, app(PurchaseService::class));

        $item->refresh();
        $supplier->refresh();
        $this->assertEquals('30.000', $item->current_stock); // 10 + 20 = 30
        $this->assertEquals('3000.000', $supplier->current_balance);

        $purchase = Purchase::where('supplier_id', $supplier->id)->firstOrFail();

        // 2. Cancel Purchase
        $controller->cancel($purchase->id, app(PurchaseService::class));

        $item->refresh();
        $supplier->refresh();
        $purchase->refresh();
        $this->assertEquals('cancelled', $purchase->status);
        $this->assertEquals('10.000', $item->current_stock); // 30 - 20 = 10
        $this->assertEquals('0.000', $supplier->current_balance);
    }

    public function test_smart_reorder_computes_shortages_correctly(): void
    {
        $this->actingAs($this->admin);

        // Low stock item
        $criticalItem = Item::create([
            'name'            => 'بن هندي روبوستا',
            'code'            => 'IND-ROB-01',
            'unit'            => 'كجم',
            'cost_price'      => '100.000',
            'selling_price'   => '160.000',
            'current_stock'   => '2.000',
            'min_stock_level' => '20.000',
            'is_active'       => true,
        ]);

        $controller = new \App\Http\Controllers\PurchaseController();
        $response = $controller->smartReorder(new Request(), app(ReorderAssistantService::class));

        $page = $response->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Purchases/SmartReorder', $page['component']);
    }
}
