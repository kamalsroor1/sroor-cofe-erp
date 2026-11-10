<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TenantTestCase;

/** SEC-03: 12h fixed token lifetime, and revocation on password / active / role changes. */
class SessionTokenLifecycleApiTest extends TenantTestCase
{
    private const LOGIN_PHONE = '01000009001';

    protected Tenant $tenant;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->adminHeaders = $this->tenantHeaders($this->tenant);
    }

    private function tokenCount(User $user): int
    {
        return $this->inTenant($this->tenant, fn (): int => $user->tokens()->count());
    }

    /** @return array<string, string> */
    private function headersWithFreshToken(User $user): array
    {
        return $this->tenantHeaders($this->tenant, $user);
    }

    private function loginAs(User $user): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $user->phone,
            'password' => 'secret123',
            'device_name' => 'spa',
        ], ['X-Tenant' => (string) $this->tenant->getTenantKey()]);
        $response->assertStatus(200);

        return (string) $response->json('data.token');
    }

    private function makeUser(string $role = 'cashier', array $attrs = []): User
    {
        return $this->createTenantUser($this->tenant, $role, attributes: array_merge([
            'phone' => self::LOGIN_PHONE,
            'password' => Hash::make('secret123'),
        ], $attrs));
    }

    /** @return array<string, mixed> */
    private function userPayload(User $u, array $overrides = []): array
    {
        return array_merge([
            'name' => $u->name,
            'phone' => $u->phone,
            'email' => $u->email,
            'role' => 'cashier',
            'default_store_id' => $u->default_store_id,
            'is_active' => true,
        ], $overrides);
    }

    public function test_login_token_expires_in_twelve_hours(): void
    {
        $user = $this->makeUser();
        $this->loginAs($user);

        $expiresAt = $this->inTenant($this->tenant, fn () => PersonalAccessToken::query()->latest('id')->firstOrFail()->expires_at);
        $this->assertNotNull($expiresAt);
        $this->assertEqualsWithDelta(now()->addHours(12)->timestamp, $expiresAt->timestamp, 5);
    }

    public function test_login_token_works_until_twelve_hours_and_is_not_renewed_on_use(): void
    {
        $user = $this->makeUser();
        $token = $this->loginAs($user);
        $headers = $this->tenantGuestHeaders($this->tenant) + ['Authorization' => 'Bearer '.$token];

        $this->travel(11)->hours();
        $this->travel(59)->minutes();
        $this->getJson('/api/v1/profile', $headers)->assertStatus(200);

        // Use above did not slide the expiry: two minutes later (12h01 after login) it is dead.
        $this->travel(2)->minutes();
        $this->getJson('/api/v1/profile', $headers)->assertStatus(401);
    }

    public function test_expired_token_is_rejected_with_401(): void
    {
        $user = $this->makeUser();
        $token = $this->loginAs($user);
        $headers = $this->tenantGuestHeaders($this->tenant) + ['Authorization' => 'Bearer '.$token];

        $this->travel(13)->hours();
        $this->getJson('/api/v1/profile', $headers)->assertStatus(401);
    }

    public function test_legacy_long_lived_token_dies_twelve_hours_after_creation(): void
    {
        $user = $this->makeUser();
        // Minted before SEC-03: 30-day expiry (or none) must still be capped at 12h from creation.
        $token = $this->inTenant($this->tenant, fn (): string => $user->createToken('legacy', ['*'], now()->addDays(30))->plainTextToken);
        $headers = $this->tenantGuestHeaders($this->tenant) + ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/profile', $headers)->assertStatus(200);

        $this->travel(12)->hours();
        $this->travel(1)->minutes();
        $this->getJson('/api/v1/profile', $headers)->assertStatus(401);
    }

    public function test_changing_own_password_revokes_other_tokens_and_keeps_the_current_one(): void
    {
        $user = $this->makeUser();
        $this->loginAs($user);
        $this->loginAs($user);
        $headers = $this->headersWithFreshToken($user);
        $this->assertSame(3, $this->tokenCount($user));

        $this->putJson('/api/v1/profile', [
            'name' => $user->name,
            'phone' => $user->phone,
            'theme_preference' => 'dark',
            'current_password' => 'secret123',
            'new_password' => 'brandnew123',
            'new_password_confirmation' => 'brandnew123',
        ], $headers)->assertStatus(200);

        $this->assertSame(1, $this->tokenCount($user));
        $this->getJson('/api/v1/profile', $headers)->assertStatus(200);
    }

    public function test_profile_update_without_password_change_keeps_all_tokens(): void
    {
        $user = $this->makeUser();
        $this->loginAs($user);
        $headers = $this->headersWithFreshToken($user);

        $this->putJson('/api/v1/profile', [
            'name' => 'اسم جديد',
            'phone' => $user->phone,
            'theme_preference' => 'light',
        ], $headers)->assertStatus(200);

        $this->assertSame(2, $this->tokenCount($user));
    }

    public function test_wrong_current_password_is_422_and_revokes_nothing(): void
    {
        $user = $this->makeUser();
        $this->loginAs($user);
        $headers = $this->headersWithFreshToken($user);

        $this->putJson('/api/v1/profile', [
            'name' => $user->name,
            'phone' => $user->phone,
            'theme_preference' => 'dark',
            'current_password' => 'nope',
            'new_password' => 'brandnew123',
            'new_password_confirmation' => 'brandnew123',
        ], $headers)->assertStatus(422);

        $this->assertSame(2, $this->tokenCount($user));
    }

    public function test_admin_password_reset_revokes_all_target_tokens(): void
    {
        $target = $this->makeUser();
        $token = $this->loginAs($target);
        $targetHeaders = $this->tenantGuestHeaders($this->tenant) + ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/v1/profile', $targetHeaders)->assertStatus(200);

        $this->putJson("/api/v1/users/{$target->id}", $this->userPayload($target, ['password' => 'resetpass1']), $this->adminHeaders)
            ->assertStatus(200);

        $this->assertSame(0, $this->tokenCount($target));
        $this->getJson('/api/v1/profile', $targetHeaders)->assertStatus(401);
    }

    public function test_deactivation_via_update_revokes_tokens(): void
    {
        $target = $this->makeUser();
        $this->loginAs($target);

        $this->putJson("/api/v1/users/{$target->id}", $this->userPayload($target, ['is_active' => false]), $this->adminHeaders)
            ->assertStatus(200);

        $this->assertSame(0, $this->tokenCount($target));
    }

    public function test_deactivation_via_toggle_revokes_tokens_but_reactivation_does_not_touch_them(): void
    {
        $target = $this->makeUser();
        $this->loginAs($target);

        $this->patchJson("/api/v1/users/{$target->id}/toggle-active", [], $this->adminHeaders)->assertStatus(200);
        $this->assertSame(0, $this->tokenCount($target));

        // Re-activate, log in again, then deactivate+reactivate path: reactivation alone keeps new tokens.
        $this->patchJson("/api/v1/users/{$target->id}/toggle-active", [], $this->adminHeaders)->assertStatus(200);
        $this->loginAs($target);
        $this->assertSame(1, $this->tokenCount($target));
    }

    public function test_role_change_revokes_tokens(): void
    {
        $target = $this->makeUser();
        $this->loginAs($target);

        $this->putJson("/api/v1/users/{$target->id}", $this->userPayload($target, ['role' => 'accountant']), $this->adminHeaders)
            ->assertStatus(200);

        $this->assertSame(0, $this->tokenCount($target));
    }

    public function test_update_without_password_role_or_active_change_keeps_tokens(): void
    {
        $target = $this->makeUser();
        $this->loginAs($target);

        $this->putJson("/api/v1/users/{$target->id}", $this->userPayload($target, ['name' => 'اسم آخر']), $this->adminHeaders)
            ->assertStatus(200);

        $this->assertSame(1, $this->tokenCount($target));
    }

    public function test_admin_reset_requires_users_manage_permission(): void
    {
        $target = $this->makeUser();
        $this->loginAs($target);
        $plain = $this->createTenantUser($this->tenant, attributes: ['phone' => '01000009002']);

        $this->putJson("/api/v1/users/{$target->id}", $this->userPayload($target, ['password' => 'resetpass1']), $this->headersWithFreshToken($plain))
            ->assertStatus(403);

        $this->assertSame(1, $this->tokenCount($target));
    }

    public function test_admin_reset_validation_still_returns_422_and_revokes_nothing(): void
    {
        $target = $this->makeUser();
        $this->loginAs($target);

        $this->putJson("/api/v1/users/{$target->id}", $this->userPayload($target, ['role' => '']), $this->adminHeaders)
            ->assertStatus(422);

        $this->assertSame(1, $this->tokenCount($target));
    }

    public function test_unauthenticated_reset_is_401(): void
    {
        $target = $this->makeUser();

        $this->putJson("/api/v1/users/{$target->id}", $this->userPayload($target), $this->tenantGuestHeaders($this->tenant))
            ->assertStatus(401);
    }

    public function test_tokens_of_another_tenant_are_untouched(): void
    {
        $other = $this->createTenant();
        $otherUser = $this->createTenantUser($other, 'cashier');
        $this->tenantToken($other, $otherUser);

        $target = $this->makeUser();
        $this->putJson("/api/v1/users/{$target->id}", $this->userPayload($target, ['password' => 'resetpass1']), $this->adminHeaders)
            ->assertStatus(200);

        $this->assertSame(1, $this->inTenant($other, fn (): int => $otherUser->tokens()->count()));
    }
}
