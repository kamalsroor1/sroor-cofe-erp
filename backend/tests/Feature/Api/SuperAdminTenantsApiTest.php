<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Models\User;
use App\Support\Tenancy\TenantSuspensionReason;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * IDEN-1.8 (split from SuperAdminApiTest): dashboard + tenants endpoints of the control plane,
 * authenticated as an App\Models\CentralUser (routes/central.php: EnsureCentralContext →
 * AuthenticateCentral → can:<CentralPermission>).
 *
 * Intentional contract change (not a weakening): a TENANT token on the control plane used to be
 * 403 (authenticated by ApiTokenAuth, refused by the gate). It is now 401: AuthenticateCentral
 * only reads central_personal_access_tokens, so a tenant token is not an identity there at all.
 * The authenticated-but-forbidden 403 path is still covered by a central operator without the
 * needed CentralPermission (no role / `support` on writes).
 */
final class SuperAdminTenantsApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();

        $this->plan = Plan::query()->create([
            'name' => 'باقة المحامص الاحترافية',
            'slug' => 'pro-roastery-'.Str::lower(Str::random(6)),
            'price_monthly' => '500.000',
            'price_yearly' => '5000.000',
            'max_users' => 10,
            'max_stores' => 3,
            'max_items' => 500,
            'max_invoices_per_month' => 5000,
            'is_active' => true,
            'is_popular' => true,
            'sort_order' => 1,
            'features' => ['coffee_blender' => true, 'smart_reorder' => true],
        ]);
    }

    // ------------------------------------------------------------------ authN / authZ

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/super-admin/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/super-admin/tenants')->assertStatus(401);
        $this->postJson('/api/v1/super-admin/tenants', [])->assertStatus(401);
    }

    public function test_tenant_user_token_is_401_on_the_control_plane(): void
    {
        // Was 403 before IDEN-1.4 (see class docblock): a tenant token is no central identity.
        $tenant = $this->createTenant();
        $token = $this->tenantToken($tenant);
        $bare = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/super-admin/dashboard', $bare)->assertStatus(401);
        $this->getJson('/api/v1/super-admin/tenants', $bare)->assertStatus(401);
        $this->postJson("/api/v1/super-admin/tenants/{$tenant->getTenantKey()}/toggle-status", [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'other',
        ], $bare)->assertStatus(401);

        $this->assertSame(TenantStatus::Active->value, Tenant::query()->findOrFail($tenant->getTenantKey())->status);
    }

    public function test_legacy_users_table_super_admin_token_is_401(): void
    {
        $legacy = $this->legacyUsersTableSuperAdmin();
        $bare = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$legacy->createToken('legacy')->plainTextToken];

        $this->getJson('/api/v1/super-admin/dashboard', $bare)->assertStatus(401);
        $this->getJson('/api/v1/super-admin/tenants', $bare)->assertStatus(401);
    }

    public function test_operator_without_any_central_role_is_403(): void
    {
        $headers = $this->centralHeaders($this->centralOperator(null));

        $this->getJson('/api/v1/super-admin/dashboard', $headers)->assertStatus(403);
        $this->getJson('/api/v1/super-admin/tenants', $headers)->assertStatus(403);
    }

    public function test_inactive_super_admin_is_401(): void
    {
        $operator = $this->centralSuperAdmin();
        $headers = $this->centralHeaders($operator);
        $operator->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/super-admin/dashboard', $headers)->assertStatus(401);
    }

    // ---------------------------------------------------------------------- dashboard

    public function test_can_get_super_admin_dashboard_metrics(): void
    {
        $freePlan = Plan::query()->create([
            'name' => 'مجاني',
            'slug' => 'free-'.Str::lower(Str::random(6)),
            'price_monthly' => '0.000',
            'price_yearly' => '0.000',
            'is_free' => true,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
            'features' => [],
        ]);

        $this->createTenant(['plan_id' => $this->plan->id, 'status' => TenantStatus::Active->value]);
        $this->createTenant(['plan_id' => $freePlan->id, 'status' => TenantStatus::Active->value]);
        $this->createTenant(['plan_id' => $this->plan->id, 'status' => TenantStatus::Suspended->value]);

        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $response = $this->getJson('/api/v1/super-admin/dashboard', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['metrics', 'plan_stats', 'recent_tenants', 'system_info'])
            ->assertJsonMissingPath('metrics.trial_tenants');

        $metrics = $response->json('metrics');

        $allTotal = (int) $this->getJson('/api/v1/super-admin/tenants', $headers)->assertOk()->json('tenants.total');
        $activeTotal = (int) $this->getJson('/api/v1/super-admin/tenants?status=active', $headers)->assertOk()->json('tenants.total');
        $suspendedTotal = (int) $this->getJson('/api/v1/super-admin/tenants?status=suspended', $headers)->assertOk()->json('tenants.total');
        $freeTotal = (int) $this->getJson('/api/v1/super-admin/tenants?plan_id='.$freePlan->getKey(), $headers)->assertOk()->json('tenants.total');

        $this->assertSame($allTotal, $metrics['total_tenants']);
        $this->assertSame($activeTotal, $metrics['active_tenants']);
        $this->assertSame($suspendedTotal, $metrics['suspended_tenants']);
        $this->assertSame($freeTotal, $metrics['free_plan_tenants']);
    }

    // ------------------------------------------------------------------------ tenants

    public function test_can_get_tenants_and_provision_new_tenant(): void
    {
        $super = $this->centralSuperAdmin();
        $headers = $this->centralHeaders($super);

        $this->getJson('/api/v1/super-admin/tenants', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['plans', 'tenants']);

        // Real per-tenant database through the production TenantCreated pipeline; the id is not
        // a harness id, so this test removes the database and storage directory itself.
        $slug = 'wadi-elbon-'.Str::lower(Str::random(6));

        try {
            $this->postJson('/api/v1/super-admin/tenants', [
                'name' => 'محمصة وادي البن',
                'slug' => $slug,
                'email' => $slug.'@elbon.test',
                'phone' => '01000007002',
                'password' => 'secret1234',
                'plan_id' => $this->plan->id,
                'trial_days' => 14,
            ], $this->steppedUpCentralHeaders($super)) // SEC-1 L11: creation needs a recent 2FA proof
                ->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('tenants', [
                'id' => $slug,
                'slug' => $slug,
                'email' => $slug.'@elbon.test',
                'status' => TenantStatus::Trial->value,
            ]);
            $this->assertDatabaseHas('subscriptions', ['tenant_id' => $slug, 'plan_id' => $this->plan->id]);

            // The provisioner seeded the tenant side (first admin) and left tenant context.
            $this->assertFalse(tenancy()->initialized);
            $tenant = Tenant::query()->findOrFail($slug);
            $admin = $this->inTenant($tenant, static fn (): ?array => ($user = User::query()->where('email', $slug.'@elbon.test')->first()) instanceof User
                ? ['is_admin' => $user->hasRole('admin')]
                : null);
            $this->assertSame(['is_admin' => true], $admin);

            // Nothing about the operator leaks into the tenant `users` table.
            $this->assertSame(0, $this->inTenant($tenant, static fn (): int => User::query()->where('email', 'like', '%@central.harness.test')->count()));
        } finally {
            $this->dropProvisionedTenant($slug);
        }
    }

    public function test_store_tenant_without_a_recent_second_factor_is_step_up_required(): void
    {
        // SEC-1 (L11): creating a workspace needs a recent 2FA proof; nothing is created without it.
        $operator = $this->centralSuperAdmin();
        $before = Tenant::query()->count();
        $payload = [
            'name' => 'محل بدون تحقق',
            'slug' => 'no-step-up-'.Str::lower(Str::random(6)),
            'email' => 'no-step-up@elbon.test',
            'password' => 'secret1234',
            'plan_id' => $this->plan->id,
        ];

        $this->postJson('/api/v1/super-admin/tenants', $payload, $this->centralHeaders($operator))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $stale = now()->subMinutes((int) config('central.step_up_ttl_minutes', 15) + 1);
        $this->postJson('/api/v1/super-admin/tenants', $payload, $this->steppedUpCentralHeaders($operator, $stale))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertSame($before, Tenant::query()->count());
    }

    public function test_store_tenant_fails_validation_on_missing_fields(): void
    {
        $this->postJson('/api/v1/super-admin/tenants', ['name' => ''], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug', 'email', 'plan_id', 'password']);
    }

    public function test_store_tenant_rejects_a_seven_char_owner_password(): void
    {
        $super = $this->centralSuperAdmin();

        $this->postJson('/api/v1/super-admin/tenants', [
            'name' => 'محمصة كلمة المرور',
            'slug' => 'pw-policy-'.Str::lower(Str::random(6)),
            'email' => 'pw-policy@elbon.test',
            'phone' => '01000007003',
            'password' => '1234567',
            'plan_id' => $this->plan->id,
            'trial_days' => 14,
        ], $this->steppedUpCentralHeaders($super))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_store_tenant_rejects_invalid_values_and_existing_slug(): void
    {
        $existing = $this->createTenant();

        $this->postJson('/api/v1/super-admin/tenants', [
            'name' => str_repeat('م', 256),
            'slug' => (string) $existing->slug,
            'email' => 'not-an-email',
            'plan_id' => 999999,
            'password' => '123',
            'trial_days' => 91,
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug', 'email', 'plan_id', 'password', 'trial_days']);

        $this->assertSame(1, Tenant::query()->count());
    }

    public function test_store_tenant_rejects_arabic_and_non_subdomain_slugs_with_422(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $invalidSlugs = [
            'arabic' => 'عربي',
            'mixed arabic' => 'shop-عربي',
            'space' => 'my shop',
            'uppercase short' => 'ABC',
            'uppercase' => 'MyShop',
            'too short' => 'ab',
            'too long' => str_repeat('a', 31),
            'underscore' => 'my_shop',
            'leading hyphen' => '-shop',
            'trailing hyphen' => 'shop-',
            'accented unicode' => 'café',
        ];

        foreach ($invalidSlugs as $case => $slug) {
            $response = $this->postJson('/api/v1/super-admin/tenants', [
                'name' => 'متجر اختبار',
                'slug' => $slug,
                'email' => 'slug-test@elbon.test',
                'phone' => '01000007004',
                'password' => 'secret1234',
                'plan_id' => $this->plan->id,
            ], $headers);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['slug'])
                ->assertJsonPath('errors.slug.0', (string) __('signup.validation.subdomain_format'));
        }

        // Reserved slugs from public signup are also rejected.
        foreach (['admin', 'api'] as $reservedSlug) {
            $this->postJson('/api/v1/super-admin/tenants', [
                'name' => 'متجر محجوز',
                'slug' => $reservedSlug,
                'email' => 'reserved-test@elbon.test',
                'phone' => '01000007006',
                'password' => 'secret1234',
                'plan_id' => $this->plan->id,
            ], $headers)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['slug'])
                ->assertJsonPath('errors.slug.0', (string) __('signup.validation.subdomain_reserved'));
        }

        // English locale returns the translated validation message as well.
        $this->postJson('/api/v1/super-admin/tenants', [
            'name' => 'Test Shop',
            'slug' => 'ABC',
            'email' => 'slug-en@elbon.test',
            'phone' => '01000007005',
            'password' => 'secret1234',
            'plan_id' => $this->plan->id,
        ], $headers + ['X-Locale' => 'en'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug'])
            ->assertJsonPath('errors.slug.0', (string) __('signup.validation.subdomain_format', [], 'en'));

        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_store_tenant_provisions_free_plan_without_trial_days_as_active_and_paid_defaults_to_zero_trial(): void
    {
        $this->seed(PlansAndFeaturesSeeder::class);
        $free = Plan::query()->where('slug', 'free')->sole();
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $freeSlug = 'free-console-'.Str::lower(Str::random(6));
        $paidSlug = 'paid-console-'.Str::lower(Str::random(6));

        try {
            $this->postJson('/api/v1/super-admin/tenants', [
                'name' => 'متجر مجاني',
                'slug' => $freeSlug,
                'email' => $freeSlug.'@elbon.test',
                'phone' => '01000007010',
                'password' => 'secret1234',
                'plan_id' => $free->id,
            ], $headers)
                ->assertStatus(201)
                ->assertJsonPath('tenant.status', TenantStatus::Active->value)
                ->assertJsonPath('tenant.trial_ends_at', null)
                ->assertJsonPath('tenant.plan_name', 'المجانية')
                ->assertJsonPath('tenant.plan.name', 'المجانية');

            // Paid plan without trial_days defaults to 0 (never silent 14-day trial).
            $this->postJson('/api/v1/super-admin/tenants', [
                'name' => 'متجر مدفوع',
                'slug' => $paidSlug,
                'email' => $paidSlug.'@elbon.test',
                'phone' => '01000007011',
                'password' => 'secret1234',
                'plan_id' => $this->plan->id,
            ], $headers)
                ->assertStatus(201)
                ->assertJsonPath('tenant.status', TenantStatus::ReadOnly->value)
                ->assertJsonPath('tenant.trial_ends_at', null);
        } finally {
            $this->dropProvisionedTenant($freeSlug);
            $this->dropProvisionedTenant($paidSlug);
        }
    }

    public function test_can_show_tenant_and_unknown_tenant_is_a_translated_404(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson("/api/v1/super-admin/tenants/{$tenant->getTenantKey()}", $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->getJson('/api/v1/super-admin/tenants/no-such-tenant', $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', __('super.tenant_not_found'));
    }

    public function test_can_toggle_tenant_status_and_override_feature(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan->id]);
        $operator = $this->centralSuperAdmin();
        $headers = $this->steppedUpCentralHeaders($operator);
        $id = (string) $tenant->getTenantKey();

        $this->postJson("/api/v1/super-admin/tenants/{$id}/toggle-status", [
            'status' => TenantStatus::Suspended->value,
            'extend_days' => 0,
            'reason' => 'non_payment',
            'note' => 'متأخر عن السداد',
        ], $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', TenantStatus::Suspended->value);

        $this->assertSame(TenantStatus::Suspended->value, Tenant::query()->findOrFail($id)->status);

        // Routed through TransitionTenantStatusAction: one lifecycle event, actor super_admin.
        $event = TenantLifecycleEvent::query()->where('tenant_id', $id)->sole();
        $this->assertSame(TenantStatus::Active, $event->from_status);
        $this->assertSame(TenantStatus::Suspended, $event->to_status);
        $this->assertSame(TenantLifecycleActor::SuperAdmin, $event->actor);
        $this->assertSame(TenantSuspensionReason::NonPayment, $event->reason);
        $this->assertSame($operator->id, $event->central_user_id, 'The causer is the central operator, never a tenant user.');

        $this->postJson("/api/v1/super-admin/tenants/{$id}/override-feature", [
            'feature_key' => 'custom_branding',
        ], $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // PLAN-12: the toggle now writes an explicit grant row (tenant_feature_overrides).
        $this->assertDatabaseHas('tenant_feature_overrides', ['tenant_id' => $id, 'feature_key' => 'custom_branding', 'mode' => 'grant'], $this->centralConnectionName());
    }

    public function test_suspension_without_reason_is_422_and_changes_nothing(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $this->postJson("/api/v1/super-admin/tenants/{$id}/toggle-status", [
            'status' => TenantStatus::Suspended->value,
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->assertSame(TenantStatus::Active->value, Tenant::query()->findOrFail($id)->status);
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $id)->count());
    }

    public function test_toggle_status_validates_status_and_extend_days(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->postJson("/api/v1/super-admin/tenants/{$id}/toggle-status", ['status' => 'expired'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // extend_days only extends a trial; with any other status it must be 0/absent.
        $this->postJson("/api/v1/super-admin/tenants/{$id}/toggle-status", [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'other',
            'extend_days' => 30,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['extend_days']);

        $this->assertSame(TenantStatus::Active->value, Tenant::query()->findOrFail($id)->status);
    }

    public function test_super_admin_cannot_activate_a_tenant_only_a_payment_can(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Suspended->value]);
        $id = (string) $tenant->getTenantKey();

        $this->postJson("/api/v1/super-admin/tenants/{$id}/toggle-status", [
            'status' => TenantStatus::Active->value,
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'subscription.invalid_transition')
            ->assertJsonPath('details.to', TenantStatus::Active->value)
            ->assertJsonPath('details.actor', 'super_admin');

        $this->assertSame(TenantStatus::Suspended->value, Tenant::query()->findOrFail($id)->status);
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $id)->count());
    }

    public function test_toggle_status_of_unknown_tenant_is_404(): void
    {
        $this->postJson('/api/v1/super-admin/tenants/no-such-tenant/toggle-status', [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'other',
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(404)
            ->assertJsonPath('message', __('super.tenant_not_found'));
    }

    // -------------------------------------------------------- support role (read-only)

    public function test_support_reads_dashboard_and_tenants(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->centralHeaders($this->centralSupport());

        $this->getJson('/api/v1/super-admin/dashboard', $headers)->assertStatus(200);
        $this->getJson('/api/v1/super-admin/tenants', $headers)->assertStatus(200)->assertJson(['success' => true]);
        $this->getJson("/api/v1/super-admin/tenants/{$tenant->getTenantKey()}", $headers)->assertStatus(200);
    }

    public function test_support_cannot_write_tenants(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan->id]);
        $id = (string) $tenant->getTenantKey();
        $headers = $this->centralHeaders($this->centralSupport());

        $this->postJson('/api/v1/super-admin/tenants', [
            'name' => 'محاولة دعم',
            'slug' => 'support-attempt',
            'email' => 'support-attempt@elbon.test',
            'password' => 'secret1234',
            'plan_id' => $this->plan->id,
        ], $headers)->assertStatus(403);

        $this->postJson("/api/v1/super-admin/tenants/{$id}/toggle-status", [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'other',
        ], $headers)->assertStatus(403);

        $this->postJson("/api/v1/super-admin/tenants/{$id}/override-feature", ['feature_key' => 'custom_branding'], $headers)
            ->assertStatus(403);

        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-units", ['units' => ['كجم']], $headers)
            ->assertStatus(403);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Active->value, $fresh->status);
        $this->assertNotContains('custom_branding', (array) $fresh->enabled_features);
        $this->assertDatabaseMissing('tenants', ['slug' => 'support-attempt']);
        $this->assertSame(0, Subscription::query()->where('tenant_id', 'support-attempt')->count());
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $id)->count());
    }

    // ---------------------------------------------------------------------- isolation

    public function test_central_operator_token_never_works_on_the_tenant_api(): void
    {
        $tenant = $this->createTenant();
        $operatorToken = $this->centralHeaders($this->centralSuperAdmin())['Authorization'];

        $headers = array_merge($this->tenantHeaders($tenant), ['Authorization' => $operatorToken]);

        $this->getJson('/api/v1/auth/me', $headers)->assertStatus(401);
        $this->getJson('/api/v1/users', $headers)->assertStatus(401);
        $this->getJson($this->tenantUrl($tenant, '/api/v1/users'), $headers)->assertStatus(401);
    }

    public function test_control_plane_with_a_tenant_selected_is_404_even_for_a_super_admin(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson('/api/v1/super-admin/tenants', $headers + ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertStatus(404);
        $this->postJson($this->tenantUrl($tenant, "/api/v1/super-admin/tenants/{$tenant->getTenantKey()}/toggle-status"), [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'other',
        ], $headers)->assertStatus(404);

        $this->assertSame(TenantStatus::Active->value, Tenant::query()->findOrFail($tenant->getTenantKey())->status);
    }

    /** Remove a tenant created through the API (not by the harness): database, storage, rows. */
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
}
