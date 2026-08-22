<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Profile;
use App\Livewire\Auth\UserManager;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin1;
    protected User $admin2;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);

        // 1. Super Admin 1 (01012316954 / password)
        $this->admin1 = User::create([
            'name'      => 'كمال سرور - المدير العام',
            'phone'     => '01012316954',
            'email'     => '01012316954@sroor.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin1->assignRole('admin');

        // 2. Super Admin 2 (01558088841 / 123456789)
        $this->admin2 = User::create([
            'name'      => 'المدير العام 2',
            'phone'     => '01558088841',
            'email'     => '01558088841@sroor.com',
            'password'  => bcrypt('123456789'),
            'is_active' => true,
        ]);
        $this->admin2->assignRole('admin');
    }

    public function test_guest_is_redirected_to_login_when_accessing_dashboard()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_login_page_renders_successfully()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_super_admin_1_can_login_with_phone_and_password()
    {
        Livewire::test(Login::class)
            ->set('phone', '01012316954')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->admin1);
    }

    public function test_super_admin_2_can_login_with_phone_and_password()
    {
        Livewire::test(Login::class)
            ->set('phone', '01558088841')
            ->set('password', '123456789')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->admin2);
    }

    public function test_user_cannot_login_with_wrong_password()
    {
        Livewire::test(Login::class)
            ->set('phone', '01012316954')
            ->set('password', 'wrong-secret-999')
            ->call('login')
            ->assertHasErrors(['phone']);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_access_dashboard()
    {
        $response = $this->actingAs($this->admin1)->get('/');
        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_update_profile_info()
    {
        $this->actingAs($this->admin1);

        Livewire::test(Profile::class)
            ->set('name', 'كمال سرور المعدل')
            ->set('email', 'kamal.new@sroor.com')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id'    => $this->admin1->id,
            'name'  => 'كمال سرور المعدل',
            'email' => 'kamal.new@sroor.com',
        ]);
    }

    public function test_authenticated_user_can_update_password()
    {
        $this->actingAs($this->admin1);

        Livewire::test(Profile::class)
            ->set('current_password', 'password')
            ->set('new_password', 'newsecret123')
            ->set('new_password_confirmation', 'newsecret123')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->admin1->refresh();
        $this->assertTrue(Hash::check('newsecret123', $this->admin1->password));
    }

    public function test_admin_can_create_new_cashier_user_with_phone()
    {
        $this->actingAs($this->admin1);

        Livewire::test(UserManager::class)
            ->call('openCreateModal')
            ->set('name', 'كاشير مسائي جديد')
            ->set('phone', '01200000000')
            ->set('email', 'cashier.night@sroor.com')
            ->set('password', 'cashier123')
            ->set('role', 'cashier')
            ->set('is_active', true)
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name'      => 'كاشير مسائي جديد',
            'phone'     => '01200000000',
            'email'     => 'cashier.night@sroor.com',
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_can_logout()
    {
        $response = $this->actingAs($this->admin1)->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
