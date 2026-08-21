<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Item;
use App\Models\Store;
use App\Models\Invoice;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\ReturnDocument;
use App\Services\ReturnService;
use App\Http\Requests\StoreReturnRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\StoreDailyJournalExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\AssignStoreUsersRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;

class ReturnsDailyJournalExpenseStoreInertiaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'returns.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'returns.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'daily_journal.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'daily_journal.close_shift', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'expenses.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'stores.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'items.view', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::all());

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->store = Store::create([
            'name'      => 'المقر الرئيسي',
            'code'      => 'MAIN-01',
            'type'      => 'retail_shop',
            'is_active' => true,
            'is_main'   => true,
        ]);
    }

    protected function makeRequest(string $uri, string $method = 'GET', array $parameters = []): Request
    {
        $req = Request::create($uri, $method, $parameters);
        $req->setLaravelSession(app('session')->driver());
        $req->setUserResolver(fn() => $this->admin);
        return $req;
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

    public function test_returns_index_and_creation_flow(): void
    {
        $this->actingAs($this->admin);

        $customer = Customer::create([
            'name'            => 'عميل المرتجع',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        Invoice::create([
            'invoice_number'   => 'INV-TST-001',
            'customer_id'      => $customer->id,
            'store_id'         => $this->store->id,
            'user_id'          => $this->admin->id,
            'invoice_date'     => now()->toDateString(),
            'subtotal'         => '1000.000',
            'net_total'        => '1000.000',
            'paid_amount'      => '0.000',
            'remaining_amount' => '1000.000',
            'status'           => 'confirmed',
            'payment_method'   => 'credit',
        ]);

        $item = Item::create([
            'name'          => 'بن برازيلي كولومبي',
            'code'          => 'BRZ-COL-01',
            'unit'          => 'كجم',
            'cost_price'    => '100.000',
            'selling_price' => '150.000',
            'current_stock' => '20.000',
            'is_active'     => true,
        ]);

        $controller = new \App\Http\Controllers\ReturnController();

        // 1. Create Sales Return
        $storeReq = $this->makeFormRequest(StoreReturnRequest::class, '/returns', 'POST', [
            'return_type' => 'sales_return',
            'customer_id' => $customer->id,
            'return_date' => now()->toDateString(),
            'reason'      => 'مرتجع جودة البن',
            'items'       => [
                [
                    'item_id'    => $item->id,
                    'quantity'   => '2.000',
                    'unit_price' => '150.000',
                ],
            ],
        ]);

        $controller->store($storeReq, app(ReturnService::class));

        $item->refresh();
        $customer->refresh();
        $this->assertEquals('22.000', $item->current_stock); // 20 + 2 = 22
        $this->assertEquals('700.000', $customer->current_balance); // 1000 - 300 = 700

        // 2. Index
        $response = $controller->index($this->makeRequest('/returns'));
        $page = $response->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Returns/Index', $page['component']);
        $this->assertCount(1, $page['props']['returns']['data']);
    }

    public function test_daily_journal_shifts_and_expenses_flow(): void
    {
        $this->actingAs($this->admin);

        $controller = new \App\Http\Controllers\DailyJournalController();

        // 1. Open Shift
        $openReq = $this->makeFormRequest(OpenShiftRequest::class, '/daily-journal/open-shift', 'POST', [
            'opening_cash_balance' => '500.000',
            'notes'                => 'عهدة بداية الوردية الصباحية',
        ]);
        $controller->openShift($openReq);

        $shift = CashShift::where('status', 'open')->firstOrFail();
        $this->assertEquals('500.000', $shift->opening_cash_balance);

        // 2. Add Quick Expense
        $expReq = $this->makeFormRequest(StoreDailyJournalExpenseRequest::class, '/daily-journal/expense', 'POST', [
            'title'          => 'شراء أدوات نظافة وضيافة',
            'amount'         => '75.000',
            'cost_center'    => 'hospitality',
            'payment_method' => 'cash',
            'notes'          => 'بوفيه',
        ]);
        $controller->storeExpense($expReq);

        $this->assertDatabaseHas('expenses', [
            'title'  => 'شراء أدوات نظافة وضيافة',
            'amount' => '75.000',
        ]);

        // 3. Close Shift (Z-Report)
        $closeReq = $this->makeFormRequest(CloseShiftRequest::class, "/daily-journal/close-shift/{$shift->id}", 'POST', [
            'actual_cash_balance' => '425.000',
            'notes'               => 'مطابقة تامة',
        ]);
        $controller->closeShift($closeReq, $shift->id);

        $shift->refresh();
        $this->assertEquals('closed', $shift->status);
        $this->assertEquals('425.000', $shift->actual_cash_balance);
        $this->assertEquals('425.000', $shift->expected_cash_balance); // 500 - 75 = 425
        $this->assertEquals('0.000', $shift->cash_difference);
    }

    public function test_expenses_management_crud(): void
    {
        $this->actingAs($this->admin);

        $controller = new \App\Http\Controllers\ExpenseController();

        // 1. Create Expense
        $createReq = $this->makeFormRequest(StoreExpenseRequest::class, '/expenses', 'POST', [
            'title'          => 'إيجار فرع مصر الجديدة',
            'category'       => 'إيجار وكهرباء ومرافق',
            'cost_center'    => 'rent',
            'amount'         => '3000.000',
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'notes'          => 'إيجار شهر أغسطس',
        ]);
        $controller->store($createReq);

        $expense = Expense::where('cost_center', 'rent')->firstOrFail();
        $this->assertEquals('3000.000', $expense->amount);

        // 2. Update Expense
        $updateReq = $this->makeFormRequest(UpdateExpenseRequest::class, "/expenses/{$expense->id}", 'PUT', [
            'title'          => 'إيجار فرع مصر الجديدة بعد التخفيض',
            'category'       => 'إيجار وكهرباء ومرافق',
            'cost_center'    => 'rent',
            'amount'         => '2800.000',
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'notes'          => 'خصم 200 ج.م',
        ]);
        $controller->update($updateReq, $expense->id);

        $expense->refresh();
        $this->assertEquals('2800.000', $expense->amount);

        // 3. Index
        $response = $controller->index($this->makeRequest('/expenses'));
        $page = $response->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Expenses/Index', $page['component']);
    }

    public function test_stores_management_and_staff_assignment(): void
    {
        $this->actingAs($this->admin);

        $controller = new \App\Http\Controllers\StoreController();

        // 1. Create Van Store
        $storeReq = $this->makeFormRequest(StoreStoreRequest::class, '/stores', 'POST', [
            'name'    => 'عربية توزيع خط الجيزة',
            'code'    => 'VAN-GIZA-01',
            'type'    => 'wholesale_van',
            'phone'   => '01122334455',
            'address' => 'خط الجيزة والدقي',
        ]);
        $controller->store($storeReq);

        $van = Store::where('code', 'VAN-GIZA-01')->firstOrFail();
        $this->assertEquals('wholesale_van', $van->type);

        // 2. Assign Staff
        $staffUser = User::factory()->create(['name' => 'كابتن التوزيع']);
        $assignReq = $this->makeFormRequest(AssignStoreUsersRequest::class, "/stores/{$van->id}/assign-users", 'POST', [
            'user_ids' => [$staffUser->id],
        ]);
        $controller->assignUsers($assignReq, $van->id);

        $van->refresh();
        $this->assertTrue($van->users->contains('id', $staffUser->id));

        // 3. Toggle Active
        $controller->toggleActive($van->id);
        $van->refresh();
        $this->assertFalse((bool)$van->is_active);

        // 4. Delete Clean Store
        $controller->destroy($van->id);
        $this->assertSoftDeleted('stores', ['id' => $van->id]);
    }
}
