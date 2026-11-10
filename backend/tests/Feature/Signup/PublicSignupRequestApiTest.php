<?php

declare(strict_types=1);

namespace Tests\Feature\Signup;

use App\Enums\SignupRequestStatus;
use App\Jobs\NotifyPlatformOfSignupRequestJob;
use App\Models\SignupRequest;
use App\Models\Tenant;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TenantTestCase;

/**
 * PRD-05: POST /api/v1/public/signup-requests (public, throttled, central DB).
 */
final class PublicSignupRequestApiTest extends TenantTestCase
{
    private const URL = '/api/v1/public/signup-requests';

    protected function setUp(): void
    {
        parent::setUp();

        $this->endTenancy();
        $this->seed(PlansAndFeaturesSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'محمصة الاختبار',
            'contact_name' => 'أحمد علي',
            'phone' => '01000000601',
            'email' => 'owner@example.test',
            'city' => 'القاهرة',
            'business_type' => 'weighted',
            'plan_code' => 'basic',
            'preferred_subdomain' => 'test-roastery',
            'notes' => 'نريد البدء الأسبوع القادم',
        ], $overrides);
    }

    public function test_it_stores_a_pending_request_and_returns_only_a_message(): void
    {
        Queue::fake();

        $response = $this->postJson(self::URL, $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('signup.submitted'));

        $this->assertArrayNotHasKey('id', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());

        $row = SignupRequest::query()->sole();
        $this->assertSame(SignupRequestStatus::Pending, $row->status);
        $this->assertSame('01000000601', $row->phone);
        $this->assertSame('basic', $row->plan_code);
        $this->assertSame('test-roastery', $row->preferred_subdomain);
        $this->assertNotNull($row->ip);
        $this->assertNull($row->tenant_id);

        Queue::assertPushed(NotifyPlatformOfSignupRequestJob::class, fn (NotifyPlatformOfSignupRequestJob $job): bool => $job->signupRequestId === $row->id);
    }

    public function test_optional_fields_may_be_omitted_and_the_phone_is_normalised(): void
    {
        Queue::fake();

        $this->postJson(self::URL, [
            'business_name' => 'محل بسيط',
            'contact_name' => 'سارة',
            'phone' => '٠١٠٠٠٠٠٠٦٠٢',
            'business_type' => 'retail',
        ])->assertCreated();

        $row = SignupRequest::query()->sole();
        $this->assertSame('01000000602', $row->phone);
        $this->assertNull($row->email);
        $this->assertNull($row->plan_code);
        $this->assertNull($row->preferred_subdomain);
    }

    public function test_it_needs_no_authentication_and_ignores_tenant_selection(): void
    {
        Queue::fake();

        $this->postJson(self::URL, $this->payload(), ['X-Tenant' => 'does-not-exist'])->assertCreated();
        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->postJson(self::URL, [])->assertStatus(422)->assertJsonValidationErrors(['business_name', 'contact_name', 'phone', 'business_type']);

        foreach (['0100000060', '01300000601', 'abc', '02212345678', '010000006011'] as $phone) {
            $this->postJson(self::URL, $this->payload(['phone' => $phone]))->assertStatus(422)->assertJsonValidationErrors(['phone']);
        }

        $this->postJson(self::URL, $this->payload(['business_type' => 'airline']))->assertStatus(422)->assertJsonValidationErrors(['business_type']);
        $this->postJson(self::URL, $this->payload(['email' => 'not-an-email']))->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson(self::URL, $this->payload(['plan_code' => 'no-such-plan']))->assertStatus(422)->assertJsonValidationErrors(['plan_code']);
        $this->postJson(self::URL, $this->payload(['notes' => str_repeat('x', 501)]))->assertStatus(422)->assertJsonValidationErrors(['notes']);

        $this->assertSame(0, SignupRequest::query()->count());
    }

    public function test_subdomain_rules(): void
    {
        foreach (['ab', 'UPPER_case', '-leading', 'trailing-', 'has space', str_repeat('a', 31)] as $bad) {
            $this->postJson(self::URL, $this->payload(['preferred_subdomain' => $bad]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['preferred_subdomain']);
        }

        // Reserved list (config) and a slug a tenant already owns.
        $this->postJson(self::URL, $this->payload(['preferred_subdomain' => 'admin']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preferred_subdomain']);

        $this->createTenant(['id' => 'already-taken', 'slug' => 'already-taken']);
        $this->endTenancy();
        // The public form never says whether a code is taken (no enumeration); the approve step checks it.
        Queue::fake();
        $this->postJson(self::URL, $this->payload(['preferred_subdomain' => 'already-taken']))->assertCreated();
    }

    public function test_a_second_pending_request_with_the_same_phone_is_refused(): void
    {
        Queue::fake();

        $this->postJson(self::URL, $this->payload())->assertCreated();

        $this->postJson(self::URL, $this->payload(['business_name' => 'محل آخر']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', __('signup.validation.duplicate_pending'));

        $this->assertSame(1, SignupRequest::query()->count());

        // Once handled, the same phone may ask again.
        SignupRequest::query()->update(['status' => SignupRequestStatus::Rejected->value]);
        $this->postJson(self::URL, $this->payload())->assertCreated();
        $this->assertSame(2, SignupRequest::query()->count());
    }

    public function test_a_filled_honeypot_answers_201_but_stores_and_notifies_nothing(): void
    {
        Queue::fake();

        $this->postJson(self::URL, $this->payload(['website' => 'http://spam.example.test']))
            ->assertCreated()
            ->assertJsonPath('message', __('signup.submitted'));

        $this->assertSame(0, SignupRequest::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_the_endpoint_is_throttled_per_ip(): void
    {
        Queue::fake();
        $perHour = (int) config('rate_limits.signup_requests.per_hour', 3);

        for ($i = 0; $i < $perHour; $i++) {
            $this->postJson(self::URL, $this->payload(['phone' => '0100000070'.$i]))->assertCreated();
        }

        $this->postJson(self::URL, $this->payload(['phone' => '01000000799']))->assertStatus(429);
        $this->assertSame($perHour, SignupRequest::query()->count());
    }

    public function test_the_owner_is_notified_through_the_platform_bot_when_configured(): void
    {
        config(['services.telegram.bot_token' => 'test-token', 'signup.notify.telegram_chat_id' => '12345']);
        Http::fake();
        $request = SignupRequest::query()->create([
            'business_name' => 'محل', 'contact_name' => 'علي', 'phone' => '01000000603',
            'business_type' => 'retail', 'status' => 'pending',
        ]);

        (new NotifyPlatformOfSignupRequestJob($request->id))->handle();

        Http::assertSent(fn ($sent): bool => str_contains($sent->url(), 'sendMessage')
            && str_contains((string) $sent['text'], '01000000603')
            && (string) $sent['chat_id'] === '12345');
    }

    public function test_a_notification_failure_never_throws_and_no_channel_means_no_call(): void
    {
        $request = SignupRequest::query()->create([
            'business_name' => 'محل', 'contact_name' => 'علي', 'phone' => '01000000604',
            'business_type' => 'retail', 'status' => 'pending',
        ]);

        config(['services.telegram.bot_token' => '', 'services.telegram.chat_id' => '']);
        Http::fake();
        (new NotifyPlatformOfSignupRequestJob($request->id))->handle();
        Http::assertNothingSent();

        config(['services.telegram.bot_token' => 'test-token', 'signup.notify.telegram_chat_id' => '12345']);
        Http::fake(fn () => throw new \RuntimeException('network down'));
        (new NotifyPlatformOfSignupRequestJob($request->id))->handle();

        $this->assertTrue(true); // reaching here = nothing escaped the job
    }

    public function test_ipv6_clients_share_one_bucket_per_64_prefix(): void
    {
        Queue::fake();
        $perHour = (int) config('rate_limits.signup_requests.per_hour', 3);

        for ($i = 0; $i < $perHour; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::'.($i + 1)])
                ->postJson(self::URL, $this->payload(['phone' => '0100000080'.$i]))->assertCreated();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2:ffff::9'])
            ->postJson(self::URL, $this->payload(['phone' => '01000000899']))->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:3::1'])
            ->postJson(self::URL, $this->payload(['phone' => '01000000898']))->assertCreated();
    }

    public function test_a_global_ceiling_applies_across_all_ips(): void
    {
        Queue::fake();
        config(['rate_limits.signup_requests.global_per_hour' => 2]);

        foreach (['203.0.113.1', '203.0.113.2'] as $i => $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson(self::URL, $this->payload(['phone' => '0100000090'.$i]))->assertCreated();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.3'])
            ->postJson(self::URL, $this->payload(['phone' => '01000000999']))->assertStatus(429);
    }

    public function test_it_refuses_new_requests_when_too_many_are_pending(): void
    {
        Queue::fake();
        config(['signup.max_pending' => 1]);

        $this->postJson(self::URL, $this->payload(['phone' => '01000000501']))->assertCreated();

        $this->postJson(self::URL, $this->payload(['phone' => '01000000502']))
            ->assertStatus(503)
            ->assertJsonPath('message', __('signup.errors.queue_full'));

        $this->assertSame(1, SignupRequest::query()->count());
    }

    public function test_a_backlog_sends_one_summary_alert_per_hour_instead_of_one_per_request(): void
    {
        Queue::fake();
        Cache::forget('signup:backlog-alert');
        config(['signup.quiet_above_pending' => 1]);

        $this->postJson(self::URL, $this->payload(['phone' => '01000000511']))->assertCreated();
        Queue::assertPushed(NotifyPlatformOfSignupRequestJob::class, fn ($j): bool => $j->backlogSummary === false);

        $this->postJson(self::URL, $this->payload(['phone' => '01000000512']))->assertCreated();
        $this->postJson(self::URL, $this->payload(['phone' => '01000000513']))->assertCreated();

        Queue::assertPushed(NotifyPlatformOfSignupRequestJob::class, 2);
        Queue::assertPushed(NotifyPlatformOfSignupRequestJob::class, fn ($j): bool => $j->backlogSummary === true);
    }

    public function test_reserved_and_idn_style_codes_are_rejected_on_the_public_form(): void
    {
        foreach (['webmail', 'xn--abc-123', 'ab--cd'] as $i => $code) {
            $this->postJson(self::URL, $this->payload(['preferred_subdomain' => $code, 'phone' => '0100000053'.$i]))
                ->assertStatus(422)->assertJsonValidationErrors(['preferred_subdomain']);
        }
    }

    public function test_single_line_fields_are_stripped_of_control_characters_and_notes_keep_newlines(): void
    {
        Queue::fake();

        $this->postJson(self::URL, $this->payload([
            'business_name' => "  Shop\n\rNew:   Line\x00  ",
            'contact_name' => "Ali\tBob\u{202E}",
            'city' => "Cairo\nGiza",
            'notes' => "line1\r\nline2\x07\x00",
        ]))->assertCreated();

        $row = SignupRequest::query()->sole();
        $this->assertSame('Shop New: Line', $row->business_name);
        $this->assertSame('Ali Bob', $row->contact_name);
        $this->assertSame('Cairo Giza', $row->city);
        $this->assertSame("line1\nline2", $row->notes);
    }

    public function test_the_telegram_message_cannot_be_forged_with_newlines_in_user_fields(): void
    {
        config(['services.telegram.bot_token' => 'TESTTOKEN', 'services.telegram.chat_id' => '123']);
        Http::fake();
        $row = SignupRequest::query()->create([
            'business_name' => "Shop\nPhone: 999",
            'contact_name' => "Ali\nPlan: free",
            'phone' => '01000000541',
            'business_type' => 'retail',
            'status' => SignupRequestStatus::Pending->value,
        ]);

        (new NotifyPlatformOfSignupRequestJob($row->id))->handle();

        Http::assertSent(fn ($request): bool => substr_count((string) $request['text'], "\n") === 4
            && str_contains((string) $request['text'], 'Shop Phone: 999'));
    }
}
