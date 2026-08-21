<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;

class TrashSettingsAuthNotificationsInertiaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'trash.access', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'roles.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'daily_journal.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pos.access', 'guard_name' => 'web']);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(Permission::all());

        $this->admin = User::factory()->create([
            'name'             => 'المدير العام',
            'phone'            => '01000000000',
            'email'            => 'admin@sroor.com',
            'password'         => Hash::make('password123'),
            'is_active'        => true,
        ]);
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

    public function test_trash_index_restore_and_force_delete(): void
    {
        $this->actingAs($this->admin);

        $item = Item::create([
            'name'          => 'بن محذوف مؤقتاً',
            'code'          => 'DEL-001',
            'unit'          => 'كجم',
            'cost_price'    => '100.000',
            'selling_price' => '150.000',
            'current_stock' => '10.000',
            'is_active'     => true,
        ]);

        $item->delete(); // Soft delete
        $this->assertSoftDeleted('items', ['id' => $item->id]);

        $controller = new \App\Http\Controllers\TrashController();

        // 1. Index Trash
        $response = $controller->index($this->makeRequest('/trash', 'GET', ['tab' => 'items']));
        $page = $response->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Trash/Index', $page['component']);
        $this->assertEquals(1, $page['props']['counts']['items']);

        // 2. Restore
        $controller->restore('items', $item->id);
        $this->assertNotSoftDeleted('items', ['id' => $item->id]);

        // 3. Force Delete
        $item->delete();
        $controller->forceDelete('items', $item->id);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_settings_index_and_update(): void
    {
        $this->actingAs($this->admin);

        $controller = new \App\Http\Controllers\SettingController();

        // 1. Index Settings
        $response = $controller->index($this->makeRequest('/settings'));
        $page = $response->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Settings/Index', $page['component']);
        $this->assertEquals('سرور كوفي', $page['props']['settings']['company_name']);

        // 2. Update Settings
        $updateReq = $this->makeFormRequest(\App\Http\Requests\UpdateSettingsRequest::class, '/settings', 'POST', [
            'company_name'            => 'سرور كوفي جروب',
            'company_subtitle'        => 'لتوريدات البن والخلطات',
            'company_phone'           => '01011112222',
            'company_address'         => 'القاهرة - مصر',
            'invoice_footer_note'     => 'أهلاً بكم دائماً',
            'show_print_company_name' => true,
            'show_print_subtitle'     => true,
            'show_print_logo'         => true,
            'thermal_show_customer_balance' => true,
            'print_show_qr'           => true,
            'invoice_primary_color'   => 'emerald',
            'telegram_notifications_enabled' => false,
        ]);
        $controller->update($updateReq);

        $this->assertEquals('سرور كوفي جروب', Setting::get('company_name'));
        $this->assertEquals('emerald', Setting::get('invoice_primary_color'));
    }

    public function test_auth_login_with_phone_and_password(): void
    {
        $dto = new \App\DTOs\Auth\LoginDTO(
            phone: '01000000000',
            password: 'password123',
            remember: true
        );

        $action = app(\App\Actions\Auth\LoginAction::class);
        $result = $action->execute($dto);

        $this->assertTrue($result);
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_notification_center_shared_alerts(): void
    {
        // 1. Create low stock item
        Item::create([
            'name'            => 'بن منخفض جداً',
            'code'            => 'LOW-01',
            'unit'            => 'كجم',
            'cost_price'      => '100.000',
            'selling_price'   => '150.000',
            'current_stock'   => '2.000',
            'min_stock_level' => '10.000',
            'is_active'       => true,
        ]);

        // 2. Create indebted customer
        Customer::create([
            'name'            => 'عميل مدين',
            'current_balance' => '500.000',
            'is_active'       => true,
        ]);

        $middleware = new \App\Http\Middleware\HandleInertiaRequests();
        $request = $this->makeRequest('/dashboard');
        $shared = $middleware->share($request);

        $notifications = $shared['system_notifications']();
        $this->assertCount(2, $notifications);
        $this->assertEquals('🚨', $notifications[0]['icon']);
        $this->assertEquals('👥', $notifications[1]['icon']);
    }
}
