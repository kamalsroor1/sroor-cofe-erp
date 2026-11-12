<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SET-02: the shop-policy settings (`returns_max_days`, `pos_idle_lock_minutes`) through the
 * tenant settings endpoints + the bootstrap payload, and POST /auth/unlock-screen.
 */
final class ShopPoliciesApiTest extends TenantTestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    // ------------------------------------------------------------------ settings

    public function test_defaults_keep_current_behaviour(): void
    {
        $headers = $this->tenantHeaders($this->tenant);

        $this->getJson('/api/v1/settings', $headers)->assertOk()
            ->assertJsonPath('settings.returns_max_days', null)
            ->assertJsonPath('settings.pos_idle_lock_minutes', 0);

        $this->getJson('/api/v1/system/context', $headers)->assertOk()
            ->assertJsonPath('data.system.returns_max_days', null)
            ->assertJsonPath('data.system.pos_idle_lock_minutes', 0);
    }

    public function test_admin_saves_and_clears_the_policies_and_the_bootstrap_carries_them(): void
    {
        $headers = $this->tenantHeaders($this->tenant);

        $this->postJson('/api/v1/settings', $this->payload(['returns_max_days' => 14, 'pos_idle_lock_minutes' => 5]), $headers)->assertOk();

        $this->getJson('/api/v1/settings', $headers)->assertOk()
            ->assertJsonPath('settings.returns_max_days', 14)
            ->assertJsonPath('settings.pos_idle_lock_minutes', 5);
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($this->tenant, $cashier))->assertOk()
            ->assertJsonPath('data.system.returns_max_days', 14)
            ->assertJsonPath('data.system.pos_idle_lock_minutes', 5);

        // An older client that omits the keys keeps them; null / '' clears the window.
        $this->postJson('/api/v1/settings', $this->payload(), $headers)->assertOk();
        $this->getJson('/api/v1/settings', $headers)->assertJsonPath('settings.returns_max_days', 14);

        $this->postJson('/api/v1/settings', $this->payload(['returns_max_days' => null, 'pos_idle_lock_minutes' => 0]), $headers)->assertOk();
        $this->getJson('/api/v1/settings', $headers)
            ->assertJsonPath('settings.returns_max_days', null)
            ->assertJsonPath('settings.pos_idle_lock_minutes', 0);

        $this->postJson('/api/v1/settings', $this->payload(['returns_max_days' => '']), $headers)->assertOk();
        $this->inTenant($this->tenant, fn () => $this->assertNull(app(TenantSettings::class)->returnsMaxDays()));
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'zero window (use empty for no limit)' => ['returns_max_days', 0],
            'negative window' => ['returns_max_days', -1],
            'window above ten years' => ['returns_max_days', 3651],
            'fractional window' => ['returns_max_days', 1.5],
            'text window' => ['returns_max_days', 'week'],
            'negative idle minutes' => ['pos_idle_lock_minutes', -1],
            'idle minutes above four hours' => ['pos_idle_lock_minutes', 241],
            'fractional idle minutes' => ['pos_idle_lock_minutes', 2.5],
            'null idle minutes' => ['pos_idle_lock_minutes', null],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected(string $field, mixed $value): void
    {
        $this->postJson('/api/v1/settings', $this->payload([$field => $value]), $this->tenantHeaders($this->tenant))
            ->assertStatus(422)->assertJsonValidationErrors([$field]);
    }

    public function test_settings_need_authentication_and_settings_manage(): void
    {
        $this->postJson('/api/v1/settings', $this->payload(['returns_max_days' => 7]), $this->tenantGuestHeaders($this->tenant))->assertStatus(401);

        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->postJson('/api/v1/settings', $this->payload(['returns_max_days' => 7]), $this->tenantHeaders($this->tenant, $cashier))->assertStatus(403);
        $this->getJson('/api/v1/settings', $this->tenantHeaders($this->tenant, $cashier))->assertStatus(403);

        $this->inTenant($this->tenant, fn () => $this->assertNull(app(TenantSettings::class)->returnsMaxDays()));
    }

    public function test_policies_are_isolated_between_tenants(): void
    {
        $this->postJson('/api/v1/settings', $this->payload(['returns_max_days' => 3, 'pos_idle_lock_minutes' => 10]), $this->tenantHeaders($this->tenant))->assertOk();

        $other = $this->createTenant();
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($other))->assertOk()
            ->assertJsonPath('data.system.returns_max_days', null)
            ->assertJsonPath('data.system.pos_idle_lock_minutes', 0);
    }

    // ------------------------------------------------------------------ unlock

    public function test_unlock_with_the_correct_password_or_pin(): void
    {
        $user = $this->userWithPin('cashier', '482916');
        $headers = $this->tenantHeaders($this->tenant, $user);

        $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'password'], $headers)
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.user_id', (int) $user->id);
        $this->postJson('/api/v1/auth/unlock-screen', ['pin' => '482916'], $headers)->assertOk();
    }

    public function test_wrong_secrets_are_422_on_the_sent_field(): void
    {
        $user = $this->userWithPin('cashier', '482916');
        $headers = $this->tenantHeaders($this->tenant, $user);

        $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'wrong-one'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->postJson('/api/v1/auth/unlock-screen', ['pin' => '000000'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['pin'])
            ->assertJsonPath('errors.pin.0', __('screen_lock.pin_invalid'));

        // Another user's PIN never unlocks this user's screen.
        $this->userWithPin('admin', '135790');
        $this->postJson('/api/v1/auth/unlock-screen', ['pin' => '135790'], $headers)->assertStatus(422);

        // A user without a PIN can only use the password.
        $noPin = $this->createTenantUser($this->tenant, 'cashier');
        $this->postJson('/api/v1/auth/unlock-screen', ['pin' => '1234'], $this->tenantHeaders($this->tenant, $noPin))->assertStatus(422);
    }

    public function test_five_wrong_passwords_lock_the_password_path(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier');
        $headers = $this->tenantHeaders($this->tenant, $user);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'wrong-one'], $headers)->assertStatus(422);
        }

        // Now even the right password is refused until the lock expires.
        $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'password'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', __('auth.account_locked', ['minutes' => 15]));
    }

    public function test_five_wrong_pins_are_throttled(): void
    {
        $user = $this->userWithPin('cashier', '482916');
        $headers = $this->tenantHeaders($this->tenant, $user);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/unlock-screen', ['pin' => '000000'], $headers)->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/unlock-screen', ['pin' => '482916'], $headers)->assertStatus(429);
    }

    public function test_unlock_validation(): void
    {
        $headers = $this->tenantHeaders($this->tenant);

        $this->postJson('/api/v1/auth/unlock-screen', [], $headers)->assertStatus(422)->assertJsonValidationErrors(['password', 'pin']);
        $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'password', 'pin' => '1234'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->postJson('/api/v1/auth/unlock-screen', ['pin' => '12'], $headers)->assertStatus(422)->assertJsonValidationErrors(['pin']);
        $this->postJson('/api/v1/auth/unlock-screen', ['pin' => 1234], $headers)->assertStatus(422)->assertJsonValidationErrors(['pin']);
    }

    public function test_unlock_needs_a_token_of_this_tenant(): void
    {
        $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'password'], $this->tenantGuestHeaders($this->tenant))->assertStatus(401);

        // A token of tenant A sent to tenant B resolves to no user there.
        $other = $this->createTenant();
        $headers = $this->tenantHeaders($this->tenant);
        $headers['X-Tenant'] = (string) $other->getTenantKey();
        $headers['X-Store-Id'] = (string) $this->tenantStore($other)->id;
        $this->postJson('/api/v1/auth/unlock-screen', ['password' => 'password'], $headers)->assertStatus(401);
    }

    /** pos_pin is not mass assignable: set it the way PUT /profile/pos-pin stores it (hashed). */
    private function userWithPin(string $role, string $pin): User
    {
        $user = $this->createTenantUser($this->tenant, $role);
        $this->inTenant($this->tenant, fn () => User::query()->whereKey($user->id)->update(['pos_pin' => Hash::make($pin)]));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['company_name' => 'محل سياسات'], $overrides);
    }
}
