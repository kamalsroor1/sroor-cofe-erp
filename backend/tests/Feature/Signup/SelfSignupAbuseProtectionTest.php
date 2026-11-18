<?php

declare(strict_types=1);

namespace Tests\Feature\Signup;

use App\Actions\Signup\ProvisionVerifiedSignupAction;
use App\Actions\Signup\RejectSignupRequestAction;
use App\DTOs\CreateTenantDTO;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Enums\TenantSignupSource;
use App\Jobs\NotifyPlatformOfSelfSignupIssueJob;
use App\Jobs\NotifyPlatformOfSignupRequestJob;
use App\Jobs\ProvisionVerifiedSignupJob;
use App\Mail\SignupShopExistsMail;
use App\Mail\SignupVerificationCodeMail;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Rules\AvailableSubdomain;
use App\Services\Signup\SelfSignupOutcomes;
use App\Support\Signup\EgyptianPhone;
use App\Support\Signup\SignupIdentity;
use App\Support\Signup\SignupNetwork;
use App\Support\Signup\SignupSecrets;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * SIGN-13 + security review: abuse protection of the public self-signup: one shop per canonical
 * email and per normalised phone for every self-signup plan (checked at verification only, never
 * at start, with a generic answer and an owner mail), link-free names and a code mail without user
 * input, code budgets per mailbox / network / platform day, the per-network provisioning cap, the
 * signup token's lifetime, password hashes dropped on final states, disposable email domains,
 * reserved subdomains, and the daily cleanup.
 */
final class SelfSignupAbuseProtectionTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

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

    // ------------------------------------------------------------------ one shop per identity

    public function test_egyptian_phone_spellings_normalise_to_one_number(): void
    {
        foreach (['01012345678', '+201012345678', '00201012345678', '201012345678', '٠١٠١٢٣٤٥٦٧٨', '010 1234 5678'] as $typed) {
            $this->assertSame('01012345678', EgyptianPhone::normalize($typed));
            $this->assertSame('01012345678', SignupIdentity::normalizedPhone($typed));
        }

        $this->assertSame(['01012345678', '+201012345678', '00201012345678', '201012345678'], EgyptianPhone::variants('+20 10 1234 5678'));
        $this->assertSame('01012345678', SignupIdentity::normalizedPhone('+20 (101) 234-5678'));
        $this->assertSame('966501234567', SignupIdentity::normalizedPhone('+966 50 123 4567'));
        $this->assertNull(SignupIdentity::normalizedPhone(' - '));
    }

    public function test_emails_are_canonicalised(): void
    {
        $this->assertSame('myshop@gmail.com', SignupIdentity::canonicalEmail('My.Shop+promo@GoogleMail.com'));
        $this->assertSame('myshop@gmail.com', SignupIdentity::canonicalEmail('m.y.s.h.o.p@gmail.com'));
        $this->assertSame('owner@shop.test', SignupIdentity::canonicalEmail(' Owner+x@Shop.test '));
        // Dots only matter outside gmail.
        $this->assertSame('my.shop@shop.test', SignupIdentity::canonicalEmail('my.shop@shop.test'));
        $this->assertNull(SignupIdentity::canonicalEmail('  '));
    }

    public function test_start_answers_the_same_201_for_a_taken_and_a_new_identity(): void
    {
        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'owner@shop.test', 'phone' => '01000000801']);
        $this->endTenancy();

        $taken = $this->postJson(self::URL, $this->payload(['subdomain' => 'taken-one']))->assertCreated();
        $fresh = $this->postJson(self::URL, $this->payload(['email' => 'new@shop.test', 'phone' => '01000000899', 'subdomain' => 'fresh-one']))->assertCreated();

        $this->assertSame($fresh->json('message'), $taken->json('message'));
        $this->assertSame(array_keys((array) $fresh->json('data')), array_keys((array) $taken->json('data')));
        $this->assertSame($fresh->json('data.code_expires_in'), $taken->json('data.code_expires_in'));
        $this->assertSame(2, SignupRequest::query()->count());
        Mail::assertQueued(SignupVerificationCodeMail::class, 2);
    }

    public function test_verify_refuses_an_email_that_owns_a_shop_generically_and_mails_the_owner(): void
    {
        $owned = $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'Owner@Shop.test', 'phone' => '01099999999']);
        $this->endTenancy();

        $token = $this->start(['phone' => '01000000802']);
        $response = $this->verify($token, $this->lastCode('owner@shop.test'))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('self_signup.errors.shop_exists_contact_support'));
        $this->assertSame(['email'], array_keys((array) $response->json('errors')));

        $signup = SignupRequest::query()->where('signup_token_hash', SignupSecrets::hashToken($token))->sole();
        $this->assertSame(SignupRequestStatus::Rejected, $signup->status);
        $this->assertNotNull($signup->email_verified_at);
        $this->assertNull($signup->password_hash, 'A final row keeps no password hash.');
        $this->assertNull($signup->verification_code_hash);
        $this->assertNotNull($signup->terminal_at);
        Queue::assertNotPushed(ProvisionVerifiedSignupJob::class);

        Mail::assertQueued(SignupShopExistsMail::class, fn (SignupShopExistsMail $mail): bool => $mail->hasTo('owner@shop.test')
            && $mail->loginUrl === SelfSignupOutcomes::loginUrl((string) $owned->slug));
        $html = (new SignupShopExistsMail(SelfSignupOutcomes::loginUrl((string) $owned->slug), 'ar'))->render();
        $this->assertStringContainsString((string) $owned->slug, $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('dir="ltr"', (new SignupShopExistsMail(null, 'en'))->render());
    }

    public function test_a_phone_match_is_refused_without_a_sign_in_link_and_with_the_same_answer(): void
    {
        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'first@shop.test', 'phone' => '+201000000803']);
        $this->endTenancy();

        $token = $this->start(['email' => 'second@shop.test', 'phone' => '01000000803']);
        $this->verify($token, $this->lastCode('second@shop.test'))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('self_signup.errors.shop_exists_contact_support'));

        Mail::assertQueued(SignupShopExistsMail::class, fn (SignupShopExistsMail $mail): bool => $mail->hasTo('second@shop.test') && $mail->loginUrl === null);
    }

    public function test_gmail_aliases_and_plus_tags_are_one_identity(): void
    {
        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'my.shop@gmail.com']);
        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'owner@shop.test']);
        $this->endTenancy();

        $gmail = $this->start(['email' => 'MyShop+promo@googlemail.com', 'phone' => '01000000820']);
        $this->verify($gmail, $this->lastCode('myshop+promo@googlemail.com'))->assertStatus(422)->assertJsonValidationErrors(['email']);

        $tagged = $this->start(['email' => 'owner+second@shop.test', 'phone' => '01000000821']);
        $this->verify($tagged, $this->lastCode('owner+second@shop.test'))->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->assertSame('myshop@gmail.com', SignupRequest::query()->where('signup_token_hash', SignupSecrets::hashToken($gmail))->sole()->canonical_email);
    }

    public function test_a_formatted_phone_on_a_console_shop_is_matched(): void
    {
        // The console normalises what it stores; the comparable column is digits only either way.
        $tenant = $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'console@shop.test', 'phone' => '+20 (100) 000-0809']);
        $this->endTenancy();
        $this->assertSame('01000000809', $tenant->fresh()?->phone_normalized);

        $token = $this->start(['email' => 'other@shop.test', 'phone' => '0100 000 0809']);
        $this->verify($token, $this->lastCode('other@shop.test'))->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_a_paid_plan_self_signup_cannot_bypass_the_one_shop_rule(): void
    {
        // A self-signup shop on a PAID plan (its trial ends on the free plan) counts.
        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $this->createTenant(['plan_id' => $basic->id, 'email' => 'owner@shop.test', 'signup_source' => 'self_signup']);
        $this->endTenancy();

        $paid = $this->start(['plan_code' => 'basic', 'phone' => '01000000830']);
        $this->verify($paid, $this->lastCode('owner@shop.test'))->assertStatus(422)->assertJsonValidationErrors(['email']);

        $free = $this->start(['phone' => '01000000831']);
        $this->verify($free, $this->lastCode('owner@shop.test'))->assertStatus(422)->assertJsonValidationErrors(['email']);
        Queue::assertNotPushed(ProvisionVerifiedSignupJob::class);
    }

    public function test_a_free_shop_blocks_a_paid_self_signup_too(): void
    {
        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'owner@shop.test']);
        $this->endTenancy();

        $paid = $this->start(['plan_code' => 'basic', 'phone' => '01000000832']);
        $this->verify($paid, $this->lastCode('owner@shop.test'))->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_a_console_paid_shop_does_not_use_up_the_self_signup(): void
    {
        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $this->createTenant(['plan_id' => $basic->id, 'email' => 'owner@shop.test', 'phone' => '01000000801']);
        $this->endTenancy();

        $token = $this->start();
        $this->verify($token, $this->lastCode('owner@shop.test'))->assertOk();
        Queue::assertPushed(ProvisionVerifiedSignupJob::class, 1);
    }

    public function test_a_verified_signup_blocks_a_second_one_but_an_unverified_one_does_not(): void
    {
        // Unverified: anyone could have typed this address, it blocks nobody.
        $this->start();
        $second = $this->start(['phone' => '01000000804']);
        $this->assertSame(2, SignupRequest::query()->count());

        // Verified (on its way to a shop): the same phone, with another email, is refused at verify.
        $this->verify($second, $this->lastCode('owner@shop.test'))->assertOk();
        $third = $this->start(['email' => 'new@shop.test', 'phone' => '+201000000804']);
        $this->verify($third, $this->lastCode('new@shop.test'))->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_the_first_verified_twin_wins(): void
    {
        // Two signups for the same email, both sent before either was verified, one paid.
        $first = $this->start();
        $firstCode = $this->lastCode('owner@shop.test');
        $second = $this->start(['phone' => '01000000805', 'plan_code' => 'basic']);
        $secondCode = $this->lastCode('owner@shop.test');

        $this->verify($first, $firstCode)->assertOk();
        $this->verify($second, $secondCode)
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('self_signup.errors.shop_exists_contact_support'));

        $loser = SignupRequest::query()->where('signup_token_hash', SignupSecrets::hashToken($second))->sole();
        $this->assertSame(SignupRequestStatus::Rejected, $loser->status);
        Queue::assertPushed(ProvisionVerifiedSignupJob::class, 1);
        Mail::assertQueued(SignupShopExistsMail::class, 1);
    }

    public function test_a_shop_created_between_verification_and_provisioning_is_caught_under_the_lock(): void
    {
        $token = $this->start(['phone' => '01000000806']);
        $this->verify($token, $this->lastCode('owner@shop.test'))->assertOk();
        $signup = SignupRequest::query()->where('signup_token_hash', SignupSecrets::hashToken($token))->sole();

        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'owner@shop.test']);
        $this->endTenancy();

        $result = app(ProvisionVerifiedSignupAction::class)->execute((int) $signup->id);

        $this->assertSame(SignupRequestStatus::Rejected, $result->status);
        $this->assertNull($result->fresh()?->tenant_id);
        $this->assertNull($result->fresh()?->password_hash);
        $this->assertSame(1, Tenant::query()->where('email', 'owner@shop.test')->count());
        Mail::assertQueued(SignupShopExistsMail::class, fn (SignupShopExistsMail $mail): bool => $mail->hasTo('owner@shop.test') && $mail->loginUrl !== null);
    }

    // ------------------------------------------------------------------ phishing through the code mail

    public function test_the_code_mail_carries_no_user_input(): void
    {
        $this->start(['business_name' => 'محل الاختبار الخاص', 'owner_name' => 'كريم المالك']);

        $this->assertFalse(property_exists(SignupVerificationCodeMail::class, 'businessName'));
        Mail::assertQueued(SignupVerificationCodeMail::class, function (SignupVerificationCodeMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, $mail->code)
                && ! str_contains($html, 'محل الاختبار الخاص')
                && ! str_contains($html, 'كريم المالك');
        });
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function linkyNames(): array
    {
        return [
            'domain in business' => ['business_name', 'Free gift at claim-prize.com'],
            'scheme' => ['business_name', 'http://evil'],
            'www' => ['owner_name', 'www.evil'],
            'at sign' => ['owner_name', 'support@bank'],
            'egyptian tld' => ['business_name', 'محل bank.eg'],
            'arabic label' => ['business_name', 'مكافأة.com'],
            'lookalike dot' => ['owner_name', "evil\u{2024}link"],
        ];
    }

    #[DataProvider('linkyNames')]
    public function test_names_with_links_are_refused(string $field, string $value): void
    {
        $this->postJson(self::URL, $this->payload([$field => $value]))
            ->assertStatus(422)
            ->assertJsonPath('errors.'.$field.'.0', __('self_signup.validation.no_links'));

        $this->assertSame(0, SignupRequest::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_ordinary_names_with_dots_are_accepted(): void
    {
        $this->start(['business_name' => 'Coffee Co. Ltd', 'owner_name' => 'Mr. Me']);
        $this->assertSame(1, SignupRequest::query()->count());
    }

    // ------------------------------------------------------------------ code email budgets

    public function test_the_platform_daily_code_ceiling_answers_503_and_alerts_once(): void
    {
        Queue::fake([ProvisionVerifiedSignupJob::class, NotifyPlatformOfSignupRequestJob::class, NotifyPlatformOfSelfSignupIssueJob::class]);
        config(['signup.max_codes_per_day' => 2]);

        $token = $this->start(['email' => 'a@shop.test']);
        $this->start(['email' => 'b@shop.test', 'phone' => '01000000840']);

        $response = $this->postJson(self::URL, $this->payload(['email' => 'c@shop.test', 'phone' => '01000000841']))
            ->assertStatus(503)
            ->assertHeader('Retry-After')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('self_signup.errors.codes_paused'));
        $this->assertGreaterThan(0, (int) $response->json('retry_after'));
        $this->assertSame(2, SignupRequest::query()->count());

        $this->travel(61)->seconds();
        $this->postJson(self::URL.'/resend', ['token' => $token])->assertStatus(503);
        Mail::assertQueuedCount(2);
        Queue::assertPushed(NotifyPlatformOfSelfSignupIssueJob::class, fn (NotifyPlatformOfSelfSignupIssueJob $job): bool => $job->issue === NotifyPlatformOfSelfSignupIssueJob::ISSUE_CODES_CEILING && $job->cap === 2);
        Queue::assertPushed(NotifyPlatformOfSelfSignupIssueJob::class, 1);
    }

    public function test_code_budgets_follow_the_mailbox_across_networks(): void
    {
        $from = fn (string $ip): static => $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        // Five per mailbox + network (a gmail alias is the same mailbox).
        $tokenA = (string) $from('198.51.100.10')->postJson(self::URL, $this->payload(['email' => 'victim@gmail.com']))->assertCreated()->json('data.signup_token');
        for ($i = 0; $i < 4; $i++) {
            $this->travel(61)->seconds();
            $from('198.51.100.10')->postJson(self::URL.'/resend', ['token' => $tokenA])->assertOk();
        }
        $this->travel(61)->seconds();
        $from('198.51.100.10')->postJson(self::URL.'/resend', ['token' => $tokenA])->assertStatus(429);

        // Another network gets its own five...
        $tokenB = (string) $from('203.0.113.20')->postJson(self::URL, $this->payload(['email' => 'v.i.c.t.i.m+b@gmail.com', 'phone' => '01000000850']))->assertCreated()->json('data.signup_token');
        for ($i = 0; $i < 4; $i++) {
            $this->travel(61)->seconds();
            $from('203.0.113.20')->postJson(self::URL.'/resend', ['token' => $tokenB])->assertOk();
        }
        Mail::assertQueuedCount(10);

        // ...but ten per mailbox and hour whatever the network.
        $from('192.0.2.30')->postJson(self::URL, $this->payload(['email' => 'victim@googlemail.com', 'phone' => '01000000851']))->assertStatus(429);

        // Fifteen per mailbox and day.
        $this->travel(61)->minutes();
        $tokenC = (string) $from('192.0.2.30')->postJson(self::URL, $this->payload(['email' => 'victim@gmail.com', 'phone' => '01000000852']))->assertCreated()->json('data.signup_token');
        for ($i = 0; $i < 4; $i++) {
            $this->travel(61)->seconds();
            $from('192.0.2.30')->postJson(self::URL.'/resend', ['token' => $tokenC])->assertOk();
        }
        Mail::assertQueuedCount(15);
        $this->travel(61)->minutes();
        $from('100.64.0.40')->postJson(self::URL, $this->payload(['email' => 'victim@gmail.com', 'phone' => '01000000853']))->assertStatus(429);
        Mail::assertQueuedCount(15);
    }

    // ------------------------------------------------------------------ token and secrets

    public function test_the_token_dies_an_hour_after_a_final_state_and_the_prune_drops_it(): void
    {
        $this->createTenant(['plan_id' => $this->freePlan()->id, 'email' => 'owner@shop.test']);
        $this->endTenancy();
        $token = $this->start(['phone' => '01000000860']);
        $this->verify($token, $this->lastCode('owner@shop.test'))->assertStatus(422);

        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->travel(59)->minutes();
        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])->assertOk();

        $this->travel(2)->minutes();
        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])
            ->assertStatus(422)->assertJsonPath('errors.token.0', __('self_signup.errors.token_invalid'));
        $this->postJson(self::URL.'/resend', ['token' => $token])->assertStatus(422)->assertJsonValidationErrors(['token']);

        $this->artisan('signup:prune-unverified')->assertSuccessful();
        $this->assertNull(SignupRequest::query()->where('email', 'owner@shop.test')->sole()->signup_token_hash);
    }

    public function test_every_final_transition_drops_the_password_hash(): void
    {
        $this->seedCentralPlatformRoles();
        $operator = $this->centralSuperAdmin();

        $failedToken = $this->start(['email' => 'failed@shop.test', 'phone' => '01000000870']);
        $this->verify($failedToken, $this->lastCode('failed@shop.test'))->assertOk();
        $failed = SignupRequest::query()->where('email', 'failed@shop.test')->sole();
        $this->assertNotNull($failed->password_hash);
        app(SelfSignupOutcomes::class)->failed((int) $failed->id, SelfSignupOutcomes::FAILURE_REGISTRATION);
        $failed->refresh();
        $this->assertSame(SignupRequestStatus::Failed, $failed->status);
        $this->assertNull($failed->password_hash);
        $this->assertNotNull($failed->terminal_at);

        // A held (pending) self-signup rejected by an operator.
        $heldToken = $this->start(['email' => 'held@shop.test', 'phone' => '01000000871']);
        $this->verify($heldToken, $this->lastCode('held@shop.test'))->assertOk();
        $held = SignupRequest::query()->where('email', 'held@shop.test')->sole();
        $held->forceFill(['status' => SignupRequestStatus::Pending->value])->save();
        app(RejectSignupRequestAction::class)->execute((int) $held->id, 'spam', $operator);
        $held->refresh();
        $this->assertSame(SignupRequestStatus::Rejected, $held->status);
        $this->assertNull($held->password_hash);
        $this->assertNotNull($held->terminal_at);
    }

    public function test_the_tenant_dto_cannot_be_told_from_an_array_that_the_password_is_a_hash(): void
    {
        $free = $this->freePlan();
        $dto = CreateTenantDTO::fromArray([
            'name' => 'x', 'slug' => 'x-shop', 'email' => 'x@shop.test', 'plan_id' => $free->id,
            'password' => '$2y$12$abcdefghijklmnopqrstuuJ1n0yq9Zc1n0yq9Zc1n0yq9Zc1n0yq', 'password_is_hashed' => true,
        ]);
        $this->assertFalse($dto->passwordIsHashed);

        $selfSignup = CreateTenantDTO::forSelfSignup('x', 'x-shop', 'x@shop.test', null, (int) $free->id, 'hash', 0);
        $this->assertTrue($selfSignup->passwordIsHashed);
        $this->assertFalse($selfSignup->ownerMustChangePassword);
        $this->assertSame(TenantSignupSource::SelfSignup, $selfSignup->signupSource);
    }

    // ------------------------------------------------------------------ per-network provisioning cap

    public function test_client_networks_are_grouped_by_prefix(): void
    {
        $this->assertSame('203.0.113.0/24', SignupNetwork::of('203.0.113.77'));
        $this->assertSame('203.0.113.0/24', SignupNetwork::of('::ffff:203.0.113.5'));
        $this->assertSame('2001:db8:1::/48', SignupNetwork::of('2001:db8:1:2:3:4:5:6'));
        $this->assertNull(SignupNetwork::of('not-an-ip'));
        $this->assertNull(SignupNetwork::of(null));
    }

    public function test_a_network_over_its_daily_cap_goes_to_the_manual_queue(): void
    {
        Queue::fake([ProvisionVerifiedSignupJob::class, NotifyPlatformOfSignupRequestJob::class, NotifyPlatformOfSelfSignupIssueJob::class]);

        // Three automatic provisionings already started today from 203.0.113.0/24.
        foreach ([1, 2, 3] as $i) {
            $row = SignupRequest::query()->create([
                'business_name' => 'محل '.$i, 'contact_name' => 'س', 'phone' => '0100000088'.$i, 'email' => 'net'.$i.'@shop.test',
                'business_type' => 'retail', 'status' => SignupRequestStatus::Provisioned->value,
                'signup_flow' => 'self_signup', 'ip' => '203.0.113.'.$i,
            ]);
            $row->forceFill(['tenant_id' => 'net-'.$i, 'provisioning_started_at' => now()->subMinutes(5)])->save();
        }

        $token = (string) $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.200'])
            ->postJson(self::URL, $this->payload(['email' => 'fourth@shop.test', 'phone' => '01000000889']))
            ->assertCreated()->json('data.signup_token');
        $this->verify($token, $this->lastCode('fourth@shop.test'))->assertOk();
        $signup = SignupRequest::query()->where('email', 'fourth@shop.test')->sole();
        $this->assertSame('203.0.113.0/24', $signup->ip_network);

        $result = app(ProvisionVerifiedSignupAction::class)->execute((int) $signup->id);

        $this->assertSame(SignupRequestStatus::Pending, $result->status);
        $this->assertNull($result->fresh()?->tenant_id);
        $this->assertFalse(Tenant::query()->where('email', 'fourth@shop.test')->exists());
        Queue::assertPushed(NotifyPlatformOfSelfSignupIssueJob::class, fn (NotifyPlatformOfSelfSignupIssueJob $job): bool => $job->issue === NotifyPlatformOfSelfSignupIssueJob::ISSUE_NETWORK_CAP && $job->cap === 3);
    }

    // ------------------------------------------------------------------ cleanup

    public function test_unverified_signups_idle_for_a_day_are_deleted_and_nothing_else(): void
    {
        $this->start(['email' => 'old@shop.test', 'phone' => '01000000811']);
        $verifiedToken = $this->start(['email' => 'kept-verified@shop.test', 'phone' => '01000000812', 'plan_code' => 'basic']);
        $this->verify($verifiedToken, $this->lastCode('kept-verified@shop.test'))->assertOk();
        $resent = $this->start(['email' => 'resent@shop.test', 'phone' => '01000000815']);
        $assisted = SignupRequest::query()->create([
            'business_name' => 'طلب يدوي', 'contact_name' => 'سارة', 'phone' => '01000000813',
            'business_type' => 'retail', 'status' => SignupRequestStatus::Pending->value,
        ]);
        $tenant = $this->createTenant();
        $this->endTenancy();

        $this->travel(23)->hours();
        $recent = $this->start(['email' => 'recent@shop.test', 'phone' => '01000000814']);
        // A code resent at 23 h keeps its row alive (idle = max(created_at, verification_sent_at)).
        $this->postJson(self::URL.'/resend', ['token' => $resent])->assertOk();

        $this->travel(2)->hours();
        $this->artisan('signup:prune-unverified')->assertSuccessful();

        $this->assertFalse(SignupRequest::query()->where('email', 'old@shop.test')->exists());
        $this->assertTrue(SignupRequest::query()->where('signup_token_hash', SignupSecrets::hashToken($recent))->exists(), 'Younger than 24 h.');
        $this->assertTrue(SignupRequest::query()->where('email', 'resent@shop.test')->exists(), 'Its last code is younger than 24 h.');
        $this->assertSame(SignupRequestStatus::Verified, SignupRequest::query()->where('email', 'kept-verified@shop.test')->sole()->status);
        $this->assertNotNull($assisted->fresh());
        $this->assertTrue(Tenant::query()->whereKey($tenant->getTenantKey())->exists());

        $this->travel(23)->hours();
        $this->artisan('signup:prune-unverified')->assertSuccessful();
        $this->assertFalse(SignupRequest::query()->where('email', 'resent@shop.test')->exists());
        $this->assertSame(0, SignupRequest::query()->where('signup_flow', SignupFlow::SelfSignup->value)->where('status', SignupRequestStatus::AwaitingVerification->value)->count());
    }

    public function test_final_rows_older_than_thirty_days_lose_their_secrets_and_ip(): void
    {
        $make = function (string $email, int $daysAgo): SignupRequest {
            $row = SignupRequest::query()->create([
                'business_name' => 'محل', 'contact_name' => 'س', 'phone' => '01000000890', 'email' => $email,
                'business_type' => 'retail', 'status' => SignupRequestStatus::Rejected->value,
                'signup_flow' => 'self_signup', 'ip' => '198.51.100.7',
            ]);
            $row->forceFill([
                'password_hash' => 'h', 'signup_token_hash' => hash('sha256', $email), 'verification_code_hash' => 'c',
                'terminal_at' => now()->subDays($daysAgo),
            ])->save();

            return $row;
        };
        $old = $make('old-final@shop.test', 31);
        $young = $make('young-final@shop.test', 29);

        $this->artisan('signup:prune-unverified')->assertSuccessful();

        $old->refresh();
        $this->assertNull($old->password_hash);
        $this->assertNull($old->signup_token_hash);
        $this->assertNull($old->verification_code_hash);
        $this->assertNull($old->ip);
        $this->assertNull($old->ip_network);
        $this->assertSame('old-final@shop.test', $old->email, 'The business record stays.');

        $young->refresh();
        $this->assertSame('198.51.100.7', $young->ip);
        $this->assertSame('h', $young->password_hash);
        $this->assertNull($young->signup_token_hash, 'Final for more than an hour: token revoked.');
    }

    public function test_the_cleanup_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('signup:prune-unverified')->assertSuccessful();
    }
}
