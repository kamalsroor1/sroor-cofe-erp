<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Actions\Plans\ListShopsNearLimitAction;
use App\Enums\CentralAuditEvent;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * PLAN-14: GET /super-admin/plans/{id}/changes (audit trail of a plan) and
 * GET /super-admin/near-limit-shops (shops at >= threshold of a limit), plus the facts the
 * console relies on: tenants_count on the plan list and the audit row of a plan update.
 */
final class PlanConsoleInsightsApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const BASE = '/api/v1/super-admin';

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->seed(PlansAndFeaturesSeeder::class);
        Cache::flush();

        $this->plan = Plan::query()->create([
            'name' => 'باقة محدودة',
            'slug' => 'limited-'.Str::lower(Str::random(6)),
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 10,
            'max_stores' => 2,
            'max_items' => 100,
            'max_customers' => 10,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
            'features' => [],
        ]);
    }

    private function shopOnPlan(string $name, int $customers): Tenant
    {
        $tenant = $this->createTenant(['name' => $name, 'plan_id' => $this->plan->id, 'status' => 'active']);

        $this->inTenant($tenant, function () use ($customers): void {
            for ($i = 1; $i <= $customers; $i++) {
                Customer::query()->create(['name' => 'عميل '.$i, 'current_balance' => '0.000', 'is_active' => true]);
            }
        });

        return $tenant;
    }

    // ------------------------------------------------------------ changes

    public function test_plan_update_is_audited_and_listed_with_the_operator_and_changed_fields(): void
    {
        $operator = $this->centralSuperAdmin();
        $headers = $this->steppedUpCentralHeaders($operator);

        $this->putJson(self::BASE.'/plans/'.$this->plan->id, [
            'name' => 'باقة محدودة',
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 12,
            'max_stores' => 2,
            'max_items' => 100,
            'max_customers' => null,
            'is_active' => true,
            'is_popular' => false,
            'features' => ['mixes.manage' => true],
        ], $headers)->assertOk();

        $rows = $this->getJson(self::BASE.'/plans/'.$this->plan->id.'/changes', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.event', CentralAuditEvent::PlanUpdated->value)
            ->assertJsonPath('data.0.operator', $operator->name)
            ->json('data');

        $this->assertContains('max_customers', $rows[0]['changed']);
        $this->assertArrayNotHasKey('values', $rows[0]);
    }

    public function test_changes_are_scoped_to_the_plan_and_support_can_read_them(): void
    {
        $other = Plan::query()->create([
            'name' => 'أخرى', 'slug' => 'other-'.Str::lower(Str::random(6)), 'price_monthly' => '1.000',
            'price_yearly' => '1.000', 'max_users' => 1, 'max_stores' => 1, 'max_items' => 1,
            'is_active' => true, 'is_popular' => false, 'features' => [],
        ]);

        $this->getJson(self::BASE.'/plans/'.$other->id.'/changes', $this->centralHeaders($this->centralSupport()))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_changes_unknown_plan_is_404_guest_401_and_no_role_403(): void
    {
        $this->getJson(self::BASE.'/plans/999999/changes', $this->centralHeaders($this->centralSuperAdmin()))->assertNotFound();
        $this->getJson(self::BASE.'/plans/'.$this->plan->id.'/changes')->assertUnauthorized();
        $this->getJson(self::BASE.'/plans/'.$this->plan->id.'/changes', $this->centralHeaders($this->centralOperator(null)))->assertForbidden();
    }

    public function test_every_limit_sent_empty_is_stored_as_unlimited(): void
    {
        $payload = [
            'name' => 'باقة محدودة',
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'is_active' => true,
            'is_popular' => false,
            'features' => ['mixes.manage' => true],
        ] + array_fill_keys(Plan::LIMIT_COLUMNS, null);

        $this->putJson(self::BASE.'/plans/'.$this->plan->id, $payload, $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertOk();

        $stored = $this->plan->fresh()->only(Plan::LIMIT_COLUMNS);
        $this->assertSame(array_fill_keys(Plan::LIMIT_COLUMNS, null), $stored);
    }

    public function test_plan_list_carries_the_shop_count_used_by_the_impact_confirmation(): void
    {
        $this->shopOnPlan('محل ١', 0);
        $this->shopOnPlan('محل ٢', 0);

        $plans = $this->getJson(self::BASE.'/plans', $this->centralHeaders($this->centralSuperAdmin()))->assertOk()->json('data.plans');

        $this->assertSame(2, collect($plans)->firstWhere('id', $this->plan->id)['tenants_count']);
    }

    // ------------------------------------------------------------ near limit

    public function test_near_limit_lists_only_shops_at_or_above_the_threshold_sorted_by_percent(): void
    {
        $this->shopOnPlan('محل قريب', 8);
        $this->shopOnPlan('محل ممتلئ', 10);
        $this->shopOnPlan('محل مرتاح', 3);

        $response = $this->getJson(self::BASE.'/near-limit-shops', $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.threshold', 80)
            ->assertJsonPath('meta.capped', false);

        $rows = $response->json('data');
        $this->assertSame(['محل ممتلئ', 'محل قريب'], array_column($rows, 'tenant_name'));
        $this->assertSame('customers', $rows[0]['resource']);
        $this->assertSame(10, $rows[0]['used']);
        $this->assertSame(10, $rows[0]['limit']);
        $this->assertSame(100, $rows[0]['percent']);
        $this->assertSame(['id' => $this->plan->id, 'name' => 'باقة محدودة'], $rows[0]['plan']);
        $this->assertSame(80, $rows[1]['percent']);
        $this->assertFalse(tenancy()->initialized, 'Tenancy is ended after the scan.');
    }

    public function test_near_limit_ignores_unlimited_plans_and_blocked_shops(): void
    {
        $this->plan->forceFill(array_fill_keys(Plan::LIMIT_COLUMNS, null))->save();
        $this->shopOnPlan('بلا حدود', 5);

        $blockedPlan = Plan::query()->create([
            'name' => 'مغلقة', 'slug' => 'blocked-'.Str::lower(Str::random(6)), 'price_monthly' => '1.000',
            'price_yearly' => '1.000', 'max_users' => 1, 'max_stores' => 1, 'max_items' => 1, 'max_customers' => 1,
            'is_active' => true, 'is_popular' => false, 'features' => [],
        ]);
        $this->createTenant(['name' => 'موقوف', 'plan_id' => $blockedPlan->id, 'status' => 'suspended']);

        $this->getJson(self::BASE.'/near-limit-shops', $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.scanned', 0);
    }

    public function test_near_limit_threshold_and_pagination_are_validated_and_applied(): void
    {
        $this->shopOnPlan('محل أ', 10);
        $this->shopOnPlan('محل ب', 9);
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson(self::BASE.'/near-limit-shops?threshold=10', $headers)->assertStatus(422)->assertJsonValidationErrors('threshold');
        $this->getJson(self::BASE.'/near-limit-shops?per_page=1000', $headers)->assertStatus(422)->assertJsonValidationErrors('per_page');

        $this->getJson(self::BASE.'/near-limit-shops?per_page=1&page=2', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tenant_name', 'محل ب');
    }

    public function test_near_limit_is_cached_until_refreshed(): void
    {
        $tenant = $this->shopOnPlan('محل مخزن', 8);
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson(self::BASE.'/near-limit-shops', $headers)->assertJsonPath('meta.total', 1);

        $this->inTenant($tenant, fn () => Customer::query()->delete());

        $this->getJson(self::BASE.'/near-limit-shops', $headers)->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'/near-limit-shops?refresh=1', $headers)->assertJsonPath('meta.total', 0);
        $this->assertSame(500, ListShopsNearLimitAction::SCAN_CAP);
    }

    public function test_near_limit_survives_a_shop_whose_usage_cannot_be_read(): void
    {
        $bad = $this->createTenant(['id' => 'near-a-broken', 'name' => 'محل معطل', 'plan_id' => $this->plan->id, 'status' => 'active']);
        $good = $this->createTenant(['id' => 'near-b-fine', 'name' => 'محل سليم', 'plan_id' => $this->plan->id, 'status' => 'active']);
        $this->inTenant($good, function (): void {
            for ($i = 1; $i <= 9; $i++) {
                Customer::query()->create(['name' => 'عميل '.$i, 'current_balance' => '0.000', 'is_active' => true]);
            }
        });

        // Reading the usage of the first shop throws; the scan has to go on with the next one.
        DB::listen(function ($query) use ($bad): void {
            if (tenant()?->getTenantKey() === $bad->getTenantKey() && str_contains($query->sql, 'customers')) {
                throw new \RuntimeException('usage read failed');
            }
        });

        $this->getJson(self::BASE.'/near-limit-shops', $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('meta.failed', 1)
            ->assertJsonPath('meta.scanned', 2)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.tenant_name', 'محل سليم');

        $this->assertFalse(tenancy()->initialized, 'Tenancy is ended even though a shop failed.');
    }

    public function test_near_limit_refresh_is_limited_per_operator_and_falls_back_to_plain_reads(): void
    {
        $this->shopOnPlan('محل', 8);
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson(self::BASE.'/near-limit-shops?refresh=1', $headers)->assertOk();
        $this->getJson(self::BASE.'/near-limit-shops?refresh=1', $headers)->assertOk();
        $this->getJson(self::BASE.'/near-limit-shops?refresh=1', $headers)->assertStatus(429);
        $this->getJson(self::BASE.'/near-limit-shops', $headers)->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_near_limit_scan_waits_on_the_lock_and_reuses_the_cached_result(): void
    {
        $this->shopOnPlan('محل', 8);
        $headers = $this->centralHeaders($this->centralSuperAdmin());
        $this->getJson(self::BASE.'/near-limit-shops', $headers)->assertOk();

        // Another worker is scanning right now and the cache is warm: a plain read must not queue behind it.
        $lock = Cache::lock('plans:near-limit-shops:80:lock', 30);
        $this->assertTrue($lock->get());

        $this->getJson(self::BASE.'/near-limit-shops', $headers)->assertOk()->assertJsonPath('meta.total', 1);

        $lock->release();
    }

    public function test_near_limit_is_super_admin_only(): void
    {
        $this->getJson(self::BASE.'/near-limit-shops')->assertUnauthorized();
        $this->getJson(self::BASE.'/near-limit-shops', $this->centralHeaders($this->centralSupport()))->assertForbidden();
        $this->getJson(self::BASE.'/near-limit-shops', $this->centralHeaders($this->centralOperator(null)))->assertForbidden();
    }
}
