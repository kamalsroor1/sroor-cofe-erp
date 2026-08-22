<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create main store
        Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN',
            'is_main'   => true,
            'is_active' => true,
        ]);
    }

    public function test_api_login_validation_fails_when_fields_are_missing(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJson(['success' => false]);
    }

    public function test_api_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'phone'     => '01012345678',
            'password'  => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login'    => '01012345678',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJson(['success' => false]);
    }

    public function test_api_login_fails_for_inactive_account(): void
    {
        User::factory()->create([
            'phone'     => '01012345678',
            'password'  => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login'    => '01012345678',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_api_login_succeeds_and_returns_sanctum_token_and_user_profile(): void
    {
        $role = Role::create(['name' => 'admin']);
        $permission = Permission::create(['name' => 'pos.access']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012345678',
            'email'            => 'kamal@sroor.test',
            'password'         => Hash::make('secret123'),
            'is_active'        => true,
            'theme_preference' => 'dark',
        ]);
        $user->assignRole($role);

        $response = $this->postJson('/api/v1/auth/login', [
            'login'       => '01012345678',
            'password'    => 'secret123',
            'device_name' => 'test-spa',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'roles',
                        'permissions',
                        'theme_preference',
                    ],
                    'store',
                    'stores',
                    'system',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    'user' => [
                        'name'  => 'كمال سرور',
                        'phone' => '01012345678',
                    ],
                ],
            ]);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
    }

    public function test_api_me_returns_unauthorized_without_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_api_me_succeeds_with_valid_bearer_token(): void
    {
        $role = Role::create(['name' => 'admin']);
        $user = User::factory()->create([
            'name'      => 'كمال سرور',
            'phone'     => '01012345678',
            'password'  => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'name', 'phone', 'roles', 'permissions'],
                    'store',
                    'stores',
                    'system',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    'user' => [
                        'name' => 'كمال سرور',
                    ],
                ],
            ]);
    }

    public function test_api_logout_revokes_token_successfully(): void
    {
        $user = User::factory()->create([
            'name'      => 'كمال سرور',
            'phone'     => '01012345678',
            'password'  => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
