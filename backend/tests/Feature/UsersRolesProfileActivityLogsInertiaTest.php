<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\ActivityLog;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class UsersRolesProfileActivityLogsInertiaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'users.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'roles.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'logs.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pos.access', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'invoices.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'items.view', 'guard_name' => 'web']);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(Permission::all());

        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web'])
            ->givePermissionTo(['pos.access', 'invoices.view']);

        $this->admin = User::factory()->create([
            'name'      => 'المدير العام',
            'phone'     => '01000000000',
            'email'     => 'admin@sroor.com',
            'password'  => Hash::make('password123'),
            'is_active' => true,
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

    public function test_user_management_crud_and_toggle_active(): void
    {
        $this->actingAs($this->admin);

        $controller = new \App\Http\Controllers\UserController();

        // 1. Create Cashier User
        $createReq = $this->makeFormRequest(StoreUserRequest::class, '/users', 'POST', [
            'name'             => 'كاشير الصباح',
            'phone'            => '01011112233',
            'email'            => 'cashier1@sroor.com',
            'password'         => 'secret123',
            'role'             => 'cashier',
            'default_store_id' => $this->store->id,
            'is_active'        => true,
        ]);
        $controller->store($createReq);

        $user = User::where('phone', '01011112233')->firstOrFail();
        $this->assertEquals('كاشير الصباح', $user->name);
        $this->assertTrue($user->hasRole('cashier'));
        $this->assertTrue(Hash::check('secret123', $user->password));

        // 2. Update User
        $updateReq = $this->makeFormRequest(UpdateUserRequest::class, "/users/{$user->id}", 'PUT', [
            'name'             => 'كاشير الصباح المعدل',
            'phone'            => '01011112233',
            'email'            => 'cashier1@sroor.com',
            'role'             => 'cashier',
            'default_store_id' => $this->store->id,
            'is_active'        => true,
        ]);
        $controller->update($updateReq, $user->id);

        $user->refresh();
        $this->assertEquals('كاشير الصباح المعدل', $user->name);

        // 3. Toggle Status
        $controller->toggleActive($user->id);
        $user->refresh();
        $this->assertFalse((bool)$user->is_active);

        // 4. Index
        $response = $controller->index($this->makeRequest('/users'));
        $page = $response->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Users/Index', $page['component']);
        $this->assertCount(2, $page['props']['users']['data']); // Admin + Cashier
    }

    public function test_roles_and_permissions_matrix(): void
    {
        $this->actingAs($this->admin);

        $controller = new \App\Http\Controllers\RoleController();

        $cashierRole = Role::where('name', 'cashier')->firstOrFail();

        // 1. Index
        $response = $controller->index($this->makeRequest('/roles', 'GET', ['role_id' => $cashierRole->id]));
        $page = $response->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Roles/Index', $page['component']);
        $this->assertContains('pos.access', $page['props']['selected_role']['permissions']);

        // 2. Update Permissions
        $updateReq = $this->makeFormRequest(UpdateRolePermissionsRequest::class, "/roles/{$cashierRole->id}", 'PUT', [
            'permissions' => ['pos.access', 'invoices.view', 'items.view'],
        ]);
        $controller->update($updateReq, $cashierRole->id);

        $cashierRole->refresh();
        $this->assertTrue($cashierRole->hasPermissionTo('items.view'));
    }

    public function test_user_profile_show_and_update(): void
    {
        $this->actingAs($this->admin);

        $controller = new \App\Http\Controllers\ProfileController();

        // 1. Show Profile
        $showResponse = $controller->show();
        $page = $showResponse->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('Profile/Show', $page['component']);
        $this->assertEquals('المدير العام', $page['props']['user']['name']);

        // 2. Update Profile & Theme
        $updateReq = $this->makeFormRequest(UpdateProfileRequest::class, '/profile', 'PUT', [
            'name'                      => 'المدير التنفيذي',
            'phone'                     => '01000000000',
            'email'                     => 'ceo@sroor.com',
            'current_password'          => 'password123',
            'new_password'              => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
            'theme_preference'          => 'light',
        ]);
        $controller->update($updateReq);

        $this->admin->refresh();
        $this->assertEquals('المدير التنفيذي', $this->admin->name);
        $this->assertEquals('light', $this->admin->theme_preference);
        $this->assertTrue(Hash::check('newpassword123', $this->admin->password));
    }

    public function test_activity_logs_index_and_stats(): void
    {
        $this->actingAs($this->admin);

        ActivityLog::create([
            'user_id'     => $this->admin->id,
            'store_id'    => $this->store->id,
            'module'      => 'auth',
            'action'      => 'login',
            'description' => 'تسجيل دخول ناجح للمدير',
            'ip_address'  => '127.0.0.1',
        ]);

        $controller = new \App\Http\Controllers\ActivityLogController();
        $response = $controller->index($this->makeRequest('/audit-logs'));

        $page = $response->toResponse(request())->getOriginalContent()->getData()['page'];
        $this->assertEquals('ActivityLogs/Index', $page['component']);
    }
}
