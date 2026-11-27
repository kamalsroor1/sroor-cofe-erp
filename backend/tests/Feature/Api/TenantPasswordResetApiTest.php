<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\TenantPasswordResetMail;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AccountLockout;
use App\Support\PlatformHosts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * SIGN-15: a shop user's own "forgot password" by email on the shop host
 * (POST /api/v1/auth/forgot-password, POST /api/v1/auth/reset-password).
 *
 * Both endpoints are public by design (no 401/403 path): the emailed token is the credential.
 * Covered: 200, 422, 429, no account enumeration, token single-use + expiry, every Sanctum token
 * revoked (old bearer answers 401), lockout lifted, tenant isolation, no shop resolved.
 */
final class TenantPasswordResetApiTest extends TenantTestCase
{
    // Fake passwords; the `fixture` prefix marks them as fake for gitleaks (.gitleaks.toml).
    private const OLD_PASSWORD = 'fixtureOld123';

    private const NEW_PASSWORD = 'fixtureNew456';

    private const FORGOT = '/api/v1/auth/forgot-password';

    private const RESET = '/api/v1/auth/reset-password';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Mail::fake();
        Notification::fake();
        $this->tenant = $this->createTenant();
    }

    // ------------------------------------------------------------------ helpers

    private function owner(array $attributes = [], ?Tenant $tenant = null): User
    {
        return $this->createTenantUser($tenant ?? $this->tenant, 'admin', attributes: array_merge([
            'email' => 'owner@shop.harness.test',
            'password' => Hash::make(self::OLD_PASSWORD),
        ], $attributes));
    }

    /** @return array<string, string> */
    private function headers(?Tenant $tenant = null): array
    {
        return ['Accept' => 'application/json', 'X-Tenant' => (string) ($tenant ?? $this->tenant)->getTenantKey()];
    }

    private function forgot(string $email, ?Tenant $tenant = null): TestResponse
    {
        return $this->postJson(self::FORGOT, ['email' => $email], $this->headers($tenant));
    }

    private function reset(string $email, string $token, ?Tenant $tenant = null, string $password = self::NEW_PASSWORD): TestResponse
    {
        return $this->postJson(self::RESET, [
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ], $this->headers($tenant));
    }

    /** Plain token from the queued mail's link (the only place it ever appears). */
    private function tokenFromMail(string $email): string
    {
        $token = null;

        Mail::assertQueued(TenantPasswordResetMail::class, function (TenantPasswordResetMail $mail) use ($email, &$token): bool {
            if (! $mail->hasTo($email)) {
                return false;
            }

            parse_str((string) parse_url($mail->resetUrl, PHP_URL_QUERY), $query);
            $token = (string) ($query['token'] ?? '');

            return true;
        });

        $this->assertNotSame('', (string) $token);

        return (string) $token;
    }

    private function freshUser(int $id, ?Tenant $tenant = null): User
    {
        return $this->inTenant($tenant ?? $this->tenant, fn (): User => User::query()->findOrFail($id));
    }

    // ------------------------------------------------------------------ forgot-password

    public function test_forgot_password_queues_a_link_on_the_shop_domain_with_a_hashed_token(): void
    {
        $owner = $this->owner();

        $this->forgot('Owner@Shop.Harness.Test ')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('auth.password_reset.link_sent'));

        Mail::assertQueued(TenantPasswordResetMail::class, 1);
        $token = $this->tokenFromMail('owner@shop.harness.test');

        Mail::assertQueued(TenantPasswordResetMail::class, function (TenantPasswordResetMail $mail): bool {
            $host = $this->tenant->domains()->orderBy('id')->value('domain') ?: PlatformHosts::tenantHost($this->tenant->slug);

            return str_starts_with($mail->resetUrl, PlatformHosts::url((string) $host, '/reset-password').'?')
                && $mail->expireMinutes === 30
                && $mail->shopName === $this->tenant->name;
        });

        $row = $this->inTenant($this->tenant, fn () => DB::table('password_reset_tokens')->where('email', 'owner@shop.harness.test')->first());
        $this->assertNotNull($row);
        $this->assertNotSame($token, $row->token);
        $this->assertTrue(Hash::check($token, $row->token));

        $this->assertSame('owner@shop.harness.test', $owner->email);
    }

    public function test_forgot_password_answers_the_same_for_unknown_and_inactive_accounts(): void
    {
        $this->owner(['email' => 'inactive@shop.harness.test', 'is_active' => false]);
        $expected = ['success' => true, 'message' => __('auth.password_reset.link_sent')];

        foreach (['nobody@shop.harness.test', 'inactive@shop.harness.test'] as $email) {
            $this->forgot($email)
                ->assertOk()
                ->assertExactJson($expected);
        }

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => DB::table('password_reset_tokens')->count()));

        // Active account receives the exact same 200 payload shape & message.
        $this->owner(['email' => 'active@shop.harness.test', 'is_active' => true]);
        $this->forgot('active@shop.harness.test')
            ->assertOk()
            ->assertExactJson($expected);
        Mail::assertQueued(TenantPasswordResetMail::class, 1);
    }

    public function test_forgot_password_validates_the_email(): void
    {
        $this->postJson(self::FORGOT, [], $this->headers())->assertStatus(422)->assertJsonValidationErrors('email');
        $this->forgot('not-an-email')->assertStatus(422)->assertJsonValidationErrors('email');

        Mail::assertNothingQueued();
    }

    public function test_forgot_password_is_throttled_per_email(): void
    {
        config(['rate_limits.tenant_password_reset.per_email_per_hour' => 2, 'rate_limits.tenant_password_reset.per_ip_per_minute' => 50]);

        $this->forgot('nobody@shop.harness.test')->assertOk();
        $this->forgot('nobody@shop.harness.test')->assertOk();
        $this->forgot('nobody@shop.harness.test')->assertStatus(429);

        // Another email from the same IP still has its own budget.
        $this->forgot('other@shop.harness.test')->assertOk();
    }

    public function test_a_second_request_within_the_broker_throttle_sends_no_second_mail(): void
    {
        $this->owner();

        $this->forgot('owner@shop.harness.test')->assertOk();
        $this->forgot('owner@shop.harness.test')->assertOk()->assertJsonPath('message', __('auth.password_reset.link_sent'));

        Mail::assertQueued(TenantPasswordResetMail::class, 1);
    }

    public function test_without_a_resolved_shop_nothing_is_sent_and_reset_fails(): void
    {
        $this->owner();

        // The central host refuses the tenant API before the controller (ResolveApiTenancy / tenant
        // guards); the Actions also refuse on their own, so the central `users` table is never read.
        $this->postJson(self::FORGOT, ['email' => 'owner@shop.harness.test'], ['Accept' => 'application/json'])
            ->assertStatus(404);
        Mail::assertNothingQueued();

        $this->postJson(self::RESET, [
            'email' => 'owner@shop.harness.test',
            'token' => 'whatever',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ], ['Accept' => 'application/json'])
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------ reset-password

    public function test_reset_changes_the_password_revokes_every_token_and_clears_flags(): void
    {
        $owner = $this->owner();
        $this->inTenant($this->tenant, fn () => User::query()->findOrFail($owner->id)->forceFill(['must_change_password' => true])->save());
        $oldBearer = $this->tenantToken($this->tenant, $owner);
        $this->tenantToken($this->tenant, $owner);
        $this->inTenant($this->tenant, function () use ($owner): void {
            for ($i = 0; $i < AccountLockout::maxAttempts(); $i++) {
                AccountLockout::hit($owner->phone);
            }
        });
        $this->assertNotNull($this->inTenant($this->tenant, fn () => AccountLockout::lockedUntil($owner->phone)));

        $this->forgot('owner@shop.harness.test')->assertOk();
        $token = $this->tokenFromMail('owner@shop.harness.test');

        $this->reset('owner@shop.harness.test', $token)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('auth.password_reset.success'));

        $fresh = $this->freshUser((int) $owner->id);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse((bool) $fresh->must_change_password);
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => $fresh->tokens()->count()));
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => DB::table('password_reset_tokens')->count()));
        $this->assertNull($this->inTenant($this->tenant, fn () => AccountLockout::lockedUntil($owner->phone)));

        // The old session is dead (401) and the new password signs in.
        $this->getJson('/api/v1/auth/me', $this->headers() + ['Authorization' => 'Bearer '.$oldBearer])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', [
            'login' => 'owner@shop.harness.test',
            'password' => self::NEW_PASSWORD,
            'device_name' => 'spa',
        ], $this->headers())->assertOk();

        // Single use.
        $this->reset('owner@shop.harness.test', $token, password: 'fixtureAgain789')
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('auth.password_reset.invalid'));
    }

    public function test_reset_rejects_bad_expired_and_unknown_tokens_with_the_same_answer(): void
    {
        $owner = $this->owner();
        $this->forgot('owner@shop.harness.test')->assertOk();
        $token = $this->tokenFromMail('owner@shop.harness.test');

        $this->reset('owner@shop.harness.test', 'wrong-token')
            ->assertStatus(422)->assertJsonPath('errors.email.0', __('auth.password_reset.invalid'));
        $this->reset('nobody@shop.harness.test', $token)
            ->assertStatus(422)->assertJsonPath('errors.email.0', __('auth.password_reset.invalid'));

        $this->travel(31)->minutes();
        $this->reset('owner@shop.harness.test', $token)
            ->assertStatus(422)->assertJsonPath('errors.email.0', __('auth.password_reset.invalid'));

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->freshUser((int) $owner->id)->password));
    }

    public function test_reset_validates_the_payload(): void
    {
        $this->postJson(self::RESET, [], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'token', 'password']);

        $this->postJson(self::RESET, [
            'email' => 'owner@shop.harness.test',
            'token' => 'x',
            'password' => 'short',
            'password_confirmation' => 'other',
        ], $this->headers())->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_reset_is_throttled(): void
    {
        config(['rate_limits.tenant_password_reset.per_ip_per_minute' => 2]);

        $this->reset('owner@shop.harness.test', 'bad')->assertStatus(422);
        $this->reset('owner@shop.harness.test', 'bad')->assertStatus(422);
        $this->reset('owner@shop.harness.test', 'bad')->assertStatus(429);
    }

    // ------------------------------------------------------------------ tenant isolation

    public function test_a_token_from_one_shop_cannot_reset_the_same_email_in_another_shop(): void
    {
        $owner = $this->owner();
        $other = $this->createTenant();
        $otherOwner = $this->owner([], $other);

        $this->forgot('owner@shop.harness.test')->assertOk();
        $token = $this->tokenFromMail('owner@shop.harness.test');
        Mail::assertQueued(TenantPasswordResetMail::class, 1);

        $this->reset('owner@shop.harness.test', $token, $other)
            ->assertStatus(422)->assertJsonPath('errors.email.0', __('auth.password_reset.invalid'));

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->freshUser((int) $otherOwner->id, $other)->password));
        $this->assertSame(0, $this->inTenant($other, fn (): int => DB::table('password_reset_tokens')->count()));

        // The token still works in its own shop.
        $this->reset('owner@shop.harness.test', $token)->assertOk();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $this->freshUser((int) $owner->id)->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->freshUser((int) $otherOwner->id, $other)->password));
    }

    public function test_an_email_of_another_shop_gets_no_mail(): void
    {
        $other = $this->createTenant();
        $this->owner(['email' => 'elsewhere@shop.harness.test'], $other);

        $this->forgot('elsewhere@shop.harness.test')->assertOk()->assertJsonPath('message', __('auth.password_reset.link_sent'));

        Mail::assertNothingQueued();
    }
}
