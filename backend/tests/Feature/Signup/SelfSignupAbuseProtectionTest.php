<?php

declare(strict_types=1);

namespace Tests\Feature\Signup;

use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Jobs\NotifyPlatformOfSignupRequestJob;
use App\Jobs\ProvisionVerifiedSignupJob;
use App\Mail\SignupVerificationCodeMail;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Rules\AvailableSubdomain;
use App\Support\Signup\EgyptianPhone;
use App\Support\Signup\SignupSecrets;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * SIGN-13: abuse protection of the public self-signup: one free shop per verified email and per
 * phone (at signup and again at verification), disposable email domains, reserved subdomains,
 * and the daily cleanup of unverified signups.
 */
final class SelfSignupAbuseProtectionTest extends TenantTestCase
{
    private const URL = '/api/v1/signup';

    private const PASSWORD = 'AbuseTest2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->endTenancy();
        $this->seed(PlansAndFeaturesSeeder::class);
        Mail::fake();
        // Verified signups stay `verified` here: provisioning is SelfSignupProvisioningTest's.
        Queue::fake([ProvisionVerifiedSignupJob::class, NotifyPlatformOfSignupRequestJob::class]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'محل الحماية',
            'owner_name' => 'منى',
            'email' => 'owner@shop.test',
            'phone' => '01000000801',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'business_type' => 'retail',
            'terms_accepted' => true,
        ], $overrides);
    }

    private function freePlan(): Plan
    {
        return Plan::query()->where('is_active', true)->where('is_free', true)->orderBy('sort_order')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function start(array $overrides = []): string
    {
        return (string) $this->postJson(self::URL, $this->payload($overrides))->assertCreated()->json('data.signup_token');
    }

    /** The code of the LAST verification mail queued for $email (read it right after its signup). */
    private function lastCode(string $email): string
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

    /**
     * @return TestResponse<Response>
     */
    private function verify(string $token, string $code): TestResponse
    {
        return $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $code]);
    }

    // ------------------------------------------------------------------ disposable email

    /**
     * @return array<string, array{0: string}>
     */
    public static function disposableEmails(): array
    {
        return [
            'mailinator' => ['someone@mailinator.com'],
            'upper case' => ['Someone@YOPMAIL.com'],
            'subdomain' => ['x@inbox.guerrillamail.com'],
            '10minutemail' => ['x@10minutemail.com'],
        ];
    }

    #[DataProvider('disposableEmails')]
    public function test_disposable_email_domains_are_refused(string $email): void
    {
        $this->postJson(self::URL, $this->payload(['email' => $email]))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('self_signup.validation.email_not_allowed'));

        $this->assertSame(0, SignupRequest::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_a_lookalike_domain_is_not_refused(): void
    {
        $this->start(['email' => 'owner@notmailinator.com']);
        $this->assertSame(1, SignupRequest::query()->count());
        $this->assertGreaterThanOrEqual(30, count((array) config('signup.blocked_email_domains')));
    }

    // ------------------------------------------------------------------ reserved subdomains

    /**
     * @return array<string, array{0: string}>
     */
    public static function reservedCodes(): array
    {
        return array_map(static fn (string $code): array => [$code], array_combine(
            $codes = ['www', 'admin', 'api', 'app', 'mail', 'smtp', 'support', 'help', 'billing', 'status', 'console', 'super', 'ibraa', 'test', 'dev', 'staging', 'cdn', 'static', 'assets'],
            $codes,
        ));
    }

    #[DataProvider('reservedCodes')]
    public function test_reserved_subdomains_are_refused(string $code): void
    {
        $this->assertContains($code, AvailableSubdomain::reserved());

        $this->postJson(self::URL, $this->payload(['subdomain' => $code]))
            ->assertStatus(422)
            ->assertJsonPath('errors.subdomain.0', __('signup.validation.subdomain_reserved'));
    }

    public function test_a_suggested_code_never_lands_on_a_reserved_name(): void
    {
        $this->start(['business_name' => 'Admin', 'subdomain' => null]);

        $this->assertNotSame('admin', SignupRequest::query()->sole()->preferred_subdomain);
        $this->assertNotContains(SignupRequest::query()->sole()->preferred_subdomain, AvailableSubdomain::reserved());
    }

    // ------------------------------------------------------------------ one free shop

    public function test_egyptian_phone_spellings_normalise_to_one_number(): void
    {
        foreach (['01012345678', '+201012345678', '00201012345678', '201012345678', '٠١٠١٢٣٤٥٦٧٨', '010 1234 5678'] as $typed) {
            $this->assertSame('01012345678', EgyptianPhone::normalize($typed));
        }

        $this->assertSame(['01012345678', '+201012345678', '00201012345678', '201012345678'], EgyptianPhone::variants('+20 10 1234 5678'));
    }

    public function test_an_email_with_a_free_shop_cannot_sign_up_for_another_free_shop(): void
    {
        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'Owner@Shop.test', 'phone' => '01099999999']);
        $this->endTenancy();

        $this->postJson(self::URL, $this->payload(['phone' => '01000000802']))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('self_signup.validation.free_shop_exists'));

        // A paid plan is not limited.
        $this->start(['plan_code' => 'basic', 'phone' => '01000000802']);
        $this->assertSame('basic', SignupRequest::query()->sole()->plan_code);
    }

    public function test_a_phone_with_a_free_shop_is_matched_in_any_spelling(): void
    {
        // Stored in the international form on the tenant; typed locally on the form.
        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'first@shop.test', 'phone' => '+201000000803']);
        $this->endTenancy();

        $this->postJson(self::URL, $this->payload(['email' => 'second@shop.test', 'phone' => '01000000803']))
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson(self::URL, $this->payload(['email' => 'third@shop.test', 'phone' => '00201000000803']))
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_a_paid_shop_does_not_use_up_the_free_one(): void
    {
        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $this->createTenant(['plan_id' => $basic->id, 'email' => 'owner@shop.test', 'phone' => '01000000801']);
        $this->endTenancy();

        $this->start();
        $this->assertSame(1, SignupRequest::query()->count());
    }

    public function test_a_verified_free_signup_blocks_a_second_one_but_an_unverified_one_does_not(): void
    {
        // Unverified: anyone could have typed this address, it blocks nobody.
        $this->start();
        $second = $this->start(['phone' => '01000000804']);
        $this->assertSame(2, SignupRequest::query()->count());

        // Verified (on its way to a free shop): the same email or phone is refused.
        $this->verify($second, $this->lastCode('owner@shop.test'))->assertOk();
        $this->postJson(self::URL, $this->payload(['email' => 'new@shop.test', 'phone' => '+201000000804']))
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson(self::URL, $this->payload(['phone' => '01000000899']))
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_the_rule_is_checked_again_at_verification(): void
    {
        // Two free signups for the same email, both sent before either was verified.
        $first = $this->start();
        $firstCode = $this->lastCode('owner@shop.test');
        $second = $this->start(['phone' => '01000000805']);
        $secondCode = $this->lastCode('owner@shop.test');

        $this->verify($first, $firstCode)->assertOk();

        $response = $this->verify($second, $secondCode)
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('self_signup.validation.free_shop_exists'));
        $this->assertNotNull($response->json('message'));

        $loser = SignupRequest::query()->where('signup_token_hash', SignupSecrets::hashToken($second))->sole();
        $this->assertSame(SignupRequestStatus::Rejected, $loser->status);
        $this->assertNull($loser->verification_code_hash);
        Queue::assertPushed(ProvisionVerifiedSignupJob::class, 1);

        // A free shop created in the meantime is caught the same way.
        $third = $this->start(['email' => 'late@shop.test', 'phone' => '01000000806']);
        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'late@shop.test']);
        $this->endTenancy();
        $this->verify($third, $this->lastCode('late@shop.test'))->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_paid_signups_are_not_checked_at_verification(): void
    {
        $first = $this->start();
        $firstCode = $this->lastCode('owner@shop.test');
        $paid = $this->start(['plan_code' => 'basic', 'phone' => '01000000807']);
        $paidCode = $this->lastCode('owner@shop.test');

        $this->verify($first, $firstCode)->assertOk();
        $this->verify($paid, $paidCode)->assertOk();
        Queue::assertPushed(ProvisionVerifiedSignupJob::class, 2);
    }

    // ------------------------------------------------------------------ cleanup

    public function test_unverified_signups_older_than_a_day_are_deleted_and_nothing_else(): void
    {
        $this->start(['email' => 'old@shop.test', 'phone' => '01000000811']);
        $verifiedToken = $this->start(['email' => 'kept-verified@shop.test', 'phone' => '01000000812', 'plan_code' => 'basic']);
        $this->verify($verifiedToken, $this->lastCode('kept-verified@shop.test'))->assertOk();
        $assisted = SignupRequest::query()->create([
            'business_name' => 'طلب يدوي', 'contact_name' => 'سارة', 'phone' => '01000000813',
            'business_type' => 'retail', 'status' => SignupRequestStatus::Pending->value,
        ]);
        $tenant = $this->createTenant();
        $this->endTenancy();

        $this->travel(23)->hours();
        $recent = $this->start(['email' => 'recent@shop.test', 'phone' => '01000000814']);

        $this->travel(2)->hours();
        $this->artisan('signup:prune-unverified')->assertSuccessful();

        $this->assertFalse(SignupRequest::query()->where('email', 'old@shop.test')->exists());
        $this->assertTrue(SignupRequest::query()->where('signup_token_hash', SignupSecrets::hashToken($recent))->exists(), 'Younger than 24 h.');
        $this->assertSame(SignupRequestStatus::Verified, SignupRequest::query()->where('email', 'kept-verified@shop.test')->sole()->status);
        $this->assertNotNull($assisted->fresh());
        $this->assertTrue(Tenant::query()->whereKey($tenant->getTenantKey())->exists());
        $this->assertSame(1, SignupRequest::query()->where('signup_flow', SignupFlow::SelfSignup->value)->where('status', SignupRequestStatus::AwaitingVerification->value)->count());
    }

    public function test_the_cleanup_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('signup:prune-unverified')->assertSuccessful();
    }
}
