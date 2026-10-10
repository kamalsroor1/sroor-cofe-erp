<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * IDEN-1.8 (split from SuperAdminApiTest): plans endpoints of the control plane
 * (GET /plans: super_admin.plans.view, PUT /plans/{id}: super_admin.plans.manage), as an
 * App\Models\CentralUser. Billing-grade plan fields are covered in
 * Tests\Feature\Billing\PlansSchemaMigrationTest.
 *
 * A tenant token is 401 here (was 403 before IDEN-1.4): see SuperAdminTenantsApiTest.
 */
final class SuperAdminPlansApiTest extends TenantTestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'باقة المحامص الذهبية المطورة',
            'price_monthly' => 750.00,
            'price_yearly' => 7500.00,
            'max_users' => 20,
            'max_stores' => 5,
            'max_items' => 1000,
            'max_invoices_per_month' => 10000,
            'is_active' => true,
            'is_popular' => true,
            'features' => ['coffee_blender' => true, 'smart_reorder' => true, 'pos_offline' => true],
        ], $overrides);
    }

    public function test_can_get_plans_and_update_plan(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $listed = collect($this->getJson('/api/v1/super-admin/plans', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->json('data.plans'))
            ->firstWhere('id', $this->plan->id);
        $this->assertIsArray($listed);
        $this->assertSame('500.000', $listed['price_monthly']);

        $this->putJson("/api/v1/super-admin/plans/{$this->plan->id}", $this->updatePayload(), $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $fresh = Plan::query()->findOrFail($this->plan->id);
        $this->assertSame('باقة المحامص الذهبية المطورة', $fresh->name);
        $this->assertSame(20, $fresh->max_users);
        // JSON floats leave the request as exact 3-decimal strings (golden rule 1).
        $this->assertSame('750.000', $fresh->price_monthly);
        $this->assertSame('7500.000', $fresh->price_yearly);
        $this->assertTrue($fresh->features['pos_offline']);
    }

    public function test_update_plan_validates_input(): void
    {
        $this->putJson("/api/v1/super-admin/plans/{$this->plan->id}", $this->updatePayload([
            'name' => '',
            'price_monthly' => '10.1234',
            'max_users' => 0,
            'features' => 'all',
        ]), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price_monthly', 'max_users', 'features']);

        $this->assertSame('باقة المحامص الاحترافية', Plan::query()->findOrFail($this->plan->id)->name);
    }

    public function test_update_of_unknown_plan_is_a_translated_404(): void
    {
        $missing = (int) Plan::query()->max('id') + 1000;

        $this->putJson("/api/v1/super-admin/plans/{$missing}", $this->updatePayload(), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(404)
            ->assertJsonPath('message', __('super.plan_not_found'));
    }

    public function test_guest_is_401(): void
    {
        $this->getJson('/api/v1/super-admin/plans')->assertStatus(401);
        $this->putJson("/api/v1/super-admin/plans/{$this->plan->id}", $this->updatePayload())->assertStatus(401);
    }

    public function test_tenant_and_legacy_tokens_are_401_and_change_nothing(): void
    {
        $tenant = $this->createTenant();
        $tenantBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];
        $legacyBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->legacyUsersTableSuperAdmin()->createToken('legacy')->plainTextToken];

        foreach ([$tenantBearer, $legacyBearer] as $headers) {
            $this->getJson('/api/v1/super-admin/plans', $headers)->assertStatus(401);
            $this->putJson("/api/v1/super-admin/plans/{$this->plan->id}", $this->updatePayload(), $headers)->assertStatus(401);
        }

        $this->assertSame('باقة المحامص الاحترافية', Plan::query()->findOrFail($this->plan->id)->name);
    }

    public function test_support_reads_plans_but_cannot_update(): void
    {
        $headers = $this->centralHeaders($this->centralSupport());

        $this->getJson('/api/v1/super-admin/plans', $headers)->assertStatus(200)->assertJson(['success' => true]);
        $this->putJson("/api/v1/super-admin/plans/{$this->plan->id}", $this->updatePayload(), $headers)->assertStatus(403);

        $fresh = Plan::query()->findOrFail($this->plan->id);
        $this->assertSame('باقة المحامص الاحترافية', $fresh->name);
        $this->assertSame('500.000', $fresh->price_monthly);
    }

    public function test_operator_without_role_cannot_read_plans(): void
    {
        $this->getJson('/api/v1/super-admin/plans', $this->centralHeaders($this->centralOperator(null)))->assertStatus(403);
    }
}
