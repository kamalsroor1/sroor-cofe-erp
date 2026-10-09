<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

class ProfileApiTest extends TenantTestCase
{
    private const PROFILE_PHONE = '01000007500';

    protected Tenant $tenant;

    protected User $user;

    /** @var array<string, string> */
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        // The profile owner: an explicit admin with known credentials and preferences.
        $this->user = $this->createTenantUser($this->tenant, 'admin', attributes: [
            'name' => 'كمال سرور',
            'phone' => self::PROFILE_PHONE,
            'email' => 'kamal@sroor.com',
            'password' => Hash::make('password123'),
            'theme_preference' => 'dark',
        ]);
        $this->headers = $this->tenantHeaders($this->tenant, $this->user);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/profile', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $response = $this->getJson('/api/v1/profile', $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->user->id,
                    'name' => 'كمال سرور',
                    'phone' => self::PROFILE_PHONE,
                    'email' => 'kamal@sroor.com',
                    'theme_preference' => 'dark',
                ],
            ]);
    }

    public function test_authenticated_user_can_update_profile_info(): void
    {
        $payload = [
            'name' => 'كمال سرور المهندس',
            'phone' => self::PROFILE_PHONE,
            'email' => 'kamal.dev@sroor.com',
            'theme_preference' => 'light',
        ];

        $response = $this->putJson('/api/v1/profile', $payload, $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'كمال سرور المهندس',
                    'email' => 'kamal.dev@sroor.com',
                    'theme_preference' => 'light',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'كمال سرور المهندس',
            'theme_preference' => 'light',
        ]));
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $payload = [
            'name' => 'كمال سرور',
            'phone' => self::PROFILE_PHONE,
            'theme_preference' => 'dark',
            'current_password' => 'password123',
            'new_password' => 'newSecretPass123',
            'new_password_confirmation' => 'newSecretPass123',
        ];

        $response = $this->putJson('/api/v1/profile', $payload, $this->headers);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $hash = $this->inTenant($this->tenant, fn (): string => (string) User::findOrFail($this->user->id)->password);
        $this->assertTrue(Hash::check('newSecretPass123', $hash));
    }

    public function test_update_profile_fails_on_wrong_current_password(): void
    {
        $payload = [
            'name' => 'كمال سرور',
            'phone' => self::PROFILE_PHONE,
            'theme_preference' => 'dark',
            'current_password' => 'wrongPassword',
            'new_password' => 'newSecretPass123',
            'new_password_confirmation' => 'newSecretPass123',
        ];

        $response = $this->putJson('/api/v1/profile', $payload, $this->headers);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_update_profile_fails_validation_on_duplicate_phone(): void
    {
        $this->createTenantUser($this->tenant, attributes: ['phone' => '01000007005']);

        $payload = [
            'name' => 'كمال سرور',
            'phone' => '01000007005',
            'theme_preference' => 'dark',
        ];

        $response = $this->putJson('/api/v1/profile', $payload, $this->headers);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_phone_uniqueness_and_profile_updates_are_scoped_to_the_tenant(): void
    {
        $other = $this->createTenant();
        $otherUser = $this->createTenantUser($other, attributes: [
            'name' => 'مستخدم مستأجر آخر',
            'phone' => '01000007006',
        ]);

        // The same phone in ANOTHER tenant's DB is not a duplicate here.
        $this->putJson('/api/v1/profile', [
            'name' => 'كمال سرور',
            'phone' => '01000007006',
            'theme_preference' => 'dark',
        ], $this->headers)->assertOk()->assertJsonPath('data.phone', '01000007006');

        // The other tenant's user is untouched, and B's profile endpoint shows B's user only.
        $this->inTenant($other, fn () => $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'name' => 'مستخدم مستأجر آخر',
            'phone' => '01000007006',
        ]));
        $this->getJson('/api/v1/profile', $this->tenantHeaders($other, $otherUser))
            ->assertOk()
            ->assertJsonPath('data.name', 'مستخدم مستأجر آخر');
    }
}
