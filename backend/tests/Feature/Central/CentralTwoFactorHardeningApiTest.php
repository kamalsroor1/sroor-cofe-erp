<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TenantTestCase;

/**
 * W2 batch 2 security hardening of the operator (central) 2FA flow:
 *
 *  - GET /two-factor/recovery-codes: an operator with CONFIRMED 2FA needs a recent step-up on
 *    the current token (403 central_auth.step_up_required otherwise); the setup token may still
 *    read them during enrollment; every read is audited `recovery_codes_viewed`; throttled.
 *  - a per-operator HOURLY cap on 2FA attempts across the challenge and step-up endpoints, on
 *    top of the per-minute limiter, so new challenges / new IPs never reset the budget.
 *  - password reset burns the operator's pending login challenges.
 *  - forgot-password does the broker's expensive work for unknown emails too.
 */
final class CentralTwoFactorHardeningApiTest extends TenantTestCase
{
    private const LOGIN = '/api/v1/super-admin/auth/login';

    private const CHALLENGE = '/api/v1/super-admin/auth/two-factor-challenge';

    private const RECOVERY_CODES = '/api/v1/super-admin/auth/two-factor/recovery-codes';

    private const STEP_UP = '/api/v1/super-admin/auth/step-up';

    private const ENABLE = '/api/v1/super-admin/auth/two-factor/enable';

    private const FORGOT = '/api/v1/super-admin/auth/forgot-password';

    private const RESET = '/api/v1/super-admin/auth/reset-password';

    // Fake operator passwords; the `fixture` prefix marks them as fake for gitleaks (.gitleaks.toml).
    private const PASSWORD = 'fixtureCentralHardeningPassword';

    private const NEW_PASSWORD = 'fixtureCentralHardeningNewPassword';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);
    }

    // ---------------------------------------------------------------- recovery codes

    public function test_recovery_codes_of_a_confirmed_operator_require_a_recent_step_up_and_are_audited(): void
    {
        [$user, $secret] = $this->operatorWithTwoFactor();
        $token = (string) $this->completeChallenge($this->challengeFor($user), ['code' => $this->otp($secret)])
            ->assertOk()
            ->json('data.token');

        // Fresh full token (2FA proven at sign-in): readable, audited.
        $this->withHeaders($this->bearer($token))->getJson(self::RECOVERY_CODES)
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::RecoveryCodesViewed));
        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::RecoveryCodesViewed->value)->sole();
        $this->assertSame('confirmed', $log->properties['phase'] ?? null);
        $this->assertStringNotContainsString($user->recoveryCodes()[0], (string) json_encode($log->properties));

        // 16 minutes later the token alone (e.g. stolen) is not enough.
        $this->travel(16)->minutes();
        $this->withHeaders($this->bearer($token))->getJson(self::RECOVERY_CODES)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'central_auth.step_up_required')
            ->assertJsonMissingPath('data.recovery_codes');
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::RecoveryCodesViewed));

        $this->withHeaders($this->bearer($token))->postJson(self::STEP_UP, ['code' => $this->otp($secret)])->assertOk();
        $this->withHeaders($this->bearer($token))->getJson(self::RECOVERY_CODES)->assertOk();
        $this->assertSame(2, $this->auditCount(CentralAuditEvent::RecoveryCodesViewed));
    }

    public function test_setup_token_still_reads_recovery_codes_during_enrollment_and_the_read_is_audited(): void
    {
        $user = $this->operator();
        $setupToken = (string) $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertOk()
            ->json('data.token');
        $this->withHeaders($this->bearer($setupToken))->postJson(self::ENABLE)->assertOk();

        $this->withHeaders($this->bearer($setupToken))->getJson(self::RECOVERY_CODES)->assertOk()->assertJsonCount(8, 'data.recovery_codes');

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::RecoveryCodesViewed->value)->sole();
        $this->assertSame('enrollment', $log->properties['phase'] ?? null);
        $this->assertSame((int) $user->getKey(), (int) $log->causer_id);
    }

    public function test_recovery_codes_route_is_throttled(): void
    {
        $route = Route::getRoutes()->getByName('central.auth.two-factor.recovery-codes');

        $this->assertNotNull($route);
        $this->assertContains('throttle:central-two-factor', $route->gatherMiddleware());
    }

    public function test_recovery_codes_viewed_event_has_ar_and_en_labels(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $label = CentralAuditEvent::RecoveryCodesViewed->label($locale);
            $this->assertNotSame(CentralAuditEvent::RecoveryCodesViewed->translationKey(), $label, "{$locale} label missing");
        }
    }

    // ---------------------------------------------------------------- hourly cap

    public function test_hourly_per_operator_cap_spans_challenge_and_step_up_and_survives_new_challenges(): void
    {
        config([
            'central.rate_limits.two_factor_per_user_per_hour' => 3,
            'central.rate_limits.two_factor_per_subject_per_minute' => 100,
            'central.rate_limits.two_factor_per_ip_per_minute' => 100,
        ]);

        [$user, $secret] = $this->operatorWithTwoFactor();
        $token = (string) $this->completeChallenge($this->challengeFor($user), ['code' => $this->otp($secret)])
            ->assertOk()
            ->json('data.token');

        // 1 (the sign-in above) + 2 wrong step-ups = 3 attempts this hour.
        $wrong = $this->wrongOtp($secret);
        $this->withHeaders($this->bearer($token))->postJson(self::STEP_UP, ['code' => $wrong])->assertStatus(422);
        $this->withHeaders($this->bearer($token))->postJson(self::STEP_UP, ['code' => $wrong])->assertStatus(422);
        $this->withHeaders($this->bearer($token))->postJson(self::STEP_UP, ['code' => $wrong])->assertStatus(429);

        // A brand-new challenge (fresh password login) does not reset the operator's budget.
        $this->completeChallenge($this->challengeFor($user), ['code' => $wrong])->assertStatus(429);

        // Another operator is unaffected.
        [$other, $otherSecret] = $this->operatorWithTwoFactor();
        $this->completeChallenge($this->challengeFor($other), ['code' => $this->otp($otherSecret)])->assertOk();

        // The window is an hour, not a minute.
        $this->travel(2)->minutes();
        $this->withHeaders($this->bearer($token))->postJson(self::STEP_UP, ['code' => $wrong])->assertStatus(429);
        $this->travel(61)->minutes();
        $this->completeChallenge($this->challengeFor($user), ['code' => $this->otp($secret, 1)])->assertOk();
    }

    // ---------------------------------------------------------------- password reset

    public function test_password_reset_burns_pending_login_challenges(): void
    {
        [$user, $secret] = $this->operatorWithTwoFactor();
        $pending = $this->challengeFor($user);

        $resetToken = Password::broker('central_users')->createToken($user);
        $this->postJson(self::RESET, [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        // The challenge obtained with the OLD password can no longer be completed.
        $this->completeChallenge($pending, ['code' => $this->otp($secret)])
            ->assertStatus(422)
            ->assertJsonPath('errors.challenge_id.0', __('central_auth.two_factor_challenge_invalid'));

        // A challenge started after the reset (new password) works.
        $fresh = (string) $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::NEW_PASSWORD])
            ->assertOk()
            ->json('data.challenge_id');
        $this->completeChallenge($fresh, ['code' => $this->otp($secret, 1)])->assertOk();
    }

    public function test_forgot_password_does_the_broker_hashing_for_unknown_emails_and_answers_the_same(): void
    {
        Mail::fake();
        config(['central.password_reset_url' => 'https://console.platform.test/reset']);
        $user = $this->operator();

        $known = $this->postJson(self::FORGOT, ['email' => $user->email])->assertOk()->json();

        Hash::spy();
        $unknown = $this->postJson(self::FORGOT, ['email' => 'nobody-'.uniqid().'@central.test'])->assertOk()->json();

        $this->assertSame($known, $unknown);
        Hash::shouldHaveReceived('make')->once();
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, mixed> $attributes */
    private function operator(array $attributes = []): CentralUser
    {
        $user = CentralUser::factory()->create(array_merge([
            'email' => 'operator-'.uniqid().'@central.test',
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
        ], $attributes));
        $user->assignRole(CentralPermission::ROLE_SUPER_ADMIN);

        return $user->refresh();
    }

    /** @return array{CentralUser, string} */
    private function operatorWithTwoFactor(): array
    {
        $user = $this->operator();
        app(EnableTwoFactorAuthentication::class)($user, true);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return [$user->refresh(), (string) Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret)];
    }

    private function otp(string $secret, int $offset = 0): string
    {
        $engine = app(Google2FA::class);

        return $engine->oathTotp($secret, (int) $engine->getTimestamp() + $offset);
    }

    private function wrongOtp(string $secret): string
    {
        $valid = [$this->otp($secret, -1), $this->otp($secret), $this->otp($secret, 1)];

        for ($candidate = 0; ; $candidate++) {
            $code = str_pad((string) $candidate, 6, '0', STR_PAD_LEFT);
            if (! in_array($code, $valid, true)) {
                return $code;
            }
        }
    }

    /** @return array<string, string> */
    private function bearer(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }

    private function challengeFor(CentralUser $user): string
    {
        return (string) $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::PASSWORD, 'device_name' => 'ops-laptop'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->json('data.challenge_id');
    }

    /**
     * @param  array<string, string>  $factor
     * @return TestResponse<JsonResponse>
     */
    private function completeChallenge(string $challengeId, array $factor): TestResponse
    {
        return $this->postJson(self::CHALLENGE, array_merge(['challenge_id' => $challengeId], $factor));
    }

    private function auditCount(CentralAuditEvent $event): int
    {
        return CentralAuditLog::query()->where('event', $event->value)->count();
    }
}
