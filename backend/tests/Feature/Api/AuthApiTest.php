<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected Store $mainStore;
    protected Store $branchStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->mainStore = Store::create([
            'name'      => 'الفرع الرئيسي',
            'code'      => 'MAIN',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->branchStore = Store::create([
            'name'      => 'فرع المعادي',
            'code'      => 'MAADI',
            'is_main'   => false,
            'is_active' => true,
        ]);

        ActivityLog::query()->delete();
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

    public function test_api_login_succeeds_with_phone_and_returns_sanctum_token_and_user_profile(): void
    {
        $role = Role::findByName('admin');

        $user = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012345678',
            'email'            => 'kamal@sroor.test',
            'password'         => Hash::make('secret123'),
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
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

    public function test_api_login_succeeds_with_email(): void
    {
        $user = User::factory()->create([
            'name'             => 'أحمد محاسب',
            'phone'            => '01099991111',
            'email'            => 'ahmed@sroor.test',
            'password'         => Hash::make('secret123'),
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/v1/auth/login', [
            'login'       => 'ahmed@sroor.test',
            'password'    => 'secret123',
            'device_name' => 'desktop-spa',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'أحمد محاسب');
    }

    public function test_api_login_rate_limiting_throttles_after_too_many_failures(): void
    {
        User::factory()->create([
            'phone'     => '01088887777',
            'password'  => Hash::make('correct-pass'),
            'is_active' => true,
        ]);

        // Attempt 6 failed logins
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'login'    => '01088887777',
                'password' => 'wrong-pass',
            ]);
        }

        // 7th attempt should hit rate limit
        $response = $this->postJson('/api/v1/auth/login', [
            'login'    => '01088887777',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_api_me_returns_unauthorized_without_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_api_me_succeeds_with_valid_bearer_token(): void
    {
        $role = Role::findByName('admin');
        $user = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012345678',
            'password'         => Hash::make('secret123'),
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
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

    public function test_api_me_respects_x_store_id_header(): void
    {
        $user = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012345678',
            'password'         => Hash::make('secret123'),
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $user->assignRole('admin');

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Store-Id'    => (string) $this->branchStore->id,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.store.id', $this->branchStore->id)
            ->assertJsonPath('data.store.name', 'فرع المعادي');
    }

    public function test_api_logout_revokes_token_successfully(): void
    {
        $user = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012345678',
            'password'         => Hash::make('secret123'),
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
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

    public function test_quick_login_succeeds_without_password(): void
    {
        $user = User::factory()->create([
            'phone'            => '01055555555',
            'name'             => 'كاشير سريع',
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
        ]);

        $response = $this->postJson('/api/v1/auth/quick-login', [
            'login'       => '01055555555',
            'device_name' => 'pos-terminal',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'user' => [
                        'id'   => $user->id,
                        'name' => 'كاشير سريع',
                    ],
                ],
            ]);

        $this->assertNotNull($response->json('data.token'));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'api_quick_login',
        ]);
    }

    public function test_quick_login_fails_when_user_is_inactive(): void
    {
        User::factory()->create([
            'phone'     => '01066666666',
            'name'      => 'موظف موقف',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/quick-login', [
            'login' => '01066666666',
        ]);

        $response->assertStatus(422);
    }
}
