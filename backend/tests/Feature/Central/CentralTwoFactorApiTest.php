<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Http\Middleware\AuthenticateCentral;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\RequireRecentTwoFactor;
use App\Models\CentralAuditLog;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TenantTestCase;

/**
 * IDEN-1.12: mandatory TOTP 2FA for platform operators.
 *
 *  - POST /api/v1/super-admin/auth/two-factor-challenge      challenge + code => full token
 *  - POST /api/v1/super-admin/auth/two-factor/enable         setup or full token
 *  - POST /api/v1/super-admin/auth/two-factor/confirm        setup or full token
 *  - GET  /api/v1/super-admin/auth/two-factor/recovery-codes setup or full token
 *  - POST /api/v1/super-admin/auth/step-up                   full token only
 *  - RequireRecentTwoFactor (exercised through a probe route registered by this test)
 */
final class CentralTwoFactorApiTest extends TenantTestCase
{
    private const LOGIN = '/api/v1/super-admin/auth/login';

    private const CHALLENGE = '/api/v1/super-admin/auth/two-factor-challenge';

    private const ENABLE = '/api/v1/super-admin/auth/two-factor/enable';

    private const CONFIRM = '/api/v1/super-admin/auth/two-factor/confirm';

    private const RECOVERY_CODES = '/api/v1/super-admin/auth/two-factor/recovery-codes';

    private const STEP_UP = '/api/v1/super-admin/auth/step-up';

    private const ME = '/api/v1/super-admin/auth/me';

    private const LOGOUT = '/api/v1/super-admin/auth/logout';

    private const PROBE = '/api/v1/super-admin/test-step-up-probe';

    // Fake operator password; the `fixture` prefix marks it as fake for gitleaks (.gitleaks.toml).
    private const PASSWORD = 'fixtureCentralTwoFactorPassword';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);

        // The step-up hook is not used by any shipped route yet: probe it here.
        Route::middleware(['api', EnsureCentralContext::class, AuthenticateCentral::class, RequireRecentTwoFactor::class])
            ->get(self::PROBE, static fn () => response()->json(['success' => true]));
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

    /**
     * An operator with confirmed 2FA, set up through Fortify's own action.
     *
     * @return array{CentralUser, string} the operator and the plain TOTP secret
     */
    private function operatorWithTwoFactor(): array
    {
        $user = $this->operator();
        app(EnableTwoFactorAuthentication::class)($user, true);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return [$user->refresh(), (string) Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret)];
    }

    /** A valid TOTP for $secret, $offset 30-second steps from now (the window accepts 0 and +1). */
    private function otp(string $secret, int $offset = 0): string
    {
        $engine = app(Google2FA::class);

        return $engine->oathTotp($secret, (int) $engine->getTimestamp() + $offset);
    }

    /** A 6-digit code that is NOT valid for $secret in the accepted window. */
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

    private function challengeFor(CentralUser $user, string $deviceName = 'ops-laptop'): string
    {
        return (string) $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::PASSWORD, 'device_name' => $deviceName])
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->json('data.challenge_id');
    }

    private function setupTokenFor(CentralUser $user): string
    {
        return (string) $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.two_factor_setup_required', true)
            ->json('data.token');
    }

    /** @return TestResponse<JsonResponse> */
    private function completeChallenge(string $challengeId, array $factor): TestResponse
    {
        return $this->postJson(self::CHALLENGE, array_merge(['challenge_id' => $challengeId], $factor));
    }

    private function auditCount(CentralAuditEvent $event): int
    {
        return CentralAuditLog::query()->where('event', $event->value)->count();
    }

    // ---------------------------------------------------------------- setup flow

    public function test_full_setup_flow_enable_confirm_issues_a_full_token_and_revokes_the_setup_token(): void
    {
        $this->freezeSecond();
        $user = $this->operator();
        $setupToken = $this->setupTokenFor($user);

        $enable = $this->withHeaders($this->bearer($setupToken))
            ->postJson(self::ENABLE)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('central_auth.two_factor_enabled'))
            ->assertJsonStructure(['data' => ['secret', 'otpauth_url', 'qr_code_svg']]);

        $secret = (string) $enable->json('data.secret');
        $this->assertStringStartsWith('otpauth://totp/', (string) $enable->json('data.otpauth_url'));
        $this->assertStringContainsString('<svg', (string) $enable->json('data.qr_code_svg'));
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::TwoFactorEnabled));

        $user->refresh();
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNotSame($secret, $user->two_factor_secret, 'the secret is stored encrypted');

        // Recovery codes are readable with the setup token before confirmation.
        $this->withHeaders($this->bearer($setupToken))
            ->getJson(self::RECOVERY_CODES)
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');

        $confirm = $this->withHeaders($this->bearer($setupToken))
            ->postJson(self::CONFIRM, ['code' => $this->otp($secret)])
            ->assertOk()
            ->assertJsonPath('message', __('central_auth.two_factor_confirmed'))
            ->assertJsonPath('data.two_factor_required', false)
            ->assertJsonPath('data.two_factor_setup_required', false)
            ->assertJsonPath('data.abilities', ['central:*'])
            ->assertJsonPath('data.user.two_factor_enabled', true)
            ->assertJsonCount(8, 'data.recovery_codes');

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::TwoFactorConfirmed));

        // Exactly one token left: the new full one, verified now, with the normal TTL.
        $token = CentralPersonalAccessToken::query()->sole();
        $this->assertSame(['central:*'], $token->abilities);
        $this->assertTrue($token->two_factor_verified_at?->equalTo(now()));
        $this->assertTrue($token->expires_at?->equalTo(now()->addMinutes(240)));

        $this->withHeaders($this->bearer($setupToken))->getJson(self::RECOVERY_CODES)->assertUnauthorized();
        $this->withHeaders($this->bearer((string) $confirm->json('data.token')))
            ->getJson(self::ME)
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);
    }

    public function test_confirm_with_a_wrong_code_is_422_audited_and_changes_nothing(): void
    {
        $user = $this->operator();
        $setupToken = $this->setupTokenFor($user);
        $secret = (string) $this->withHeaders($this->bearer($setupToken))->postJson(self::ENABLE)->json('data.secret');

        $this->withHeaders($this->bearer($setupToken))
            ->postJson(self::CONFIRM, ['code' => $this->wrongOtp($secret)])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', __('central_auth.two_factor_invalid'));

        $this->assertNull($user->refresh()->two_factor_confirmed_at);
        $this->assertSame(['central:2fa-setup'], CentralPersonalAccessToken::query()->sole()->abilities);
        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TwoFactorChallengeFailed->value)->sole();
        $this->assertSame('confirm', $log->properties['context'] ?? null);
        $this->assertSame(0, $this->auditCount(CentralAuditEvent::TwoFactorConfirmed));
    }

    public function test_confirm_validation_requires_a_code(): void
    {
        $setupToken = $this->setupTokenFor($this->operator());

        $this->withHeaders($this->bearer($setupToken))
            ->postJson(self::CONFIRM, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_confirm_or_recovery_codes_before_enable_is_409(): void
    {
        $setupToken = $this->setupTokenFor($this->operator());

        $this->withHeaders($this->bearer($setupToken))
            ->postJson(self::CONFIRM, ['code' => '123456'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'central_auth.two_factor_not_enabled');

        $this->withHeaders($this->bearer($setupToken))
            ->getJson(self::RECOVERY_CODES)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'central_auth.two_factor_not_enabled');
    }

    public function test_enable_or_confirm_after_confirmation_is_409_and_keeps_the_secret(): void
    {
        [$user] = $this->operatorWithTwoFactor();
        $storedSecret = $user->two_factor_secret;
        $token = $user->createToken('full')->plainTextToken;

        $this->withHeaders($this->bearer($token))
            ->postJson(self::ENABLE)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'central_auth.two_factor_already_confirmed');

        $this->withHeaders($this->bearer($token))
            ->postJson(self::CONFIRM, ['code' => '123456'])
            ->assertStatus(409);

        $this->assertSame($storedSecret, $user->refresh()->two_factor_secret);
    }

    /** @return array<string, array{string, string}> */
    public static function fullTokenRoutes(): array
    {
        return [
            'me' => ['GET', self::ME],
            'logout' => ['POST', self::LOGOUT],
            'step-up' => ['POST', self::STEP_UP],
        ];
    }

    #[DataProvider('fullTokenRoutes')]
    public function test_a_setup_token_is_refused_on_every_other_central_route(string $method, string $uri): void
    {
        $setupToken = $this->setupTokenFor($this->operator());

        $this->withHeaders($this->bearer($setupToken))
            ->json($method, $uri, ['code' => '123456'])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('central_auth.two_factor_setup_required'))
            ->assertJsonPath('error_code', 'central_auth.two_factor_setup_required');

        // Logout did not run: the setup token still exists.
        $this->assertSame(1, CentralPersonalAccessToken::query()->count());
    }

    public function test_setup_endpoints_need_a_central_token(): void
    {
        $this->postJson(self::ENABLE)->assertUnauthorized();
        $this->postJson(self::CONFIRM, ['code' => '123456'])->assertUnauthorized();
        $this->getJson(self::RECOVERY_CODES)->assertUnauthorized();
        $this->postJson(self::STEP_UP, ['code' => '123456'])->assertUnauthorized();

        // A token with neither central ability is 401 too.
        $other = $this->operator()->createToken('t', ['central:read'])->plainTextToken;
        $this->withHeaders($this->bearer($other))->postJson(self::ENABLE)->assertUnauthorized();
    }

    public function test_a_tenant_token_cannot_reach_the_two_factor_endpoints(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->tenantHeaders($tenant);
        unset($headers['X-Tenant']);

        $this->withHeaders($headers)->postJson(self::ENABLE)->assertUnauthorized();
        $this->withHeaders($headers)->postJson(self::STEP_UP, ['code' => '123456'])->assertUnauthorized();
    }

    // ---------------------------------------------------------------- login challenge

    public function test_challenge_with_a_valid_totp_issues_the_full_token_once(): void
    {
        $this->freezeSecond();
        [$user, $secret] = $this->operatorWithTwoFactor();
        $challengeId = $this->challengeFor($user, 'ops-laptop');

        $this->assertSame(0, CentralPersonalAccessToken::query()->count());

        $response = $this->completeChallenge($challengeId, ['code' => $this->otp($secret)])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('central_auth.login_success'))
            ->assertJsonPath('data.two_factor_required', false)
            ->assertJsonPath('data.two_factor_setup_required', false)
            ->assertJsonPath('data.abilities', ['central:*'])
            ->assertJsonPath('data.user.id', $user->getKey())
            ->assertJsonMissingPath('data.recovery_codes')
            ->assertJsonMissingPath('data.user.two_factor_secret');

        $token = CentralPersonalAccessToken::query()->sole();
        $this->assertSame('ops-laptop', $token->name);
        $this->assertSame(['central:*'], $token->abilities);
        $this->assertTrue($token->expires_at?->equalTo(now()->addMinutes(240)));
        $this->assertTrue($token->two_factor_verified_at?->equalTo(now()));

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('127.0.0.1', $user->last_login_ip);

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::LoginSucceeded->value)->sole();
        $this->assertSame('totp', $log->properties['two_factor_method'] ?? null);
        $this->assertSame('full', $log->properties['session_scope'] ?? null);

        $this->withHeaders($this->bearer((string) $response->json('data.token')))->getJson(self::ME)->assertOk();

        // Single use: the same challenge can never be exchanged again.
        $this->completeChallenge($challengeId, ['code' => $this->otp($secret, 1)])
            ->assertStatus(422)
            ->assertJsonPath('errors.challenge_id.0', __('central_auth.two_factor_challenge_invalid'));
        $this->assertSame(1, CentralPersonalAccessToken::query()->count());
    }

    public function test_full_token_ttl_follows_config(): void
    {
        $this->freezeSecond();
        config(['central.token_ttl_minutes' => 30]);
        [$user, $secret] = $this->operatorWithTwoFactor();

        $this->completeChallenge($this->challengeFor($user), ['code' => $this->otp($secret)])->assertOk();

        $this->assertTrue(CentralPersonalAccessToken::query()->sole()->expires_at?->equalTo(now()->addMinutes(30)));
    }

    public function test_a_wrong_code_is_422_audited_and_the_challenge_stays_usable(): void
    {
        [$user, $secret] = $this->operatorWithTwoFactor();
        $challengeId = $this->challengeFor($user);

        $this->completeChallenge($challengeId, ['code' => $this->wrongOtp($secret)])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', __('central_auth.two_factor_invalid'));

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TwoFactorChallengeFailed->value)->sole();
        $this->assertSame('login', $log->properties['context'] ?? null);
        $this->assertSame('invalid_code', $log->properties['reason'] ?? null);
        $this->assertSame($user->getKey(), $log->causer_id);
        $this->assertSame(0, CentralPersonalAccessToken::query()->count());

        $this->completeChallenge($challengeId, ['code' => $this->otp($secret)])->assertOk();
    }

    public function test_the_challenge_is_burnt_after_the_maximum_number_of_wrong_codes(): void
    {
        config(['central.two_factor_challenge_max_attempts' => 3]);
        [$user, $secret] = $this->operatorWithTwoFactor();
        $challengeId = $this->challengeFor($user);
        $wrong = $this->wrongOtp($secret);

        $this->completeChallenge($challengeId, ['code' => $wrong])->assertJsonValidationErrors(['code']);
        $this->completeChallenge($challengeId, ['code' => $wrong])->assertJsonValidationErrors(['code']);
        $this->completeChallenge($challengeId, ['code' => $wrong])
            ->assertStatus(422)
            ->assertJsonPath('errors.challenge_id.0', __('central_auth.two_factor_challenge_invalid'));

        // Even the right code no longer works on that challenge.
        $this->completeChallenge($challengeId, ['code' => $this->otp($secret)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_id']);

        $this->assertSame(0, CentralPersonalAccessToken::query()->count());
        $this->assertSame(4, $this->auditCount(CentralAuditEvent::TwoFactorChallengeFailed));
    }

    public function test_an_expired_challenge_is_refused(): void
    {
        [$user, $secret] = $this->operatorWithTwoFactor();
        $challengeId = $this->challengeFor($user);

        $this->travel(301)->seconds();

        $this->completeChallenge($challengeId, ['code' => $this->otp($secret)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_id']);

        $this->assertSame(0, CentralPersonalAccessToken::query()->count());
        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TwoFactorChallengeFailed->value)->sole();
        $this->assertSame('invalid_challenge', $log->properties['reason'] ?? null);
    }

    public function test_unknown_challenge_and_deactivated_operator_are_refused(): void
    {
        [$user, $secret] = $this->operatorWithTwoFactor();

        $this->completeChallenge(str_repeat('x', 64), ['code' => $this->otp($secret)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_id']);

        $challengeId = $this->challengeFor($user);
        $user->forceFill(['is_active' => false])->save();

        $this->completeChallenge($challengeId, ['code' => $this->otp($secret)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_id']);

        $this->assertSame(0, CentralPersonalAccessToken::query()->count());
    }

    public function test_a_recovery_code_signs_in_once_and_is_rotated(): void
    {
        [$user] = $this->operatorWithTwoFactor();
        $recoveryCode = $user->recoveryCodes()[0];

        $this->completeChallenge($this->challengeFor($user), ['recovery_code' => $recoveryCode])->assertOk();

        $user->refresh();
        $this->assertNotContains($recoveryCode, $user->recoveryCodes());
        $this->assertCount(8, $user->recoveryCodes());
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::RecoveryCodeUsed));
        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::LoginSucceeded->value)->sole();
        $this->assertSame('recovery_code', $log->properties['two_factor_method'] ?? null);

        // The same recovery code is refused the second time.
        $this->completeChallenge($this->challengeFor($user), ['recovery_code' => $recoveryCode])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
        $this->assertSame(1, CentralPersonalAccessToken::query()->count());
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidChallengePayloads(): array
    {
        return [
            'no factor' => [['challenge_id' => 'abc'], 'code'],
            'both factors' => [['challenge_id' => 'abc', 'code' => '123456', 'recovery_code' => 'aaaa-bbbb'], 'code'],
            'missing challenge id' => [['code' => '123456'], 'challenge_id'],
            'overlong code' => [['challenge_id' => 'abc', 'code' => str_repeat('1', 17)], 'code'],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidChallengePayloads')]
    public function test_challenge_validation_errors_are_422(array $payload, string $field): void
    {
        $this->postJson(self::CHALLENGE, $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors([$field]);
    }

    public function test_the_challenge_endpoint_is_throttled(): void
    {
        $limit = (int) config('central.rate_limits.two_factor_per_ip_per_minute');

        for ($i = 0; $i < $limit; $i++) {
            $this->completeChallenge('unknown-'.$i, ['code' => '123456'])->assertStatus(422);
        }

        $this->completeChallenge('unknown-x', ['code' => '123456'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    // ---------------------------------------------------------------- step-up

    public function test_step_up_is_required_after_the_window_and_renews_only_the_current_token(): void
    {
        $this->freezeSecond();
        [$user, $secret] = $this->operatorWithTwoFactor();
        $current = (string) $this->completeChallenge($this->challengeFor($user), ['code' => $this->otp($secret)])->json('data.token');
        $other = $user->createToken('other-device')->plainTextToken;

        // Fresh from the challenge: the probe passes; a token never verified does not.
        $this->withHeaders($this->bearer($current))->getJson(self::PROBE)->assertOk();
        $this->withHeaders($this->bearer($other))
            ->getJson(self::PROBE)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required')
            ->assertJsonPath('message', __('central_auth.step_up_required'));

        $this->travel(16)->minutes();

        $this->withHeaders($this->bearer($current))
            ->getJson(self::PROBE)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->withHeaders($this->bearer($current))
            ->postJson(self::STEP_UP, ['code' => $this->otp($secret, 1)])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('central_auth.step_up_confirmed'))
            ->assertJsonPath('data.two_factor_verified_until', now()->addMinutes(15)->toIso8601String());

        $this->withHeaders($this->bearer($current))->getJson(self::PROBE)->assertOk();
        $this->withHeaders($this->bearer($other))->getJson(self::PROBE)->assertForbidden();

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::StepUpConfirmed->value)->sole();
        $this->assertSame($user->getKey(), $log->causer_id);
        $this->assertSame('totp', $log->properties['two_factor_method'] ?? null);

        $this->travel(15)->minutes();
        $this->travel(1)->seconds();
        $this->withHeaders($this->bearer($current))->getJson(self::PROBE)->assertForbidden();
    }

    public function test_step_up_with_a_wrong_or_replayed_code_is_422_and_audited(): void
    {
        [$user, $secret] = $this->operatorWithTwoFactor();
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeaders($this->bearer($token))
            ->postJson(self::STEP_UP, ['code' => $this->wrongOtp($secret)])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', __('central_auth.two_factor_invalid'));

        $code = $this->otp($secret);
        $this->withHeaders($this->bearer($token))->postJson(self::STEP_UP, ['code' => $code])->assertOk();

        // Fortify's replay cache: a TOTP accepted once is refused inside its window.
        $this->withHeaders($this->bearer($token))
            ->postJson(self::STEP_UP, ['code' => $code])
            ->assertStatus(422);

        $failures = CentralAuditLog::query()->where('event', CentralAuditEvent::TwoFactorChallengeFailed->value)->get();
        $this->assertCount(2, $failures);
        $this->assertSame(['step_up'], $failures->pluck('properties.context')->unique()->values()->all());
    }

    public function test_step_up_with_a_recovery_code_is_audited(): void
    {
        [$user] = $this->operatorWithTwoFactor();
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeaders($this->bearer($token))
            ->postJson(self::STEP_UP, ['recovery_code' => $user->recoveryCodes()[1]])
            ->assertOk();

        $this->withHeaders($this->bearer($token))->getJson(self::PROBE)->assertOk();
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::StepUpConfirmed));
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::RecoveryCodeUsed));
    }

    public function test_step_up_validation_errors_are_422(): void
    {
        [$user] = $this->operatorWithTwoFactor();
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeaders($this->bearer($token))->postJson(self::STEP_UP, [])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    // ---------------------------------------------------------------- central context

    public function test_two_factor_routes_are_404_on_a_tenant_host(): void
    {
        $tenant = $this->createTenant();
        [$user, $secret] = $this->operatorWithTwoFactor();
        $token = $user->createToken('t')->plainTextToken;

        $this->postJson($this->tenantUrl($tenant, self::CHALLENGE), ['challenge_id' => 'x', 'code' => $this->otp($secret)])->assertNotFound();
        $this->withHeaders($this->bearer($token))->postJson($this->tenantUrl($tenant, self::ENABLE))->assertNotFound();
        $this->withHeaders($this->bearer($token))->postJson($this->tenantUrl($tenant, self::CONFIRM), ['code' => '1'])->assertNotFound();
        $this->withHeaders($this->bearer($token))->getJson($this->tenantUrl($tenant, self::RECOVERY_CODES))->assertNotFound();
        $this->withHeaders($this->bearer($token))->postJson($this->tenantUrl($tenant, self::STEP_UP), ['code' => '1'])->assertNotFound();

        $this->assertSame(0, $this->auditCount(CentralAuditEvent::TwoFactorChallengeFailed));
    }
}
