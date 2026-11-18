<?php

declare(strict_types=1);

namespace Tests\Feature\Signup;

use App\Contracts\TenantProvisionerInterface;
use App\DTOs\CreateTenantDTO;
use App\Enums\CentralPermission;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Enums\TenantSignupSource;
use App\Enums\TenantStatus;
use App\Mail\SignupAccountReadyMail;
use App\Mail\SignupProvisioningDelayedMail;
use App\Mail\SignupVerificationCodeMail;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Branding\PlatformBranding;
use App\Services\Signup\SelfSignupOutcomes;
use App\Services\TenantProvisionerService;
use App\Support\Signup\SignupSecrets;
use App\Support\Tenancy\TenantProvisioningFinished;
use Carbon\CarbonImmutable;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * SIGN-12: a verified self-signup is provisioned automatically through ProvisionTenantAction
 * (sync queue in tests: the whole chain runs), the owner signs in with the password chosen at
 * signup and gets "your shop is ready" once; the daily cap, the failure path, the status
 * endpoint and the console actions.
 */
final class SelfSignupProvisioningTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const URL = '/api/v1/signup';

    private const CONSOLE = '/api/v1/super-admin/signup-requests';

    private const PASSWORD = 'OwnerChosen2026';

    /** @var list<string> tenant ids created by the tests, dropped in tearDown */
    private array $provisioned = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->endTenancy();
        $this->seedCentralPlatformRoles();
        $this->seed(PlansAndFeaturesSeeder::class);
        Mail::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config(['services.telegram.bot_token' => 'test-bot-token', 'services.telegram.chat_id' => '-100123']);
    }

    protected function tearDown(): void
    {
        foreach ($this->provisioned as $id) {
            $this->dropProvisionedTenant($id);
        }

        parent::tearDown();
    }

    private function slug(): string
    {
        $slug = 'self-'.Str::lower(Str::random(6));
        $this->provisioned[] = $slug;

        return $slug;
    }

    private function dropProvisionedTenant(string $id): void
    {
        $this->endTenancy();
        $tenant = Tenant::query()->find($id);
        if (! $tenant instanceof Tenant) {
            return;
        }

        $manager = $tenant->database()->manager();
        $database = (string) $tenant->database()->getName();
        if ($manager->databaseExists($database)) {
            gc_collect_cycles();
            $manager->deleteDatabase($tenant);
        }

        $storage = storage_path().'/'.config('tenancy.filesystem.suffix_base', 'tenant').$id;
        if (is_dir($storage)) {
            File::deleteDirectory($storage);
        }
    }

    /**
     * Start a self-signup and return its token and row.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{0: string, 1: SignupRequest}
     */
    private function start(string $slug, array $overrides = []): array
    {
        $email = $slug.'@owner.test';
        $token = (string) $this->postJson(self::URL, array_merge([
            'business_name' => 'محمصة '.$slug,
            'owner_name' => 'صاحب المحل',
            'email' => $email,
            'phone' => '0100'.random_int(1000000, 9999999),
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'business_type' => 'retail',
            'subdomain' => $slug,
            'terms_accepted' => true,
            'locale' => 'ar',
        ], $overrides))->assertCreated()->json('data.signup_token');

        return [$token, SignupRequest::query()->where('signup_token_hash', SignupSecrets::hashToken($token))->sole()];
    }

    private function codeFor(string $email): string
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

    private function verify(string $slug, string $token): void
    {
        $this->postJson(self::URL.'/verify', ['token' => $token, 'code' => $this->codeFor($slug.'@owner.test')])->assertOk();
    }

    private function telegramMessages(string $needle): int
    {
        return Http::recorded(fn (HttpRequest $request): bool => str_contains($request->url(), 'sendMessage')
            && str_contains((string) ($request->data()['text'] ?? ''), $needle))->count();
    }

    private function setCap(int $cap): void
    {
        app(PlatformBranding::class)->update(['signup_daily_cap' => (string) $cap]);
        PlatformBranding::forget();
    }

    // ------------------------------------------------------------------ happy path

    public function test_verification_provisions_the_shop_with_the_password_the_owner_chose(): void
    {
        $slug = $this->slug();
        [$token, $signup] = $this->start($slug);
        $this->verify($slug, $token);

        $signup->refresh();
        $this->assertSame(SignupRequestStatus::Provisioned, $signup->status);
        $this->assertSame($slug, $signup->tenant_id);
        $this->assertNotNull($signup->provisioning_started_at);
        $this->assertNotNull($signup->provisioned_at);
        $this->assertNull($signup->password_hash, 'The hash leaves the signup row once the tenant has it.');

        // Same provisioning path as the console: tenant, default domain, subscription, self_signup.
        $free = Plan::query()->where('is_active', true)->where('is_free', true)->orderBy('sort_order')->firstOrFail();
        $tenant = Tenant::query()->findOrFail($slug);
        $this->assertSame(TenantSignupSource::SelfSignup, $tenant->signup_source);
        $this->assertSame($free->id, $tenant->plan_id);
        $this->assertSame(TenantStatus::Active->value, (string) $tenant->status);
        $this->assertNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->provisioningStatus()->isReady());

        // The owner signs in with the chosen password and is NOT forced to change it.
        $owner = $this->inTenant($tenant, fn (): User => User::query()->where('email', $slug.'@owner.test')->sole());
        $this->assertFalse($owner->must_change_password);
        $this->assertTrue(Hash::check(self::PASSWORD, $owner->password));
        $this->postJson('/api/v1/auth/login', ['login' => $slug.'@owner.test', 'password' => self::PASSWORD, 'device_name' => 'spa'], ['X-Tenant' => $slug])
            ->assertOk()
            ->assertJsonPath('data.must_change_password', false);

        // "Your shop is ready" exactly once, never with a password.
        Mail::assertQueued(SignupAccountReadyMail::class, 1);
        Mail::assertQueued(SignupAccountReadyMail::class, function (SignupAccountReadyMail $mail) use ($slug): bool {
            $html = $mail->render();

            return $mail->hasTo($slug.'@owner.test')
                && $mail->loginEmail === $slug.'@owner.test'
                && str_contains($mail->loginUrl, $slug.'.')
                && str_ends_with($mail->loginUrl, '/login')
                && str_ends_with($mail->helpUrl, '/help')
                && $mail->trialEndsAt === null
                && str_contains($html, $mail->loginUrl)
                && ! str_contains($html, self::PASSWORD)
                && ! str_contains(serialize($mail), self::PASSWORD);
        });
        Mail::assertNotQueued(SignupProvisioningDelayedMail::class);

        // Platform Telegram: one message for the new self-signup.
        $this->assertSame(1, $this->telegramMessages($slug));

        // Status: ready, with the sign-in URL.
        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.status', 'provisioned')
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.subdomain', $slug)
            ->assertJsonPath('data.login_url', SelfSignupOutcomes::loginUrl($slug));

        // A replayed success event sends nothing more.
        event(new TenantProvisioningFinished($slug, succeeded: true));
        Mail::assertQueued(SignupAccountReadyMail::class, 1);
    }

    public function test_a_paid_plan_starts_a_trial_and_the_mail_names_its_end(): void
    {
        $slug = $this->slug();
        [$token] = $this->start($slug, ['plan_code' => 'basic']);
        $this->verify($slug, $token);

        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $tenant = Tenant::query()->findOrFail($slug);
        $this->assertSame($basic->id, $tenant->plan_id);
        $this->assertSame(TenantStatus::Trial->value, (string) $tenant->status);
        $this->assertNotNull($tenant->trial_ends_at);

        Mail::assertQueued(SignupAccountReadyMail::class, fn (SignupAccountReadyMail $mail): bool => $mail->trialEndsAt instanceof CarbonImmutable
            && $mail->planName === $basic->name
            && str_contains($mail->render(), (string) __('mail.signup_ready.trial_ends', ['date' => $mail->trialEndsAt->locale('ar')->translatedFormat('j F Y')], 'ar')));
    }

    public function test_a_code_taken_in_the_meantime_gets_a_free_variant(): void
    {
        $slug = $this->slug();
        [$token, $signup] = $this->start($slug);

        // Someone else got the code between signup and verification.
        $this->createTenant(['id' => $slug, 'slug' => $slug]);
        $this->endTenancy();
        $this->provisioned[] = $slug.'-2';

        $this->verify($slug, $token);

        $signup->refresh();
        $this->assertSame(SignupRequestStatus::Provisioned, $signup->status);
        $this->assertSame($slug.'-2', $signup->tenant_id);
        $this->assertSame($slug.'-2', $signup->preferred_subdomain);
    }

    public function test_the_provisioner_refuses_a_plain_value_passed_as_a_hash(): void
    {
        $free = Plan::query()->where('is_free', true)->firstOrFail();

        try {
            app(TenantProvisionerService::class)->provision(CreateTenantDTO::forSelfSignup(
                name: 'x', slug: 'plain-hash', email: 'plain@owner.test', phone: null, planId: (int) $free->id,
                passwordHash: self::PASSWORD, trialDays: 0,
            ));
            $this->fail('A plain password flagged as a hash must be refused.');
        } catch (InvalidArgumentException) {
            $this->assertFalse(Tenant::query()->whereKey('plain-hash')->exists());
        }
    }

    // ------------------------------------------------------------------ daily cap

    public function test_the_daily_cap_sends_verified_signups_to_the_manual_queue_and_alerts_once(): void
    {
        $this->setCap(1);

        $first = $this->slug();
        [$token] = $this->start($first);
        $this->verify($first, $token);
        $this->assertSame(SignupRequestStatus::Provisioned, SignupRequest::query()->where('tenant_id', $first)->sole()->status);

        $held = [];
        foreach ([1, 2] as $i) {
            $slug = 'held-'.$i.'-'.Str::lower(Str::random(4));
            [$heldToken, $signup] = $this->start($slug);
            $this->verify($slug, $heldToken);
            $signup->refresh();
            $this->assertSame(SignupRequestStatus::Pending, $signup->status);
            $this->assertNull($signup->tenant_id);
            $this->assertFalse(Tenant::query()->whereKey($slug)->exists());
            $held[] = [$slug, $heldToken, $signup];
        }

        $this->assertSame(1, $this->telegramMessages((string) __('self_signup.notify.cap_reached', ['cap' => 1], 'ar')) + $this->telegramMessages((string) __('self_signup.notify.cap_reached', ['cap' => 1], 'en')));
        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $held[0][1]])->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.ready', false)->assertJsonPath('data.login_url', null);

        // The console releases a held signup: same provisioning, the owner's own password.
        [$slug, $heldToken, $signup] = $held[0];
        $this->provisioned[] = $slug;
        $operator = $this->centralSuperAdmin();
        $this->postJson(self::CONSOLE."/{$signup->id}/provision", [], $this->steppedUpCentralHeaders($operator))
            ->assertOk()
            ->assertJsonPath('data.signup_flow', 'self_signup')
            ->assertJsonPath('data.tenant_id', $slug);

        $signup->refresh();
        $this->assertSame(SignupRequestStatus::Provisioned, $signup->status);
        $this->assertSame((int) $operator->getKey(), $signup->reviewed_by);
        $this->postJson('/api/v1/auth/login', ['login' => $slug.'@owner.test', 'password' => self::PASSWORD, 'device_name' => 'spa'], ['X-Tenant' => $slug])
            ->assertOk()->assertJsonPath('data.must_change_password', false);
        Mail::assertQueued(SignupAccountReadyMail::class, fn (SignupAccountReadyMail $mail): bool => $mail->hasTo($slug.'@owner.test'));

        // Only a held self-signup can be released.
        $this->postJson(self::CONSOLE."/{$signup->id}/provision", [], $this->steppedUpCentralHeaders($operator))->assertStatus(409);

        // The second held one still goes through the old manual approval.
        [, , $other] = $held[1];
        $this->assertSame(SignupRequestStatus::Pending, $other->fresh()?->status);
    }

    public function test_the_cap_counts_the_cairo_day(): void
    {
        $this->setCap(1);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 21:30:00', 'Africa/Cairo'));

        $first = $this->slug();
        [$token] = $this->start($first);
        $this->verify($first, $token);

        // 23:59 Cairo: same day, held.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 23:59:00', 'Africa/Cairo'));
        $held = 'held-'.Str::lower(Str::random(5));
        [$heldToken, $heldSignup] = $this->start($held);
        $this->verify($held, $heldToken);
        $this->assertSame(SignupRequestStatus::Pending, $heldSignup->fresh()?->status);

        // 00:01 Cairo next day: a new budget.
        $this->travelTo(CarbonImmutable::parse('2026-11-19 00:01:00', 'Africa/Cairo'));
        $next = $this->slug();
        [$nextToken, $nextSignup] = $this->start($next);
        $this->verify($next, $nextToken);
        $this->assertSame(SignupRequestStatus::Provisioned, $nextSignup->fresh()?->status);
    }

    // ------------------------------------------------------------------ failure

    public function test_a_refused_registration_marks_the_signup_failed_and_tells_everyone_once(): void
    {
        $this->app->bind(TenantProvisionerInterface::class, fn (): TenantProvisionerInterface => new class implements TenantProvisionerInterface
        {
            public function provision(CreateTenantDTO $dto): Tenant
            {
                throw new RuntimeException('database server unreachable');
            }
        });

        $slug = $this->slug();
        [$token, $signup] = $this->start($slug);
        $this->verify($slug, $token);

        $signup->refresh();
        $this->assertSame(SignupRequestStatus::Failed, $signup->status);
        $this->assertSame(SelfSignupOutcomes::FAILURE_REGISTRATION, $signup->failure_code);
        $this->assertNotNull($signup->failed_at);
        $this->assertNull($signup->tenant_id);
        $this->assertSame(0, Tenant::query()->count());

        Mail::assertQueued(SignupProvisioningDelayedMail::class, 1);
        Mail::assertQueued(SignupProvisioningDelayedMail::class, fn (SignupProvisioningDelayedMail $mail): bool => $mail->hasTo($slug.'@owner.test')
            && $mail->locale === 'ar'
            && ! str_contains($mail->render(), 'database server unreachable'));
        Mail::assertNotQueued(SignupAccountReadyMail::class);
        // Telegram: the new self-signup, then one failure alert naming the reason code.
        $this->assertSame(1, $this->telegramMessages(SelfSignupOutcomes::FAILURE_REGISTRATION));
        $this->assertSame(2, $this->telegramMessages($slug));

        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])
            ->assertOk()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.ready', false);
    }

    public function test_a_failed_provisioning_job_marks_the_signup_failed(): void
    {
        $signup = SignupRequest::query()->create([
            'business_name' => 'محل', 'contact_name' => 'سارة', 'phone' => '01000000703', 'email' => 'job-fail@owner.test',
            'business_type' => 'retail', 'status' => SignupRequestStatus::Provisioning->value,
            'signup_flow' => SignupFlow::SelfSignup->value, 'locale' => 'en', 'tenant_id' => 'job-fail-shop',
        ]);
        $signup->forceFill(['email_verified_at' => now(), 'provisioning_started_at' => now()])->save();

        event(new TenantProvisioningFinished('job-fail-shop', succeeded: false));
        event(new TenantProvisioningFinished('job-fail-shop', succeeded: false));

        $signup->refresh();
        $this->assertSame(SignupRequestStatus::Failed, $signup->status);
        $this->assertSame(SelfSignupOutcomes::FAILURE_PROVISIONING, $signup->failure_code);
        Mail::assertQueued(SignupProvisioningDelayedMail::class, 1);

        // A tenant no self-signup owns is ignored.
        event(new TenantProvisioningFinished('console-made-shop', succeeded: false));
        Mail::assertQueued(SignupProvisioningDelayedMail::class, 1);
    }

    // ------------------------------------------------------------------ status endpoint

    public function test_status_needs_a_valid_token_and_works_while_signup_is_off(): void
    {
        $slug = $this->slug();
        [$token] = $this->start($slug);

        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_verification')
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.email_masked', mb_substr($slug, 0, 1).'***@owner.test')
            ->assertJsonMissingPath('data.tenant_id');

        $this->getJson(self::URL.'/status')->assertStatus(422)->assertJsonValidationErrors(['token']);
        // Security review: the header only; a token in the query string (access logs) is ignored.
        $this->getJson(self::URL.'/status?token='.$token)->assertStatus(422)->assertJsonValidationErrors(['token']);
        $this->getJson(self::URL.'/status', ['X-Signup-Token' => str_repeat('x', 64)])
            ->assertStatus(422)->assertJsonPath('errors.token.0', __('self_signup.errors.token_invalid'));

        app(PlatformBranding::class)->update(['self_signup_enabled' => false]);
        PlatformBranding::forget();
        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])->assertOk();
    }

    public function test_status_is_throttled(): void
    {
        config(['rate_limits.self_signup.status_per_minute' => 2]);
        $token = str_repeat('y', 64);

        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])->assertStatus(422);
        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])->assertStatus(422);
        $this->getJson(self::URL.'/status', ['X-Signup-Token' => $token])->assertStatus(429)->assertHeader('Retry-After');
    }

    // ------------------------------------------------------------------ console

    public function test_the_console_list_shows_the_self_signup_log_and_filters_by_flow(): void
    {
        $slug = $this->slug();
        [$token] = $this->start($slug);
        $this->verify($slug, $token);
        SignupRequest::query()->create([
            'business_name' => 'طلب قديم', 'contact_name' => 'سارة', 'phone' => '01000000704',
            'business_type' => 'retail', 'status' => SignupRequestStatus::Pending->value,
        ]);

        $headers = $this->steppedUpCentralHeaders($this->centralOperator(CentralPermission::ROLE_SUPPORT));

        $row = $this->getJson(self::CONSOLE.'?flow=self_signup', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.signup_flow', 'self_signup')
            ->assertJsonPath('data.0.status', 'provisioned')
            ->json('data.0');
        $this->assertNotNull($row['email_verified_at']);
        $this->assertNotNull($row['provisioning_started_at']);
        $this->assertNotNull($row['provisioned_at']);
        foreach (['password_hash', 'signup_token_hash', 'verification_code_hash'] as $secret) {
            $this->assertArrayNotHasKey($secret, $row);
        }

        $this->getJson(self::CONSOLE.'?flow=assisted', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.signup_flow', 'assisted');
        $this->getJson(self::CONSOLE.'?status=provisioned', $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_console_suspends_the_shop_a_signup_created(): void
    {
        $slug = $this->slug();
        [$token, $signup] = $this->start($slug);
        $this->verify($slug, $token);

        $support = $this->steppedUpCentralHeaders($this->centralOperator(CentralPermission::ROLE_SUPPORT));
        $this->postJson(self::CONSOLE."/{$signup->id}/suspend-tenant", ['reason' => 'violation'], $support)->assertStatus(403);
        $this->postJson(self::CONSOLE."/{$signup->id}/suspend-tenant", ['reason' => 'violation'])->assertStatus(401);

        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $this->postJson(self::CONSOLE."/{$signup->id}/suspend-tenant", [], $headers)->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->postJson(self::CONSOLE."/{$signup->id}/suspend-tenant", ['reason' => 'violation', 'note' => 'spam shop'], $headers)
            ->assertOk()
            ->assertJsonPath('tenant.id', $slug)
            ->assertJsonPath('tenant.status', TenantStatus::Suspended->value);
        $this->assertSame(TenantStatus::Suspended->value, (string) Tenant::query()->findOrFail($slug)->status);

        // No shop behind the request: 409; unknown request: 404.
        $assisted = SignupRequest::query()->create([
            'business_name' => 'طلب', 'contact_name' => 'سارة', 'phone' => '01000000705',
            'business_type' => 'retail', 'status' => SignupRequestStatus::Pending->value,
        ]);
        $this->postJson(self::CONSOLE."/{$assisted->id}/suspend-tenant", ['reason' => 'violation'], $headers)->assertStatus(409);
        $this->postJson(self::CONSOLE.'/999999/suspend-tenant', ['reason' => 'violation'], $headers)->assertStatus(404);
        $this->postJson(self::CONSOLE."/{$assisted->id}/provision", [], $headers)->assertStatus(409);
    }

    public function test_an_assisted_approval_still_works_and_sends_no_self_signup_mail(): void
    {
        $slug = $this->slug();
        $request = SignupRequest::query()->create([
            'business_name' => 'محل يدوي', 'contact_name' => 'سارة', 'phone' => '01000000706', 'email' => $slug.'@owner.test',
            'business_type' => 'retail', 'status' => SignupRequestStatus::Pending->value, 'preferred_subdomain' => $slug,
        ]);
        $operator = $this->centralSuperAdmin();

        $this->postJson(self::CONSOLE."/{$request->id}/approve", ['owner_password' => 'secret1234'], $this->steppedUpCentralHeaders($operator))
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertInstanceOf(CentralUser::class, $operator);
        Mail::assertNotQueued(SignupAccountReadyMail::class);
        $this->assertSame(SignupRequestStatus::Approved, $request->fresh()?->status);
    }
}
