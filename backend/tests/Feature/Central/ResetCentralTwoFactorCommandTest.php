<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;
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

    private const REASON = 'ticket 42, identity checked by phone call';

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

        $this->artisan(self::COMMAND, ['email' => ' Lost-Phone@Central.test ', '--reason' => self::REASON])
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

        $this->artisan(self::COMMAND, ['email' => $user->email, '--reason' => self::REASON])
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

        $this->artisan(self::COMMAND, ['email' => $user->email, '--reason' => self::REASON])
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_identity'), 'no')
            ->assertFailed();

        $this->artisan(self::COMMAND, ['email' => $user->email, '--reason' => self::REASON])
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

        $this->artisan(self::COMMAND, ['email' => $user->email, '--reason' => self::REASON, '--no-interaction' => true])->assertFailed();
        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);

        $this->artisan(self::COMMAND, ['email' => 'nobody@central.test', '--reason' => self::REASON])
            ->expectsOutputToContain((string) __('console.reset_2fa.not_found'))
            ->assertFailed();
    }

    public function test_it_requires_a_reason_and_audits_it_with_the_os_user(): void
    {
        $user = $this->operatorWithTwoFactor();

        foreach ([[], ['--reason' => '   ']] as $options) {
            $this->artisan(self::COMMAND, array_merge(['email' => $user->email], $options))
                ->expectsOutputToContain((string) __('console.reset_2fa.reason_required'))
                ->assertFailed();
        }
        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);

        $this->artisan(self::COMMAND, ['email' => $user->email, '--reason' => '  '.self::REASON.'  '])
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_identity'), 'yes')
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_reset', ['email' => $user->email]), 'yes')
            ->assertSuccessful();

        $properties = CentralAuditLog::query()->where('event', CentralAuditEvent::TwoFactorReset->value)->sole()->properties;
        $this->assertSame(self::REASON, $properties['reason']);
        $expected = function_exists('posix_geteuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user();
        $this->assertSame($expected, $properties['performed_by']);
    }

    public function test_it_revokes_the_operators_central_web_sessions_only(): void
    {
        config(['session.driver' => 'database', 'session.encrypt' => false]);
        $user = $this->operatorWithTwoFactor();
        $other = CentralUser::factory()->create(['email' => 'other-op@central.test', 'is_active' => true]);
        $key = 'login_central_web_'.sha1(SessionGuard::class);
        $encode = static fn (array $data): string => base64_encode((string) json_encode($data));
        $connection = config('session.connection');
        $sessions = DB::connection(is_string($connection) ? $connection : null)->table((string) config('session.table'));
        $sessions->insert([
            ['id' => 'sess-target', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => $encode(['_token' => 'a', $key => $user->getKey()]), 'last_activity' => time()],
            ['id' => 'sess-other-op', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => $encode(['_token' => 'b', $key => $other->getKey()]), 'last_activity' => time()],
            ['id' => 'sess-guest', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => $encode(['_token' => 'c']), 'last_activity' => time()],
            ['id' => 'sess-garbage', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => '%%%not-base64', 'last_activity' => time()],
        ]);

        $this->artisan(self::COMMAND, ['email' => $user->email, '--reason' => self::REASON])
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_identity'), 'yes')
            ->expectsConfirmation((string) __('console.reset_2fa.confirm_reset', ['email' => $user->email]), 'yes')
            ->assertSuccessful();

        $this->assertSame(['sess-garbage', 'sess-guest', 'sess-other-op'], $sessions->orderBy('id')->pluck('id')->all());
        $properties = CentralAuditLog::query()->where('event', CentralAuditEvent::TwoFactorReset->value)->sole()->properties;
        $this->assertSame(1, $properties['revoked_web_sessions']);
        $this->assertSame(1, $properties['revoked_sessions']);
    }
}
