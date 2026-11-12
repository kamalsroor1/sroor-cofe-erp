<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * OPS-20: `central:reset-2fa {email}`: interactive double confirmation, wipes the second factor,
 * revokes sessions, audited; the next sign-in is sent to a fresh 2FA setup (password still required).
 */
final class ResetCentralTwoFactorCommandTest extends TenantTestCase
{
    private const COMMAND = 'central:reset-2fa';

    private const PASSWORD = 'fixtureOperatorPassword1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);
    }

    private function operatorWithTwoFactor(): CentralUser
    {
        $user = CentralUser::factory()->create([
            'email' => 'lost-phone@central.test',
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
        ]);
        $user->assignRole(CentralPermission::ROLE_SUPER_ADMIN);
        $user->forceFill([
            'two_factor_secret' => encrypt('SECRETSECRETSECR'),
            'two_factor_recovery_codes' => encrypt(json_encode(['aaaaaaaaaa-bbbbbbbbbb'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $user->createToken('laptop');

        return $user->refresh();
    }

    public function test_it_wipes_the_second_factor_revokes_sessions_and_audits(): void
    {
        $user = $this->operatorWithTwoFactor();

        $this->artisan(self::COMMAND, ['email' => ' Lost-Phone@Central.test '])
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_identity'), 'yes')
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_reset', ['email' => $user->email]), 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TwoFactorReset->value)->sole();
        $this->assertSame((string) $user->getKey(), (string) $log->subject_id);
        $this->assertStringNotContainsString('SECRETSECRET', (string) json_encode($log->properties));
    }

    public function test_after_the_reset_the_login_asks_for_a_new_2fa_setup(): void
    {
        $user = $this->operatorWithTwoFactor();

        $this->artisan(self::COMMAND, ['email' => $user->email])
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_identity'), 'yes')
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_reset', ['email' => $user->email]), 'yes')
            ->assertSuccessful();

        $this->postJson('/api/v1/super-admin/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'device_name' => 'ops-laptop',
        ])
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', false)
            ->assertJsonPath('data.two_factor_setup_required', true);
    }

    public function test_declining_either_confirmation_changes_nothing(): void
    {
        $user = $this->operatorWithTwoFactor();

        $this->artisan(self::COMMAND, ['email' => $user->email])
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_identity'), 'no')
            ->assertFailed();

        $this->artisan(self::COMMAND, ['email' => $user->email])
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_identity'), 'yes')
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_reset', ['email' => $user->email]), 'no')
            ->assertFailed();

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TwoFactorReset->value)->count());
    }

    public function test_it_refuses_without_interaction_and_for_an_unknown_email(): void
    {
        $user = $this->operatorWithTwoFactor();

        $this->artisan(self::COMMAND, ['email' => $user->email, '--no-interaction' => true])->assertFailed();
        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);

        $this->artisan(self::COMMAND, ['email' => 'nobody@central.test'])
            ->expectsOutputToContain((string) __('console.reset_2fa.not_found'))
            ->assertFailed();
    }
}
