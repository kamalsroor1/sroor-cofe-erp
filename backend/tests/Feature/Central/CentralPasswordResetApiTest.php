<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralAuditLog;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * IDEN-1.12: operator password reset through the `central_users` broker.
 *
 *  - POST /api/v1/super-admin/auth/forgot-password  always the same 200; a mail only for an
 *    active operator; link from central.password_reset_url (never the Host header);
 *  - POST /api/v1/super-admin/auth/reset-password   uniform 422 for unknown email / bad
 *    token; success revokes every central token; both audited;
 *  - central hosts only (404 on a tenant host), throttled.
 */
final class CentralPasswordResetApiTest extends TenantTestCase
{
    private const FORGOT = '/api/v1/super-admin/auth/forgot-password';

    private const RESET = '/api/v1/super-admin/auth/reset-password';

    private const LOGIN = '/api/v1/super-admin/auth/login';

    private const ME = '/api/v1/super-admin/auth/me';

    private const RESET_URL = 'https://console.platform.test/super-admin/reset-password';

    // Fake operator passwords; the `fixture` prefix marks them as fake for gitleaks (.gitleaks.toml).
    private const OLD_PASSWORD = 'fixtureCentralOldPassword';

    private const NEW_PASSWORD = 'fixtureCentralNewPassword';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);
        config(['central.password_reset_url' => self::RESET_URL]);
        Mail::fake();
    }

    /** @param array<string, mixed> $attributes */
    private function operator(array $attributes = []): CentralUser
    {
        $user = CentralUser::factory()->create(array_merge([
            'email' => 'operator-'.uniqid().'@central.test',
            'password' => Hash::make(self::OLD_PASSWORD),
            'is_active' => true,
        ], $attributes));
        $user->assignRole(CentralPermission::ROLE_SUPER_ADMIN);

        return $user->refresh();
    }

    /** Requests a link for $email and returns the plain token from the queued mail. */
    private function requestToken(string $email): string
    {
        $this->postJson(self::FORGOT, ['email' => $email])->assertOk();

        $html = '';
        Mail::assertQueued(Mailable::class, function (Mailable $mail) use (&$html, $email): bool {
            if (! $mail->hasTo($email)) {
                return false;
            }
            $html = $mail->render();

            return true;
        });

        $this->assertSame(1, preg_match('/[?&]token=([^&"]+)/', html_entity_decode($html), $matches));

        return urldecode($matches[1]);
    }

    /** @return array<string, string> */
    private function resetPayload(string $email, string $token, string $password = self::NEW_PASSWORD): array
    {
        return ['email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password];
    }

    private function auditCount(CentralAuditEvent $event): int
    {
        return CentralAuditLog::query()->where('event', $event->value)->count();
    }

    // ---------------------------------------------------------------- forgot-password

    public function test_forgot_password_queues_a_link_built_from_config_and_audits(): void
    {
        $user = $this->operator();

        $this->withHeaders(['Accept' => 'application/json', 'X-Forwarded-Host' => 'evil.example'])
            ->postJson(self::FORGOT, ['email' => '  '.strtoupper($user->email).' '])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => __('central_auth.password_reset_link_sent')]);

        Mail::assertQueued(Mailable::class, function (Mailable $mail) use ($user): bool {
            $html = html_entity_decode($mail->render());

            return $mail->hasTo($user->email)
                && $mail->subject === __('central_auth.password_reset_mail.subject')
                && str_contains($html, self::RESET_URL.'?token=')
                && str_contains($html, 'email='.urlencode($user->email))
                && ! str_contains($html, 'evil.example')
                && ! str_contains($html, 'localhost');
        });
        Mail::assertQueuedCount(1);

        // Only a hash of the token is stored, in the central broker table.
        $row = DB::connection((string) $user->getConnectionName())->table('central_password_reset_tokens')->sole();
        $this->assertSame($user->email, $row->email);
        $this->assertStringStartsWith('$2y$', (string) $row->token);

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::PasswordResetRequested->value)->sole();
        $this->assertSame('sent', $log->properties['outcome'] ?? null);
        $this->assertSame($user->getKey(), (int) $log->subject_id);
    }

    public function test_forgot_password_answers_identically_for_unknown_inactive_and_throttled_emails(): void
    {
        $active = $this->operator();
        $inactive = $this->operator(['is_active' => false]);
        $expected = ['success' => true, 'message' => __('central_auth.password_reset_link_sent')];

        $this->postJson(self::FORGOT, ['email' => $active->email])->assertOk()->assertExactJson($expected);
        // Second request inside the broker throttle (60s): same answer, no second mail.
        $this->postJson(self::FORGOT, ['email' => $active->email])->assertOk()->assertExactJson($expected);
        $this->postJson(self::FORGOT, ['email' => 'nobody@central.test'])->assertOk()->assertExactJson($expected);
        $this->postJson(self::FORGOT, ['email' => $inactive->email])->assertOk()->assertExactJson($expected);

        Mail::assertQueuedCount(1);

        $outcomes = CentralAuditLog::query()
            ->where('event', CentralAuditEvent::PasswordResetRequested->value)
            ->orderBy('id')
            ->get()
            ->map(static fn (CentralAuditLog $log): mixed => $log->properties['outcome'] ?? null)
            ->all();
        $this->assertSame(['sent', 'throttled', 'unknown_email', 'inactive'], $outcomes);
    }

    public function test_forgot_password_without_a_configured_url_sends_nothing_but_answers_the_same(): void
    {
        config(['central.password_reset_url' => null]);
        $user = $this->operator();

        $this->postJson(self::FORGOT, ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', __('central_auth.password_reset_link_sent'));

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        $this->assertSame(0, DB::connection((string) $user->getConnectionName())->table('central_password_reset_tokens')->count());
    }

    public function test_a_tenant_user_email_never_gets_a_central_reset_link(): void
    {
        $tenant = $this->createTenant();
        $tenantAdmin = $this->tenantAdmin($tenant);

        $this->postJson(self::FORGOT, ['email' => (string) $tenantAdmin->email])->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_forgot_password_validation_is_422(): void
    {
        $this->postJson(self::FORGOT, [])->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson(self::FORGOT, ['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_is_throttled_per_ip(): void
    {
        $limit = (int) config('central.rate_limits.password_reset_per_ip_per_minute');

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson(self::FORGOT, ['email' => 'probe'.$i.'@central.test'])->assertOk();
        }

        $this->postJson(self::FORGOT, ['email' => 'probe-x@central.test'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    // ---------------------------------------------------------------- reset-password

    public function test_reset_password_changes_the_password_revokes_every_token_and_audits(): void
    {
        $user = $this->operator();
        $full = $user->createToken('laptop')->plainTextToken;
        $user->createToken('setup', ['central:2fa-setup']);
        $other = $this->operator();
        $otherToken = $other->createToken('other')->plainTextToken;

        $token = $this->requestToken($user->email);

        $this->postJson(self::RESET, $this->resetPayload(strtoupper($user->email), $token))
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => __('central_auth.password_reset_success')]);

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $user->password));

        // Every token of that operator is gone; other operators keep theirs.
        $this->assertSame(0, CentralPersonalAccessToken::query()->where('tokenable_id', $user->getKey())->count());
        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$full])->getJson(self::ME)->assertUnauthorized();
        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$otherToken])->getJson(self::ME)->assertOk();

        // The reset token is single use.
        $this->assertSame(0, DB::connection((string) $user->getConnectionName())->table('central_password_reset_tokens')->count());
        $this->postJson(self::RESET, $this->resetPayload($user->email, $token, 'fixtureCentralThirdPassword'))
            ->assertStatus(422);

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::PasswordResetCompleted->value)->sole();
        $this->assertSame($user->getKey(), $log->causer_id);
        $this->assertSame(2, $log->properties['revoked_sessions'] ?? null);

        // The new password signs in (2FA still applies: no confirmed 2FA => setup token).
        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::NEW_PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.two_factor_setup_required', true);
    }

    public function test_reset_with_a_bad_token_or_unknown_email_gets_the_same_422(): void
    {
        $user = $this->operator();
        $token = $this->requestToken($user->email);

        foreach ([
            $this->resetPayload($user->email, 'not-the-token'),
            $this->resetPayload('nobody@central.test', $token),
        ] as $payload) {
            $this->postJson(self::RESET, $payload)
                ->assertStatus(422)
                ->assertJsonPath('errors.email.0', __('central_auth.password_reset_invalid'));
        }

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->refresh()->password));
        $this->assertSame(0, $this->auditCount(CentralAuditEvent::PasswordResetCompleted));
    }

    public function test_an_expired_reset_token_is_refused(): void
    {
        $user = $this->operator();
        $token = $this->requestToken($user->email);

        $this->travel(31)->minutes();

        $this->postJson(self::RESET, $this->resetPayload($user->email, $token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->refresh()->password));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidResetPayloads(): array
    {
        return [
            'missing token' => [['email' => 'a@central.test', 'password' => 'fixturePasswordLong', 'password_confirmation' => 'fixturePasswordLong'], 'token'],
            'missing email' => [['token' => 't', 'password' => 'fixturePasswordLong', 'password_confirmation' => 'fixturePasswordLong'], 'email'],
            'short password' => [['email' => 'a@central.test', 'token' => 't', 'password' => 'fixtureShrt', 'password_confirmation' => 'fixtureShrt'], 'password'],
            'unconfirmed password' => [['email' => 'a@central.test', 'token' => 't', 'password' => 'fixturePasswordLong', 'password_confirmation' => 'fixtureSomethingElse'], 'password'],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidResetPayloads')]
    public function test_reset_validation_errors_are_422(array $payload, string $field): void
    {
        $this->postJson(self::RESET, $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors([$field]);
    }

    // ---------------------------------------------------------------- central context

    public function test_password_reset_routes_are_404_on_a_tenant_host(): void
    {
        $tenant = $this->createTenant();
        $user = $this->operator();

        $this->postJson($this->tenantUrl($tenant, self::FORGOT), ['email' => $user->email])->assertNotFound();
        $this->postJson($this->tenantUrl($tenant, self::RESET), $this->resetPayload($user->email, 'x'))->assertNotFound();

        Mail::assertNothingQueued();
        $this->assertSame(0, $this->auditCount(CentralAuditEvent::PasswordResetRequested));
    }

    public function test_operator_emails_are_stored_lowercase(): void
    {
        $user = $this->operator(['email' => '  Mixed.Case@Central.TEST ']);

        $this->assertSame('mixed.case@central.test', $user->email);
        $this->assertSame('mixed.case@central.test', $user->getRawOriginal('email'));
    }
}
