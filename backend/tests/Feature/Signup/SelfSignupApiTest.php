<?php

declare(strict_types=1);

namespace Tests\Feature\Signup;

use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Mail\SignupVerificationCodeMail;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Services\Branding\PlatformBranding;
use App\Services\Signup\SignupTrialTerms;
use App\Support\Signup\SignupSecrets;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * SIGN-11: public self-signup with an emailed verification code (central DB, no tenancy).
 *
 *   POST /api/v1/signup, /api/v1/signup/verify, /api/v1/signup/resend
 */
final class SelfSignupApiTest extends TenantTestCase
{
    private const URL = '/api/v1/signup';

    private const PASSWORD = 'MyShopPass2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->endTenancy();
        $this->seed(PlansAndFeaturesSeeder::class);
        Mail::fake();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'محمصة التسجيل',
            'owner_name' => 'أحمد علي',
            'email' => 'Owner@Example.test',
            'phone' => '+201000000701',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'business_type' => 'weighted',
            'city' => 'القاهرة',
            'subdomain' => 'self-roastery',
            'terms_accepted' => true,
            'locale' => 'ar',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: string, 1: SignupRequest, 2: TestResponse<Response>}
     */
    private function start(array $overrides = []): array
    {
        $response = $this->postJson(self::URL, $this->payload($overrides))->assertCreated();
        $token = (string) $response->json('data.signup_token');
        $signup = SignupRequest::query()->where('signup_token_hash', SignupSecrets::hashToken($token))->sole();

        return [$token, $signup, $response];
    }

    /** The code of the LAST verification mail queued for $email. */
    private function lastCode(string $email = 'owner@example.test'): string
    {
        $code = null;
        Mail::assertQueued(SignupVerificationCodeMail::class, function (SignupVerificationCodeMail $mail) use ($email, &$code): bool {
            if ($mail->hasTo($email)) {
                $code = $mail->code;
            }

            return true;
        });

        $this->assertIsString($code);

        return $code;
    }

    private function wrongCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    // ------------------------------------------------------------------ start

    public function test_it_stores_an_unverified_self_signup_and_emails_a_code(): void
    {
        [$token, $signup, $response] = $this->start();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('message', __('self_signup.code_sent'))
            ->assertJsonPath('data.email_masked', 'o***@example.test')
            ->assertJsonPath('data.subdomain', 'self-roastery')
            ->assertJsonPath('data.code_expires_in', 900)
            ->assertJsonPath('data.resend_available_in', 60);
        $this->assertSame(SignupSecrets::TOKEN_LENGTH, strlen($token));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->assertSame(SignupRequestStatus::AwaitingVerification, $signup->status);
        $this->assertSame(SignupFlow::SelfSignup, $signup->signup_flow);
        $this->assertSame('owner@example.test', $signup->email);
        $this->assertSame('01000000701', $signup->phone);
        $this->assertSame('أحمد علي', $signup->contact_name);
        $this->assertSame('self-roastery', $signup->preferred_subdomain);
        $this->assertSame('ar', $signup->locale);
        $this->assertNotNull($signup->terms_accepted_at);
        $this->assertSame((string) config('legal.version'), $signup->terms_version);
        $this->assertNull($signup->email_verified_at);
        $this->assertNull($signup->tenant_id);
        $this->assertSame(0, Tenant::query()->count());

        // Password: hashed only. Code: keyed hash only. Neither is anywhere in the row.
        $this->assertTrue(Hash::check(self::PASSWORD, (string) $signup->password_hash));
        $code = $this->lastCode();
        $this->assertTrue(SignupSecrets::codeMatches($signup->verification_code_hash, (int) $signup->id, $code));
        $raw = json_encode(DB::table('signup_requests')->where('id', $signup->id)->first());
        $this->assertStringNotContainsString(self::PASSWORD, (string) $raw);
        $this->assertStringNotContainsString('"'.$code.'"', (string) $raw);
        $this->assertStringNotContainsString($token, (string) $raw);

        // The secrets never serialise out of the model.
        $this->assertArrayNotHasKey('password_hash', $signup->toArray());
        $this->assertArrayNotHasKey('verification_code_hash', $signup->toArray());

        Mail::assertQueued(SignupVerificationCodeMail::class, fn (SignupVerificationCodeMail $mail): bool => $mail->hasTo('owner@example.test')
            && $mail->locale === 'ar');
        $this->assertContains(ShouldBeEncrypted::class, class_implements(SignupVerificationCodeMail::class), 'The queued payload (the code) is encrypted.');
    }

    public function test_the_code_mail_renders_in_both_languages(): void
    {
        $ar = (new SignupVerificationCodeMail('123456', 'محمصة', 15, 'ar'))->render();
        $this->assertStringContainsString('123456', $ar);
        $this->assertStringContainsString('dir="rtl"', $ar);
        $this->assertStringContainsString(__('mail.signup_code.heading', [], 'ar'), $ar);

        $en = new SignupVerificationCodeMail('654321', 'Coffee', 15, 'en');
        $this->assertStringContainsString('654321', $en->render());
        $this->assertStringContainsString('dir="ltr"', $en->render());
        $en->assertHasSubject(__('mail.signup_code.subject', ['code' => '654321', 'app' => app(PlatformBranding::class)->get()->name], 'en'));
    }

    public function test_without_a_plan_or_subdomain_it_uses_the_default_plan_and_suggests_a_code(): void
    {
        [, $signup, $response] = $this->start(['plan_code' => null, 'subdomain' => null, 'business_name' => 'Coffee Corner']);

        $default = app(SignupTrialTerms::class)->resolvePlan(null);
        $this->assertNotNull($default);
        $this->assertSame($default->slug, $signup->plan_code);
        $this->assertSame('coffee-corner', $signup->preferred_subdomain);
        $response->assertJsonPath('data.subdomain', 'coffee-corner');

        // A taken suggestion gets a suffix.
        $this->createTenant(['id' => 'coffee-house', 'slug' => 'coffee-house']);
        $this->endTenancy();
        [, $second] = $this->start(['subdomain' => null, 'business_name' => 'Coffee House', 'email' => 'second@example.test']);
        $this->assertSame('coffee-house-2', $second->preferred_subdomain);

        // Arabic names are transliterated.
        [, $third] = $this->start(['subdomain' => null, 'business_name' => 'محمصة', 'email' => 'third@example.test']);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]{3,30}$/', (string) $third->preferred_subdomain);
    }

    public function test_a_chosen_paid_plan_is_kept(): void
    {
        [, $signup] = $this->start(['plan_code' => 'BASIC']);

        $this->assertSame('basic', $signup->plan_code);
    }

    public function test_validation_rejects_bad_input_and_stores_nothing(): void
    {
        $this->postJson(self::URL, [])->assertStatus(422)->assertJsonValidationErrors([
            'business_name', 'owner_name', 'email', 'phone', 'password', 'business_type', 'terms_accepted',
        ]);

        $this->postJson(self::URL, $this->payload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->postJson(self::URL, $this->payload(['password_confirmation' => 'SomethingElse99']))
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->postJson(self::URL, $this->payload(['email' => 'not-an-email']))->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson(self::URL, $this->payload(['phone' => '0221234567']))->assertStatus(422)->assertJsonValidationErrors(['phone']);
        $this->postJson(self::URL, $this->payload(['business_type' => 'airline']))->assertStatus(422)->assertJsonValidationErrors(['business_type']);
        $this->postJson(self::URL, $this->payload(['plan_code' => 'no-such-plan']))->assertStatus(422)->assertJsonValidationErrors(['plan_code']);
        $this->postJson(self::URL, $this->payload(['terms_accepted' => false]))->assertStatus(422)->assertJsonValidationErrors(['terms_accepted']);
        $this->postJson(self::URL, $this->payload(['locale' => 'fr']))->assertStatus(422)->assertJsonValidationErrors(['locale']);

        foreach (['ab', 'Bad_Code', '-lead', 'admin'] as $bad) {
            $this->postJson(self::URL, $this->payload(['subdomain' => $bad]))->assertStatus(422)->assertJsonValidationErrors(['subdomain']);
        }

        // Unlike the assisted form, the code must be free now (the shop is created automatically).
        $this->createTenant(['id' => 'already-taken', 'slug' => 'already-taken']);
        $this->endTenancy();
        $this->postJson(self::URL, $this->payload(['subdomain' => 'already-taken']))
            ->assertStatus(422)
            ->assertJsonPath('errors.subdomain.0', __('signup.validation.subdomain_taken'));

        $this->assertSame(0, SignupRequest::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_a_code_held_by_a_verified_signup_is_taken(): void
    {
        [$token, $signup] = $this->start();
        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $this->lastCode()])->assertOk();
        $this->assertNotSame(SignupRequestStatus::AwaitingVerification, $signup->refresh()->status);

        $this->postJson(self::URL, $this->payload(['email' => 'other@example.test']))
            ->assertStatus(422)->assertJsonValidationErrors(['subdomain']);
    }

    public function test_a_filled_honeypot_answers_the_same_shape_and_stores_nothing(): void
    {
        $response = $this->postJson(self::URL, $this->payload(['website' => 'http://spam.example']))
            ->assertCreated()
            ->assertJsonPath('data.email_masked', 'o***@example.test');

        $this->assertSame(0, SignupRequest::query()->count());
        Mail::assertNothingQueued();

        $this->postJson(self::URL.'/verify', ['token' => $response->json('data.signup_token'), 'code' => '123456'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_it_ignores_tenant_selection(): void
    {
        $this->postJson(self::URL, $this->payload(), ['X-Tenant' => 'does-not-exist'])->assertCreated();
        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_every_endpoint_answers_503_while_self_signup_is_off(): void
    {
        app(PlatformBranding::class)->update(['self_signup_enabled' => false]);
        PlatformBranding::forget();

        $this->postJson(self::URL, $this->payload())
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('self_signup.errors.disabled'));
        $this->postJson(self::URL.'/verify', ['token' => str_repeat('a', 64), 'code' => '123456'])->assertStatus(503);
        $this->postJson(self::URL.'/resend', ['token' => str_repeat('a', 64)])->assertStatus(503);

        $this->assertSame(0, SignupRequest::query()->count());
    }

    public function test_accepted_signups_are_rate_limited_per_client(): void
    {
        config(['rate_limits.self_signup.per_hour' => 2]);

        $this->postJson(self::URL, $this->payload(['email' => 'a1@example.test', 'subdomain' => 'shop-one']))->assertCreated();
        $this->postJson(self::URL, $this->payload(['email' => 'a2@example.test', 'subdomain' => 'shop-two']))->assertCreated();
        $this->postJson(self::URL, $this->payload(['email' => 'a3@example.test', 'subdomain' => 'shop-three']))
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        $this->assertSame(2, SignupRequest::query()->count());
    }

    // ------------------------------------------------------------------ verify

    public function test_the_right_code_verifies_the_email(): void
    {
        [$token, $signup] = $this->start();
        $code = $this->lastCode();

        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email_masked', 'o***@example.test');

        $fresh = $signup->refresh();
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNotSame(SignupRequestStatus::AwaitingVerification, $fresh->status);
        $this->assertNull($fresh->verification_code_hash);

        // A double submit is not an error, and the code cannot be reused for anything else.
        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $code])->assertOk();
    }

    public function test_arabic_digits_are_accepted_in_the_code(): void
    {
        [$token, $signup] = $this->start();
        $arabic = strtr($this->lastCode(), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $arabic])->assertOk();
        $this->assertNotNull($signup->refresh()->email_verified_at);
    }

    public function test_errors_are_generic_and_identical(): void
    {
        [$token] = $this->start();
        $code = $this->lastCode();
        $message = __('self_signup.errors.code_invalid');

        // Wrong code, unknown token: same status, same field, same message.
        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $this->wrongCode($code)])
            ->assertStatus(422)->assertJsonPath('errors.code.0', $message);
        $this->postJson(self::URL.'/verify', ['token' => str_repeat('z', 64), 'code' => $code])
            ->assertStatus(422)->assertJsonPath('errors.code.0', $message);

        // Malformed input never reaches a lookup.
        $this->postJson(self::URL.'/verify', ['token' => 'short', 'code' => '12345'])
            ->assertStatus(422)->assertJsonValidationErrors(['token', 'code']);
    }

    public function test_five_wrong_attempts_burn_the_code(): void
    {
        [$token, $signup] = $this->start();
        $code = $this->lastCode();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $this->wrongCode($code)])->assertStatus(422);
            $this->assertSame($i, $signup->refresh()->verification_attempts, 'Each wrong try is committed.');
        }

        $this->assertNull($signup->refresh()->verification_code_hash);
        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $code])
            ->assertStatus(422)->assertJsonPath('errors.code.0', __('self_signup.errors.code_invalid'));
        $this->assertNull($signup->refresh()->email_verified_at);

        // A new code works again.
        $this->travel(61)->seconds();
        $this->postJson(self::URL.'/resend', ['token' => $token])->assertOk();
        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $this->lastCode()])->assertOk();
    }

    public function test_the_code_expires_after_fifteen_minutes(): void
    {
        [$token, $signup] = $this->start();
        $code = $this->lastCode();

        $this->travel(15)->minutes();
        $this->travel(1)->seconds();

        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $code])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);
        $this->assertNull($signup->refresh()->email_verified_at);
        $this->assertSame(0, $signup->refresh()->verification_attempts, 'An expired code spends no attempt.');
    }

    // ------------------------------------------------------------------ resend

    public function test_resend_waits_sixty_seconds_and_replaces_the_code(): void
    {
        [$token] = $this->start();
        $first = $this->lastCode();

        $this->postJson(self::URL.'/resend', ['token' => $token])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('success', false);
        $this->assertGreaterThan(0, (int) $this->postJson(self::URL.'/resend', ['token' => $token])->json('retry_after'));

        $this->travel(61)->seconds();
        $this->postJson(self::URL.'/resend', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('message', __('self_signup.code_resent'))
            ->assertJsonPath('data.status', 'awaiting_verification')
            ->assertJsonPath('data.resend_available_in', 60);
        Mail::assertQueuedCount(2);

        $second = $this->lastCode();
        if ($second !== $first) {
            $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $first])->assertStatus(422);
        }
        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $second])->assertOk();

        // Verified: nothing more to resend.
        $this->travel(61)->seconds();
        $this->postJson(self::URL.'/resend', ['token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('message', __('self_signup.errors.already_verified'));
    }

    public function test_at_most_five_codes_per_email_and_hour(): void
    {
        [$token] = $this->start();

        for ($i = 0; $i < 4; $i++) {
            $this->travel(61)->seconds();
            $this->postJson(self::URL.'/resend', ['token' => $token])->assertOk();
        }
        Mail::assertQueuedCount(5);

        $this->travel(61)->seconds();
        $response = $this->postJson(self::URL.'/resend', ['token' => $token])->assertStatus(429);
        $this->assertGreaterThan(60, (int) $response->json('retry_after'));

        // A new signup for the same address is refused too, and stores nothing.
        $this->postJson(self::URL, $this->payload(['subdomain' => 'another-shop']))->assertStatus(429);
        $this->assertSame(1, SignupRequest::query()->count());
        Mail::assertQueuedCount(5);

        // The budget frees up after the hour.
        $this->travel(1)->hours();
        $this->postJson(self::URL.'/resend', ['token' => $token])->assertOk();
    }

    public function test_resend_with_an_unknown_token_is_a_generic_422(): void
    {
        $this->postJson(self::URL.'/resend', ['token' => str_repeat('q', 64)])
            ->assertStatus(422)
            ->assertJsonPath('errors.token.0', __('self_signup.errors.token_invalid'));
        $this->postJson(self::URL.'/resend', [])->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_an_assisted_request_cannot_be_driven_through_the_self_signup_endpoints(): void
    {
        $assisted = SignupRequest::query()->create([
            'business_name' => 'محل', 'contact_name' => 'سارة', 'phone' => '01000000702',
            'business_type' => 'retail', 'status' => SignupRequestStatus::Pending->value,
        ]);
        $token = SignupSecrets::newToken();
        $assisted->forceFill(['signup_token_hash' => SignupSecrets::hashToken($token)])->save();

        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => '123456'])->assertStatus(422);
        $this->postJson(self::URL.'/resend', ['token' => $token])->assertStatus(422);
        $this->assertSame(SignupRequestStatus::Pending, $assisted->refresh()->status);
    }
}
