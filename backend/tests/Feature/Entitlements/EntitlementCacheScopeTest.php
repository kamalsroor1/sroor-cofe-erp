<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\LimitResource;
use App\Enums\TenantStatus;
use App\Http\Middleware\ResolveApiTenancy;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\Entitlements\TenantEntitlementService;
use App\Support\TenantCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * ENTI-2.2 [REV]: the entitlement cache lives in the EXPLICIT tenant scope
 * (TenantCache::keyFor/bumpFor), so a change made from CENTRAL code (super-admin, billing)
 * is seen by the tenant's very next request, never a stale cached value.
 *
 * The probe route below is a test-only tenant route (ResolveApiTenancy, X-Tenant header)
 * that answers through the bound TenantFeatureManagerInterface and Pennant, exactly what a
 * real tenant endpoint does.
 */
#[Group('entitlements')]
#[Group('mysql')]
final class EntitlementCacheScopeTest extends TenantTestCase
{
    private const PROBE = '/api/__test/entitlements-probe';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(ResolveApiTenancy::class)->get(self::PROBE, function () {
            $tenant = tenant();
            abort_unless($tenant instanceof Tenant, 404);

            return response()->json([
                'tenant' => $tenant->getTenantKey(),
                'users' => app(TenantFeatureManagerInterface::class)->limit($tenant, LimitResource::Users),
                'mixes' => Feature::active('mixes.manage'),
            ]);
        });
    }

    public function test_a_central_plan_change_is_seen_by_the_next_tenant_request(): void
    {
        $plan = $this->plan(['max_users' => 3]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);

        $this->probe($tenant)->assertOk()->assertJson(['users' => 3, 'mixes' => false]);

        // Central context (super-admin editing the plan).
        $plan->update(['max_users' => 10, 'features' => ['mixes.manage' => true]]);

        $this->probe($tenant)->assertOk()->assertJson(['users' => 10, 'mixes' => true]);
    }

    public function test_central_add_on_and_subscription_changes_are_seen_by_the_next_tenant_request(): void
    {
        $plan = $this->plan(['max_users' => 3]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $subscription = $this->subscription($tenant, $plan);

        $this->probe($tenant)->assertJson(['users' => 3]);

        $line = SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->id,
            'addon_id' => $this->addon('addon.user', ['users' => 1])->id,
            'quantity' => 2,
            'unit_price' => '79.000',
            'status' => SubscriptionAddonStatus::Active,
            'starts_at' => now()->subDay(),
        ]);
        $this->probe($tenant)->assertJson(['users' => 5]);

        $line->addon->update(['bundled_limits' => ['users' => 3]]);
        // An Addon catalog edit bumps the tenants holding it.
        $this->probe($tenant)->assertJson(['users' => 9]);

        $subscription->update(['status' => SubscriptionStatus::Cancelled]);
        $this->probe($tenant)->assertJson(['users' => 3]);

        $subscription->update(['status' => SubscriptionStatus::PastDue]);
        // past_due (grace) keeps the add-ons.
        $this->probe($tenant)->assertJson(['users' => 9]);

        $tenant->update(['status' => TenantStatus::ReadOnly->value]);
        // A read_only tenant loses its add-ons.
        $this->probe($tenant)->assertJson(['users' => 3]);
    }

    public function test_a_central_override_toggle_is_seen_by_the_next_tenant_request(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan()->id]);
        $this->probe($tenant)->assertJson(['mixes' => false]);

        app(TenantFeatureManagerInterface::class)->toggleFeatureOverride($tenant, 'blender.access');

        $this->probe($tenant)->assertJson(['mixes' => true]);
    }

    public function test_values_are_cached_until_the_tenant_version_is_bumped(): void
    {
        $plan = $this->plan(['max_users' => 3]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $this->probe($tenant)->assertJson(['users' => 3]);

        // A raw write fires no model event: the cached value is (deliberately) still served.
        DB::connection($this->centralConnectionName())->table('plans')->where('id', $plan->id)->update(['max_users' => 50]);
        $this->probe($tenant)->assertJson(['users' => 3]);

        // Explicit invalidation from central code.
        app(TenantEntitlementService::class)->forget((string) $tenant->id);
        $this->probe($tenant)->assertJson(['users' => 50]);
    }

    public function test_pennant_never_answers_from_a_previous_request_after_a_bump_from_elsewhere(): void
    {
        $plan = $this->plan(['features' => []]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $this->probe($tenant)->assertJson(['mixes' => false]);

        // Another process changed the data and bumped the version: no model event and no
        // Pennant flush ran in THIS process. The next tenant request must still be fresh.
        DB::connection($this->centralConnectionName())->table('plans')->where('id', $plan->id)
            ->update(['features' => json_encode(['mixes.manage' => true])]);
        TenantCache::bumpFor((string) $tenant->id, TenantEntitlementService::CACHE_NAMESPACE);

        $this->probe($tenant)->assertJson(['mixes' => true]);
    }

    public function test_a_bump_moves_only_that_tenants_version_never_the_central_one(): void
    {
        $plan = $this->plan();
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $other = $this->createTenant(['plan_id' => $this->plan()->id]);

        $centralBefore = TenantCache::centralVersion(TenantEntitlementService::CACHE_NAMESPACE);
        $implicitBefore = TenantCache::version(TenantEntitlementService::CACHE_NAMESPACE);
        $tenantBefore = TenantCache::versionFor((string) $tenant->id, TenantEntitlementService::CACHE_NAMESPACE);
        $otherBefore = TenantCache::versionFor((string) $other->id, TenantEntitlementService::CACHE_NAMESPACE);

        $plan->update(['max_users' => 7]);

        $this->assertNotSame($tenantBefore, TenantCache::versionFor((string) $tenant->id, TenantEntitlementService::CACHE_NAMESPACE));
        $this->assertSame($otherBefore, TenantCache::versionFor((string) $other->id, TenantEntitlementService::CACHE_NAMESPACE), 'Another plan\'s tenant is untouched.');
        $this->assertSame($centralBefore, TenantCache::centralVersion(TenantEntitlementService::CACHE_NAMESPACE));
        $this->assertSame($implicitBefore, TenantCache::version(TenantEntitlementService::CACHE_NAMESPACE), 'The central-context implicit version must not move.');

        // Inside the tenant the implicit key resolves to the very key the bump moved.
        $insideVersion = $this->inTenant($tenant, fn (): string => TenantCache::version(TenantEntitlementService::CACHE_NAMESPACE));
        $this->assertSame(TenantCache::versionFor((string) $tenant->id, TenantEntitlementService::CACHE_NAMESPACE), $insideVersion);
    }

    public function test_one_tenants_cached_entitlements_never_leak_to_another(): void
    {
        $small = $this->createTenant(['plan_id' => $this->plan(['max_users' => 2])->id]);
        $large = $this->createTenant(['plan_id' => $this->plan(['max_users' => 40, 'features' => ['mixes.manage' => true]])->id]);

        $this->probe($small)->assertJson(['tenant' => $small->id, 'users' => 2, 'mixes' => false]);
        $this->probe($large)->assertJson(['tenant' => $large->id, 'users' => 40, 'mixes' => true]);
        $this->probe($small)->assertJson(['tenant' => $small->id, 'users' => 2, 'mixes' => false]);
    }

    public function test_a_bump_inside_a_central_transaction_is_repeated_after_commit(): void
    {
        $plan = $this->plan(['max_users' => 3]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $central = DB::connection($this->centralConnectionName());

        $central->transaction(function () use ($plan, $tenant): void {
            $plan->update(['max_users' => 4]);

            // A concurrent reader caches the value between the change and the commit.
            app(TenantEntitlementService::class)->entitlements($tenant);
        });

        $this->assertSame(4, app(TenantEntitlementService::class)->limit($tenant, LimitResource::Users));
    }

    /**
     * @return TestResponse<Response>
     */
    private function probe(Tenant $tenant): TestResponse
    {
        return $this->getJson(self::PROBE, ['X-Tenant' => (string) $tenant->getTenantKey()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): Plan
    {
        $slug = 'cache-'.Str::lower(Str::random(10));

        return Plan::query()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => [],
            'max_users' => 3,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    private function subscription(Tenant $tenant, Plan $plan): Subscription
    {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Active,
            'amount' => '449.000',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * @param  array<string, int>  $limits
     */
    private function addon(string $key, array $limits): Addon
    {
        return Addon::query()->firstOrCreate(['key' => $key], [
            'name_key' => 'plans.addons.test.name',
            'bundled_limits' => $limits,
            'unit_price' => '79.000',
        ]);
    }
}
