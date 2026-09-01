<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name'      => 'الفرع الرئيسي',
            'code'      => 'MAIN-001',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->user = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'email'            => 'kamal@sroor.com',
            'password'         => Hash::make('password123'),
            'theme_preference' => 'dark',
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->user->assignRole($adminRole);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/profile');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'               => $this->user->id,
                    'name'             => 'كمال سرور',
                    'phone'            => '01012316954',
                    'email'            => 'kamal@sroor.com',
                    'theme_preference' => 'dark',
                ],
            ]);
    }

    public function test_authenticated_user_can_update_profile_info(): void
    {
        $payload = [
            'name'             => 'كمال سرور المهندس',
            'phone'            => '01012316954',
            'email'            => 'kamal.dev@sroor.com',
            'theme_preference' => 'light',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/profile', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'             => 'كمال سرور المهندس',
                    'email'            => 'kamal.dev@sroor.com',
                    'theme_preference' => 'light',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id'               => $this->user->id,
            'name'             => 'كمال سرور المهندس',
            'theme_preference' => 'light',
        ]);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $payload = [
            'name'                  => 'كمال سرور',
            'phone'                 => '01012316954',
            'theme_preference'      => 'dark',
            'current_password'      => 'password123',
            'new_password'          => 'newSecretPass123',
            'new_password_confirmation' => 'newSecretPass123',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/profile', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->user->refresh();
        $this->assertTrue(Hash::check('newSecretPass123', $this->user->password));
    }

    public function test_update_profile_fails_on_wrong_current_password(): void
    {
        $payload = [
            'name'                  => 'كمال سرور',
            'phone'                 => '01012316954',
            'theme_preference'      => 'dark',
            'current_password'      => 'wrongPassword',
            'new_password'          => 'newSecretPass123',
            'new_password_confirmation' => 'newSecretPass123',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/profile', $payload);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_update_profile_fails_validation_on_duplicate_phone(): void
    {
        User::factory()->create([
            'phone' => '01099998888',
        ]);

        $payload = [
            'name'             => 'كمال سرور',
            'phone'            => '01099998888',
            'theme_preference' => 'dark',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/profile', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }
}
