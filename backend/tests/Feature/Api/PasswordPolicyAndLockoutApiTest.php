<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * SEC-05: minimum 8 characters on new/changed tenant-user passwords (login never enforces it),
 * and a per-account lockout (5 failures, any IP, 15 minutes) on the tenant login.
 */
final class PasswordPolicyAndLockoutApiTest extends TenantTestCase
{
    private const LOGIN = 'locked.user@sec05.test';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    // ── password policy ───────────────────────────────────────────────────

    public function test_user_create_rejects_a_seven_char_password_and_accepts_eight(): void
    {
        $headers = $this->tenantHeaders($this->tenant, $this->tenantAdmin($this->tenant));
        $storeId = (int) $this->tenantStore($this->tenant)->id;
        $payload = [
            'name' => 'موظف جديد',
            'phone' => '01000000051',
            'email' => 'new.sec05@sroor.test',
            'role' => 'cashier',
            'default_store_id' => $storeId,
            'is_active' => true,
        ];

        $this->postJson('/api/v1/users', $payload + ['password' => '1234567'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/v1/users', $payload + ['password' => '12345678'], $headers)
            ->assertStatus(201);
    }

    public function test_admin_reset_rejects_a_seven_char_password_and_accepts_eight(): void
    {
        $headers = $this->tenantHeaders($this->tenant, $this->tenantAdmin($this->tenant));
        $target = $this->createTenantUser($this->tenant, 'cashier');
        $payload = [
            'name' => 'موظف',
            'phone' => (string) $target->phone,
            'email' => (string) $target->email,
            'role' => 'cashier',
            'default_store_id' => (int) $this->tenantStore($this->tenant)->id,
            'is_active' => true,
        ];

        $this->putJson("/api/v1/users/{$target->id}", $payload + ['password' => '1234567'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->putJson("/api/v1/users/{$target->id}", $payload + ['password' => '12345678'], $headers)
            ->assertStatus(200);
    }

    public function test_own_profile_password_change_enforces_eight_chars(): void
    {
        $user = $this->createTenantUser($this->tenant, 'admin', attributes: ['password' => Hash::make('legacy')]);
        $headers = $this->tenantHeaders($this->tenant, $user);
        $payload = [
            'name' => 'مستخدم',
            'phone' => (string) $user->phone,
            'theme_preference' => 'light',
            'current_password' => 'legacy',
        ];

        $this->putJson('/api/v1/profile', $payload + ['new_password' => '1234567', 'new_password_confirmation' => '1234567'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);

        $this->putJson('/api/v1/profile', $payload + ['new_password' => '12345678', 'new_password_confirmation' => '12345678'], $headers)
            ->assertStatus(200);
    }

    public function test_password_length_message_is_translated(): void
    {
        $headers = $this->tenantHeaders($this->tenant, $this->tenantAdmin($this->tenant));

        $message = $this->postJson('/api/v1/users', ['password' => '1234567'], $headers)->json('errors.password.0');

        $this->assertIsString($message);
        $this->assertStringNotContainsString('validation.', $message);
    }

    public function test_legacy_short_password_can_still_log_in(): void
    {
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => self::LOGIN, 'password' => Hash::make('abc123')]);

        $this->tenantLogin($this->tenant, self::LOGIN, 'abc123')->assertStatus(200)->assertJsonPath('success', true);
    }

    // ── lockout ───────────────────────────────────────────────────────────

    public function test_five_failures_from_different_ips_lock_the_account_even_for_the_right_password(): void
    {
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => self::LOGIN]);

        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.{$i}"]);
            $this->tenantLogin($this->tenant, self::LOGIN, 'wrong-pass')
                ->assertStatus(422)
                ->assertJsonPath('errors.login.0', __('auth.failed'));
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.99']);
        $locked = $this->tenantLogin($this->tenant, self::LOGIN, 'password')->assertStatus(422);
        $this->assertSame(__('auth.account_locked', ['seconds' => 900, 'minutes' => 15]), $locked->json('errors.login.0'));
        $this->assertStringContainsString('15', (string) $locked->json('errors.login.0'));

        // Logged exactly once.
        $count = $this->inTenant($this->tenant, fn (): int => ActivityLog::query()->where('module', 'auth')->where('action', 'account_locked')->count());
        $this->assertSame(1, $count);
    }

    public function test_login_works_again_after_fifteen_minutes(): void
    {
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => self::LOGIN]);

        for ($i = 1; $i <= 5; $i++) {
            $this->tenantLogin($this->tenant, self::LOGIN, 'wrong-pass')->assertStatus(422);
        }
        $this->tenantLogin($this->tenant, self::LOGIN, 'password')->assertStatus(422);

        Carbon::setTestNow(now()->addMinutes(16));
        try {
            $this->tenantLogin($this->tenant, self::LOGIN, 'password')->assertStatus(200);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_successful_login_resets_the_failure_counter(): void
    {
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => self::LOGIN]);

        for ($i = 1; $i <= 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.2.0.{$i}"]);
            $this->tenantLogin($this->tenant, self::LOGIN, 'wrong-pass')->assertStatus(422);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.50']);
        $this->tenantLogin($this->tenant, self::LOGIN, 'password')->assertStatus(200);

        // Four more failures would have locked it had the counter not been cleared.
        for ($i = 1; $i <= 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.3.0.{$i}"]);
            $this->tenantLogin($this->tenant, self::LOGIN, 'wrong-pass')->assertStatus(422)
                ->assertJsonPath('errors.login.0', __('auth.failed'));
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.3.0.50']);
        $this->tenantLogin($this->tenant, self::LOGIN, 'password')->assertStatus(200);
    }

    public function test_unknown_logins_lock_the_same_way(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.4.0.{$i}"]);
            $this->tenantLogin($this->tenant, 'ghost@sec05.test', 'x')->assertStatus(422);
        }

        $this->tenantLogin($this->tenant, 'ghost@sec05.test', 'x')
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', __('auth.account_locked', ['seconds' => 900, 'minutes' => 15]));
    }

    public function test_lockout_is_isolated_per_account_and_per_tenant(): void
    {
        $other = $this->createTenant();
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => self::LOGIN]);
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => 'b.user@sec05.test']);
        $this->createTenantUser($other, 'cashier', attributes: ['email' => self::LOGIN]);

        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.5.0.{$i}"]);
            $this->tenantLogin($this->tenant, self::LOGIN, 'wrong-pass')->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.5.0.60']);
        $this->tenantLogin($this->tenant, self::LOGIN, 'password')->assertStatus(422);
        $this->tenantLogin($this->tenant, 'b.user@sec05.test', 'password')->assertStatus(200);
        $this->tenantLogin($other, self::LOGIN, 'password')->assertStatus(200);
    }

    public function test_lockout_is_case_insensitive_on_the_login(): void
    {
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => self::LOGIN]);

        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.6.0.{$i}"]);
            $this->tenantLogin($this->tenant, strtoupper(self::LOGIN), 'wrong-pass')->assertStatus(422);
        }

        $this->tenantLogin($this->tenant, self::LOGIN, 'password')->assertStatus(422);
    }

    public function test_invisible_characters_in_the_login_do_not_reset_the_lockout(): void
    {
        $this->createTenantUser($this->tenant, 'cashier', attributes: ['email' => self::LOGIN]);
        $variants = ["\u{200B}", "\u{00A0}", "\u{FEFF}", ' ', "\t"];

        foreach ($variants as $i => $padding) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.7.0.'.($i + 1)]);
            $this->tenantLogin($this->tenant, self::LOGIN.$padding, 'wrong-pass')->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.7.0.99']);
        $this->tenantLogin($this->tenant, self::LOGIN, 'password')
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', __('auth.account_locked', ['seconds' => 900, 'minutes' => 15]));
    }

    public function test_profile_current_password_guesses_are_capped(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: ['password' => Hash::make('real-pass-1')]);
        $headers = $this->tenantHeaders($this->tenant, $user);
        $payload = [
            'name' => 'مستخدم',
            'phone' => (string) $user->phone,
            'theme_preference' => 'light',
            'new_password' => 'new-pass-123',
            'new_password_confirmation' => 'new-pass-123',
        ];

        for ($i = 1; $i <= 5; $i++) {
            $this->putJson('/api/v1/profile', $payload + ['current_password' => "guess-{$i}"], $headers)
                ->assertStatus(422)
                ->assertJsonPath('errors.current_password.0', __('auth.current_password_incorrect'));
        }

        // The right password is refused while the budget is spent, and the password is unchanged.
        $this->putJson('/api/v1/profile', $payload + ['current_password' => 'real-pass-1'], $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', __('auth.account_locked', ['minutes' => 15]));
        $this->assertTrue(Hash::check('real-pass-1', (string) $this->inTenant($this->tenant, fn () => User::query()->findOrFail($user->id)->password)));

        $this->travel(16)->minutes();
        $this->putJson('/api/v1/profile', $payload + ['current_password' => 'real-pass-1'], $headers)->assertStatus(200);
    }

    /**
     * @return TestResponse<Response>
     */
    private function tenantLogin(Tenant $tenant, string $login, string $password): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', [
            'login' => $login,
            'password' => $password,
        ], ['X-Tenant' => (string) $tenant->getTenantKey()]);
    }
}
