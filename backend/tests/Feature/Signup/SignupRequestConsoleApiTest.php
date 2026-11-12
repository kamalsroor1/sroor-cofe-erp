<?php

declare(strict_types=1);

namespace Tests\Feature\Signup;

use App\Contracts\TenantProvisionerInterface;
use App\DTOs\CreateTenantDTO;
use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Enums\SignupRequestStatus;
use App\Enums\TenantStatus;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * PRD-05: platform console review of signup requests (routes/central.php).
 */
final class SignupRequestConsoleApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const LIST = '/api/v1/super-admin/signup-requests';

    private CentralUser $operator;

    /** @var array<string, string> */
    private array $headers;

    /** @var list<string> tenant ids created by approve tests, dropped in tearDown */
    private array $provisioned = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->seed(PlansAndFeaturesSeeder::class);
        $this->operator = $this->centralSuperAdmin();
        $this->headers = $this->steppedUpCentralHeaders($this->operator);
    }

    protected function tearDown(): void
    {
        foreach ($this->provisioned as $id) {
            $this->dropProvisionedTenant($id);
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pending(array $overrides = []): SignupRequest
    {
        return SignupRequest::query()->create(array_merge([
            'business_name' => 'محمصة الاختبار',
            'contact_name' => 'أحمد علي',
            'phone' => '01000000'.str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT),
            'email' => 'applicant@example.test',
            'business_type' => 'weighted',
            'plan_code' => null,
            'preferred_subdomain' => null,
            'status' => SignupRequestStatus::Pending->value,
        ], $overrides));
    }

    private function slug(): string
    {
        return 'signup-'.Str::lower(Str::random(6));
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

    // ------------------------------------------------------------------ authN / authZ

    public function test_unauthenticated_requests_are_401(): void
    {
        $request = $this->pending();

        $this->getJson(self::LIST)->assertStatus(401);
        $this->postJson(self::LIST."/{$request->id}/approve", [])->assertStatus(401);
        $this->postJson(self::LIST."/{$request->id}/reject", [])->assertStatus(401);
    }

    public function test_an_operator_without_a_role_is_403(): void
    {
        $request = $this->pending();
        $headers = $this->centralHeaders($this->centralOperator(null));

        $this->getJson(self::LIST, $headers)->assertStatus(403);
        $this->postJson(self::LIST."/{$request->id}/approve", [], $headers)->assertStatus(403);
        $this->postJson(self::LIST."/{$request->id}/reject", ['reason' => 'no reason'], $headers)->assertStatus(403);
    }

    public function test_a_read_only_support_operator_can_list_but_not_decide(): void
    {
        $request = $this->pending();
        $support = $this->centralOperator(CentralPermission::ROLE_SUPPORT);
        $headers = $this->steppedUpCentralHeaders($support);

        $this->getJson(self::LIST, $headers)->assertOk();
        $this->postJson(self::LIST."/{$request->id}/approve", ['subdomain' => $this->slug(), 'owner_email' => 'a@b.test', 'owner_password' => 'secret1234'], $headers)->assertStatus(403);
        $this->postJson(self::LIST."/{$request->id}/reject", ['reason' => 'not now'], $headers)->assertStatus(403);

        $this->assertSame(SignupRequestStatus::Pending, $request->fresh()?->status);
    }

    public function test_a_tenant_token_is_not_a_console_identity(): void
    {
        $tenant = $this->createTenant();
        $bare = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];

        $this->getJson(self::LIST, $bare)->assertStatus(401);
    }

    // ------------------------------------------------------------------------- list

    public function test_list_is_paginated_newest_first_and_filterable(): void
    {
        $old = $this->pending(['business_name' => 'قديم', 'created_at' => now()->subDays(2)]);
        $new = $this->pending(['business_name' => 'جديد', 'created_at' => now()]);
        $this->pending(['business_name' => 'مرفوض', 'status' => SignupRequestStatus::Rejected->value]);

        $all = $this->getJson(self::LIST, $this->headers)->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
        $this->assertCount(3, $all->json('data'));

        $pending = $this->getJson(self::LIST.'?status=pending', $this->headers)->assertOk();
        $ids = array_column($pending->json('data'), 'id');
        $this->assertSame([$new->id, $old->id], $ids);

        $this->getJson(self::LIST.'?status=pending&per_page=1', $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2);

        $row = $pending->json('data.0');
        $this->assertArrayHasKey('business_name', $row);
        $this->assertArrayNotHasKey('user_agent', $row);
    }

    public function test_dashboard_reports_the_pending_count(): void
    {
        $this->pending();
        $this->pending();
        $this->pending(['status' => SignupRequestStatus::Rejected->value]);

        $this->getJson('/api/v1/super-admin/dashboard', $this->headers)
            ->assertOk()
            ->assertJsonPath('pending_signup_requests', 2);
    }

    // ------------------------------------------------------------------------ approve

    public function test_approve_provisions_a_tenant_and_a_second_approve_is_409(): void
    {
        $slug = $this->slug();
        $this->provisioned[] = $slug;
        $request = $this->pending(['business_name' => 'محمصة الموافقة', 'preferred_subdomain' => $slug, 'email' => $slug.'@applicant.test']);

        $this->postJson(self::LIST."/{$request->id}/approve", ['owner_password' => 'secret1234'], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.tenant_id', $slug)
            ->assertJsonPath('data.reviewed_by', $this->operator->getKey());

        // Same provisioning path as POST /tenants: tenant row, trial plan, subscription, default domain.
        $trial = Plan::query()->where('is_active', true)->where('trial_days', '>', 0)->orderBy('sort_order')->firstOrFail();
        $this->assertDatabaseHas('tenants', ['id' => $slug, 'name' => 'محمصة الموافقة', 'plan_id' => $trial->id, 'status' => TenantStatus::Trial->value]);
        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $slug, 'plan_id' => $trial->id]);

        $fresh = $request->fresh();
        $this->assertSame(SignupRequestStatus::Approved, $fresh?->status);
        $this->assertSame($slug, $fresh?->tenant_id);
        $this->assertSame((int) $this->operator->getKey(), $fresh?->reviewed_by);
        $this->assertNotNull($fresh?->reviewed_at);

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::SignupRequestApproved->value)->sole();
        $this->assertSame((int) $this->operator->getKey(), (int) $log->causer_id);
        $this->assertStringNotContainsString('secret1234', (string) json_encode($log->properties));

        // The same request again: already handled, nothing else is created.
        $tenants = Tenant::query()->count();
        $this->postJson(self::LIST."/{$request->id}/approve", ['subdomain' => $this->slug(), 'owner_email' => 'x@y.test', 'owner_password' => 'secret1234'], $this->headers)
            ->assertStatus(409);
        $this->assertSame($tenants, Tenant::query()->count());
        $this->assertSame(1, Subscription::query()->where('tenant_id', $slug)->count());
    }

    public function test_approve_uses_the_requested_plan_and_explicit_fields(): void
    {
        $slug = $this->slug();
        $this->provisioned[] = $slug;
        $request = $this->pending(['plan_code' => 'basic', 'preferred_subdomain' => null, 'email' => null]);
        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();

        $this->postJson(self::LIST."/{$request->id}/approve", [
            'subdomain' => $slug,
            'owner_email' => $slug.'@owner.test',
            'owner_password' => 'secret1234',
        ], $this->headers)->assertOk();

        $this->assertDatabaseHas('tenants', ['id' => $slug, 'plan_id' => $basic->id, 'email' => $slug.'@owner.test']);
    }

    public function test_approve_without_a_password_generates_one_and_the_new_owner_must_change_it(): void
    {
        $slug = $this->slug();
        $this->provisioned[] = $slug;
        $email = $slug.'@owner.test';
        $request = $this->pending(['preferred_subdomain' => $slug, 'email' => $email]);

        $response = $this->postJson(self::LIST."/{$request->id}/approve", [], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('owner_credentials.login', $email)
            ->assertJsonPath('owner_credentials.must_change_password', true);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $temporary = (string) $response->json('owner_credentials.temporary_password');
        $this->assertGreaterThanOrEqual(8, strlen($temporary));

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::SignupRequestApproved->value)->sole();
        $this->assertStringNotContainsString($temporary, (string) json_encode($log->toArray()));
        $tenant = Tenant::query()->findOrFail($slug);
        $this->assertStringNotContainsString($temporary, (string) DB::table('tenants')->where('id', $slug)->value('data'));

        // The provisioned owner is flagged in ITS tenant database.
        $this->assertTrue($this->inTenant($tenant, fn (): bool => User::query()->where('email', $email)->sole()->must_change_password));

        // Sign in: the flag is in the payload and every other endpoint answers 403 with the code.
        $tenantHeaders = ['Accept' => 'application/json', 'X-Tenant' => $slug];
        $login = $this->postJson('/api/v1/auth/login', ['login' => $email, 'password' => $temporary, 'device_name' => 'spa'], $tenantHeaders)
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
        $otherToken = (string) $this->postJson('/api/v1/auth/login', ['login' => $email, 'password' => $temporary, 'device_name' => 'tablet'], $tenantHeaders)
            ->assertOk()->json('data.token');
        $headers = $tenantHeaders + ['Authorization' => 'Bearer '.$login->json('data.token')];

        $this->getJson('/api/v1/items', $headers)->assertStatus(403)->assertJsonPath('error_code', 'auth.password_change_required');
        $this->postJson('/api/v1/customers', ['name' => 'عميل'], $headers)->assertStatus(403)->assertJsonPath('error_code', 'auth.password_change_required');
        $this->getJson('/api/v1/auth/me', $headers)->assertOk()->assertJsonPath('data.must_change_password', true);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => $temporary,
            'password' => 'fixtureOwnerOwn123',
            'password_confirmation' => 'fixtureOwnerOwn123',
        ], $headers)->assertOk()->assertJsonPath('data.must_change_password', false);

        $this->getJson('/api/v1/items', $headers)->assertOk();
        $this->getJson('/api/v1/auth/me', $tenantHeaders + ['Authorization' => 'Bearer '.$otherToken])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['login' => $email, 'password' => $temporary, 'device_name' => 'spa'], $tenantHeaders)->assertStatus(422);
    }

    public function test_approve_with_a_typed_password_returns_no_credentials_but_still_forces_a_change(): void
    {
        $slug = $this->slug();
        $this->provisioned[] = $slug;
        $email = $slug.'@owner.test';
        $request = $this->pending(['preferred_subdomain' => $slug, 'email' => $email]);

        $this->postJson(self::LIST."/{$request->id}/approve", ['owner_password' => 'secret1234'], $this->headers)
            ->assertOk()
            ->assertJsonPath('owner_credentials', null);

        $tenant = Tenant::query()->findOrFail($slug);
        $this->assertTrue($this->inTenant($tenant, fn (): bool => User::query()->where('email', $email)->sole()->must_change_password));
    }

    public function test_approve_validates_input_and_keeps_the_request_pending(): void
    {
        $request = $this->pending(['preferred_subdomain' => null, 'email' => null]);
        $url = self::LIST."/{$request->id}/approve";

        // SELL-01: owner_password is optional (generated on the server when omitted).
        $this->postJson($url, [], $this->headers)->assertStatus(422)
            ->assertJsonValidationErrors(['subdomain', 'owner_email'])
            ->assertJsonMissingValidationErrors(['owner_password']);
        $this->postJson($url, ['subdomain' => 'www', 'owner_email' => 'a@b.test', 'owner_password' => 'secret1234'], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['subdomain']);
        $this->postJson($url, ['subdomain' => $this->slug(), 'owner_email' => 'a@b.test', 'owner_password' => 'short1'], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['owner_password']);
        $this->postJson($url, ['subdomain' => $this->slug(), 'owner_email' => 'a@b.test', 'owner_password' => 'abc123456'], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['owner_password']);
        $this->postJson($url, ['subdomain' => $this->slug(), 'owner_email' => 'a@b.test', 'owner_password' => 'onlyletterspw'], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['owner_password']);
        $this->postJson($url, ['subdomain' => 'central', 'owner_email' => 'a@b.test', 'owner_password' => 'secret1234'], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['subdomain']);
        $this->postJson($url, ['subdomain' => $this->slug(), 'owner_email' => 'a@b.test', 'owner_password' => 'secret1234', 'plan_code' => 'nope'], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['plan_code']);

        $this->assertSame(SignupRequestStatus::Pending, $request->fresh()?->status);
        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_approve_provisioning_failure_is_422_and_rolls_back_everything(): void
    {
        $slug = $this->slug();
        $request = $this->pending(['preferred_subdomain' => $slug, 'email' => $slug.'@applicant.test']);

        // Writes central rows mid-operation (inside the action's transaction) and then fails,
        // so the assertions below prove the rollback rather than a failure before any write.
        $this->app->bind(TenantProvisionerInterface::class, fn (): TenantProvisionerInterface => new class implements TenantProvisionerInterface
        {
            public function provision(CreateTenantDTO $dto): Tenant
            {
                Tenant::withoutEvents(function () use ($dto): void {
                    Tenant::query()->create(['id' => $dto->slug, 'name' => $dto->name, 'slug' => $dto->slug, 'email' => $dto->email]);
                });
                DB::table('domains')->insert(['domain' => $dto->slug.'.example.test', 'tenant_id' => $dto->slug]);

                throw new RuntimeException('simulated provisioning failure');
            }
        });

        $this->postJson(self::LIST."/{$request->id}/approve", ['owner_password' => 'secret1234'], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subdomain'])
            ->assertJsonPath('errors.subdomain.0', __('signup.errors.provisioning_failed'));

        $fresh = $request->fresh();
        $this->assertSame(SignupRequestStatus::Pending, $fresh?->status);
        $this->assertNull($fresh?->tenant_id);
        $this->assertNull($fresh?->reviewed_by);
        $this->assertNull($fresh?->reviewed_at);
        $this->assertDatabaseMissing('tenants', ['id' => $slug]);
        $this->assertSame(0, Tenant::query()->count());
        $this->assertDatabaseCount('domains', 0);
        $this->assertSame(0, Subscription::query()->count());
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::SignupRequestApproved->value)->count());
    }

    public function test_approve_needs_a_recent_second_factor(): void
    {
        $request = $this->pending(['preferred_subdomain' => $this->slug(), 'email' => 'a@b.test']);

        $this->postJson(self::LIST."/{$request->id}/approve", ['owner_password' => 'secret1234'], $this->centralHeaders($this->operator))
            ->assertStatus(403);

        $this->assertSame(SignupRequestStatus::Pending, $request->fresh()?->status);
        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_approve_of_an_unknown_request_is_404(): void
    {
        $this->postJson(self::LIST.'/999999/approve', ['subdomain' => $this->slug(), 'owner_email' => 'a@b.test', 'owner_password' => 'secret1234'], $this->headers)
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------------- reject

    public function test_reject_requires_a_reason(): void
    {
        $request = $this->pending();
        $url = self::LIST."/{$request->id}/reject";

        $this->postJson($url, [], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->postJson($url, ['reason' => 'ab'], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->postJson($url, ['reason' => str_repeat('x', 501)], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->assertSame(SignupRequestStatus::Pending, $request->fresh()?->status);
    }

    public function test_reject_marks_the_request_audits_it_and_cannot_repeat_or_be_approved(): void
    {
        $request = $this->pending(['preferred_subdomain' => $this->slug(), 'email' => 'a@b.test']);

        $this->postJson(self::LIST."/{$request->id}/reject", ['reason' => 'خارج نطاق الخدمة'], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'خارج نطاق الخدمة');

        $fresh = $request->fresh();
        $this->assertSame(SignupRequestStatus::Rejected, $fresh?->status);
        $this->assertSame((int) $this->operator->getKey(), $fresh?->reviewed_by);
        $this->assertNotNull($fresh?->reviewed_at);
        $this->assertNull($fresh?->tenant_id);
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::SignupRequestRejected->value)->count());

        $this->postJson(self::LIST."/{$request->id}/reject", ['reason' => 'مرة ثانية'], $this->headers)->assertStatus(409);
        $this->postJson(self::LIST."/{$request->id}/approve", ['owner_password' => 'secret1234'], $this->headers)->assertStatus(409);
        $this->assertSame(0, Tenant::query()->count());
    }
}
