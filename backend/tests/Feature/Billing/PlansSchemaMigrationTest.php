<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * ENTI-1.2: the central `plans` table moves to billing-grade money (DECIMAL(12,3)),
 * nullable limits (null = unlimited, the old "magic" 99/999/99999… sentinels become
 * NULL), and gains max_warehouses / max_vans / trial_days / is_public / founder
 * prices / name_key. Plan and PlanFeature are pinned to the central connection, so
 * they keep reading the central DB while a tenant is initialized.
 */
#[Group('billing')]
#[Group('mysql')]
final class PlansSchemaMigrationTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const MIGRATION = 'migrations/2026_10_10_200100_update_plans_table_for_billing.php';

    private const NEW_COLUMNS = [
        'max_warehouses',
        'max_vans',
        'trial_days',
        'is_public',
        'founder_price_monthly',
        'founder_price_yearly',
        'name_key',
    ];

    private const LIMIT_COLUMNS = [
        'max_users',
        'max_stores',
        'max_warehouses',
        'max_vans',
        'max_items',
        'max_invoices_per_month',
        'max_storage_mb',
    ];

    public function test_plans_table_has_the_billing_columns(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        foreach (self::NEW_COLUMNS as $column) {
            $this->assertTrue($schema->hasColumn('plans', $column), "plans.{$column} is missing.");
        }

        $columns = collect($schema->getColumns('plans'))->keyBy('name');

        foreach (self::LIMIT_COLUMNS as $column) {
            $this->assertTrue((bool) $columns[$column]['nullable'], "plans.{$column} must be nullable (null = unlimited).");
        }

        foreach (['price_monthly', 'price_yearly'] as $column) {
            $this->assertFalse((bool) $columns[$column]['nullable'], "plans.{$column} must stay NOT NULL.");
        }

        foreach (['price_monthly', 'price_yearly', 'founder_price_monthly', 'founder_price_yearly'] as $column) {
            if (in_array($schema->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $this->assertSame('decimal(12,3)', strtolower((string) $columns[$column]['type']), "plans.{$column} must be DECIMAL(12,3).");
            }
        }
    }

    public function test_new_columns_have_safe_defaults(): void
    {
        $plan = Plan::query()->create($this->planAttributes(['slug' => 'defaults']));
        $plan->refresh();

        $this->assertSame(0, $plan->max_warehouses);
        $this->assertSame(0, $plan->max_vans);
        $this->assertSame(0, $plan->trial_days);
        $this->assertTrue($plan->is_public);
        $this->assertNull($plan->founder_price_monthly);
        $this->assertNull($plan->founder_price_yearly);
        $this->assertNull($plan->name_key);
    }

    public function test_money_is_stored_with_three_decimals_and_limits_keep_null(): void
    {
        $plan = Plan::query()->create($this->planAttributes([
            'slug' => 'enterprise-like',
            'price_monthly' => '123456789.125',
            'price_yearly' => '17990',
            'founder_price_monthly' => '999.5',
            'founder_price_yearly' => '9990.000',
            'max_users' => null,
            'max_stores' => null,
            'max_warehouses' => null,
            'max_vans' => null,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => null,
            'trial_days' => 14,
            'is_public' => false,
            'name_key' => 'plans.enterprise.name',
        ]));
        $plan = Plan::query()->findOrFail($plan->id);

        $this->assertSame('123456789.125', $plan->price_monthly);
        $this->assertSame('17990.000', $plan->price_yearly);
        $this->assertSame('999.500', $plan->founder_price_monthly);
        $this->assertSame('9990.000', $plan->founder_price_yearly);
        foreach (self::LIMIT_COLUMNS as $column) {
            $this->assertNull($plan->{$column}, "{$column} must round-trip as null (unlimited).");
        }
        $this->assertSame(14, $plan->trial_days);
        $this->assertFalse($plan->is_public);
        $this->assertSame('plans.enterprise.name', $plan->name_key);
    }

    public function test_up_converts_sentinels_to_null_and_keeps_real_limits(): void
    {

        try {
            $this->migrateDown();

            $this->assertFalse(Schema::hasColumn('plans', 'max_warehouses'), 'down() must drop the new columns.');

            $now = now();
            DB::table('plans')->insert([
                $this->legacyRow('legacy-enterprise', '999.00', 999, 99, 99999, 999999, 51200, $now),
                $this->legacyRow('legacy-basic', '299.50', 3, 2, 500, 1000, 2048, $now),
                $this->legacyRow('legacy-negative', '0.00', -1, 1, -1, 100, 500, $now),
            ]);

            $this->migrateUp();

            $enterprise = DB::table('plans')->where('slug', 'legacy-enterprise')->first();
            $this->assertNull($enterprise->max_users);
            $this->assertNull($enterprise->max_stores);
            $this->assertNull($enterprise->max_items);
            $this->assertNull($enterprise->max_invoices_per_month);
            $this->assertSame(51200, (int) $enterprise->max_storage_mb, 'A real storage quota is not a sentinel.');
            $this->assertSame(0, (int) $enterprise->max_warehouses);
            $this->assertSame(0, (int) $enterprise->max_vans);
            $this->assertSame(1, (int) $enterprise->is_public);
            $this->assertSame(0, (int) $enterprise->trial_days);

            $basic = DB::table('plans')->where('slug', 'legacy-basic')->first();
            $this->assertSame(3, (int) $basic->max_users);
            $this->assertSame(2, (int) $basic->max_stores);
            $this->assertSame(500, (int) $basic->max_items);
            $this->assertSame(1000, (int) $basic->max_invoices_per_month);
            $this->assertSame(2048, (int) $basic->max_storage_mb);
            $this->assertSame(0, bccomp((string) $basic->price_monthly, '299.500', 3));

            $negative = DB::table('plans')->where('slug', 'legacy-negative')->first();
            $this->assertNull($negative->max_users);
            $this->assertNull($negative->max_items);
            $this->assertSame(1, (int) $negative->max_stores);
        } finally {
            $this->ensureMigrated();
        }
    }

    public function test_down_restores_not_null_limits_with_sentinels(): void
    {

        try {
            Plan::query()->create($this->planAttributes([
                'slug' => 'unlimited',
                'max_users' => null,
                'max_stores' => null,
                'max_items' => null,
                'max_invoices_per_month' => null,
                'max_storage_mb' => null,
            ]));

            $this->migrateDown();

            foreach (self::NEW_COLUMNS as $column) {
                $this->assertFalse(Schema::hasColumn('plans', $column), "down() must drop plans.{$column}.");
            }

            $row = DB::table('plans')->where('slug', 'unlimited')->first();
            $this->assertSame(999, (int) $row->max_users);
            $this->assertSame(99, (int) $row->max_stores);
            $this->assertSame(99999, (int) $row->max_items);
            $this->assertSame(999999, (int) $row->max_invoices_per_month);
            $this->assertSame(999999, (int) $row->max_storage_mb);

            // Round trip: the sentinels written by down() become NULL again.
            $this->migrateUp();

            $row = DB::table('plans')->where('slug', 'unlimited')->first();
            $this->assertNull($row->max_users);
            $this->assertNull($row->max_storage_mb);
        } finally {
            $this->ensureMigrated();
        }
    }

    public function test_plan_and_plan_feature_read_the_central_db_inside_a_tenant(): void
    {
        $plan = Plan::query()->create($this->planAttributes(['slug' => 'central-pinned']));
        $feature = PlanFeature::query()->create([
            'key' => 'qa.central_pinned',
            'name' => 'QA',
            'module' => 'system',
        ]);

        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $central = $this->centralConnectionName();

        $seen = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'plan_connection' => (new Plan)->getConnectionName(),
            'feature_connection' => (new PlanFeature)->getConnectionName(),
            'plan_slug' => Plan::query()->find($plan->id)?->slug,
            'feature_key' => PlanFeature::query()->where('key', 'qa.central_pinned')->value('key'),
            'tenant_plan_slug' => tenant()?->plan?->slug,
        ]);

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame($central, $seen['plan_connection']);
        $this->assertSame($central, $seen['feature_connection']);
        $this->assertSame('central-pinned', $seen['plan_slug']);
        $this->assertSame($feature->key, $seen['feature_key']);
        $this->assertSame('central-pinned', $seen['tenant_plan_slug']);
    }

    public function test_tenant_limits_include_warehouses_and_vans_and_keep_null(): void
    {
        $plan = Plan::query()->create($this->planAttributes([
            'slug' => 'limits',
            'max_users' => 8,
            'max_stores' => 3,
            'max_warehouses' => 2,
            'max_vans' => 1,
            'max_items' => null,
        ]));
        $tenant = $this->createTenant(['plan_id' => $plan->id]);

        $limits = $tenant->fresh()?->getAllLimits();

        $this->assertSame(8, $limits['users']);
        $this->assertSame(3, $limits['stores']);
        $this->assertSame(2, $limits['warehouses']);
        $this->assertSame(1, $limits['vans']);
        $this->assertNull($limits['items']);
    }

    public function test_super_admin_can_set_limits_to_null_and_new_fields(): void
    {
        $plan = Plan::query()->create($this->planAttributes(['slug' => 'editable']));
        $headers = $this->superAdminHeaders();

        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload([
            'price_monthly' => '1799.000',
            'price_yearly' => 17990,
            'max_users' => null,
            'max_stores' => null,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => null,
            'max_warehouses' => null,
            'max_vans' => 5,
            'trial_days' => 14,
            'is_public' => false,
            'founder_price_monthly' => '999.000',
            'founder_price_yearly' => null,
            'name_key' => 'plans.enterprise.name',
        ]), $headers)->assertOk()->assertJson(['success' => true]);

        $fresh = Plan::query()->findOrFail($plan->id);
        $this->assertSame('1799.000', $fresh->price_monthly);
        $this->assertSame('17990.000', $fresh->price_yearly);
        $this->assertNull($fresh->max_users);
        $this->assertNull($fresh->max_stores);
        $this->assertNull($fresh->max_items);
        $this->assertNull($fresh->max_invoices_per_month);
        $this->assertNull($fresh->max_storage_mb);
        $this->assertNull($fresh->max_warehouses);
        $this->assertSame(5, $fresh->max_vans);
        $this->assertSame(14, $fresh->trial_days);
        $this->assertFalse($fresh->is_public);
        $this->assertSame('999.000', $fresh->founder_price_monthly);
        $this->assertNull($fresh->founder_price_yearly);
        $this->assertSame('plans.enterprise.name', $fresh->name_key);

        $listed = collect($this->getJson('/api/v1/super-admin/plans', $this->superAdminHeaders())
            ->assertOk()
            ->json('data.plans'))
            ->firstWhere('id', $plan->id);

        $this->assertIsArray($listed);
        $this->assertNull($listed['max_users'], 'PlanResource must not turn "unlimited" into 0.');
        $this->assertNull($listed['max_warehouses']);
        $this->assertSame(5, $listed['max_vans']);
        $this->assertSame('1799.000', $listed['price_monthly']);
        $this->assertSame('999.000', $listed['founder_price_monthly']);
        $this->assertSame(14, $listed['trial_days']);
        $this->assertFalse($listed['is_public']);
    }

    public function test_update_keeps_working_for_the_legacy_payload(): void
    {
        $plan = Plan::query()->create($this->planAttributes(['slug' => 'legacy-payload']));

        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload(), $this->superAdminHeaders())
            ->assertOk();

        $fresh = Plan::query()->findOrFail($plan->id);
        $this->assertSame(20, $fresh->max_users);
        $this->assertSame(0, $fresh->max_warehouses, 'Fields absent from the payload stay untouched.');
        $this->assertTrue($fresh->is_public);
    }

    public function test_update_rejects_invalid_money_and_limits(): void
    {
        $plan = Plan::query()->create($this->planAttributes(['slug' => 'invalid']));

        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload([
            'price_monthly' => '10.1234',
            'price_yearly' => -1,
            'max_users' => 0,
            'max_warehouses' => -1,
            'max_vans' => 'many',
            'trial_days' => 366,
            'founder_price_monthly' => '-5',
            'name_key' => str_repeat('k', 101),
        ]), $this->superAdminHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'price_monthly',
                'price_yearly',
                'max_users',
                'max_warehouses',
                'max_vans',
                'trial_days',
                'founder_price_monthly',
                'name_key',
            ]);

        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload(['max_users' => 'x']), $this->superAdminHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_users']);

        $payloadWithoutLimit = $this->updatePayload();
        unset($payloadWithoutLimit['max_items']);
        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $payloadWithoutLimit, $this->superAdminHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_items']);
    }

    public function test_update_requires_authentication_and_super_admin(): void
    {
        $plan = Plan::query()->create($this->planAttributes(['slug' => 'guarded']));

        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload())
            ->assertStatus(401);

        // A tenant token is not a central identity at all.
        $tenant = $this->createTenant();
        $tenantBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];
        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload(), $tenantBearer)
            ->assertStatus(401);

        // With the tenant selected (X-Tenant, as the tenant clients send it) the control plane
        // is not even there: EnsureCentralContext answers 404 before authentication (IDEN-1.4).
        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload(), $this->tenantHeaders($tenant))
            ->assertStatus(404);

        // The Phase 0 operator (central `users` row with the web-guard super_admin role) is gone.
        $legacyBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->legacyUsersTableSuperAdmin()->createToken('legacy')->plainTextToken];
        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload(), $legacyBearer)
            ->assertStatus(401);

        // A central user without the super_admin role is authenticated but forbidden; so is the
        // read-only support role.
        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload(), $this->superAdminHeaders(withRole: false))
            ->assertStatus(403);
        $this->putJson("/api/v1/super-admin/plans/{$plan->id}", $this->updatePayload(), $this->centralHeaders($this->centralSupport()))
            ->assertStatus(403);

        $this->assertSame('Plan editable', Plan::query()->findOrFail($plan->id)->name);
    }

    /**
     * IDEN-1.8: Bearer headers for a central operator (App\Models\CentralUser, central token,
     * routes/central.php → AuthenticateCentral → can:super_admin.plans.*). Before IDEN-1.4 this
     * was a legacy central App\Models\User through ApiTokenAuth.
     *
     * @return array<string, string>
     */
    private function superAdminHeaders(bool $withRole = true): array
    {
        return $this->steppedUpCentralHeaders($withRole ? $this->centralSuperAdmin() : $this->centralOperator(null));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planAttributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Plan editable',
            'slug' => 'plan',
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'max_users' => 3,
            'max_stores' => 1,
            'max_items' => 3000,
            'max_invoices_per_month' => 6000,
            'max_storage_mb' => 2048,
            'features' => ['pos.access' => true],
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated plan',
            'price_monthly' => 899.00,
            'price_yearly' => 8990.00,
            'max_users' => 20,
            'max_stores' => 5,
            'max_items' => 20000,
            'max_invoices_per_month' => 30000,
            'is_active' => true,
            'is_popular' => true,
            'features' => ['pos.access' => true],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyRow(string $slug, string $price, int $users, int $stores, int $items, int $invoices, int $storage, mixed $now): array
    {
        return [
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => $price,
            'price_yearly' => '0.00',
            'max_users' => $users,
            'max_stores' => $stores,
            'max_items' => $items,
            'max_invoices_per_month' => $invoices,
            'max_storage_mb' => $storage,
            'features' => '{}',
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function migrateUp(): void
    {
        $migration = $this->migration();
        if (! method_exists($migration, 'up')) {
            $this->fail('The ENTI-1.2 migration has no up().');
        }

        $migration->up();
    }

    private function migrateDown(): void
    {
        $migration = $this->migration();
        if (! method_exists($migration, 'down')) {
            $this->fail('The ENTI-1.2 migration has no down().');
        }

        $migration->down();
    }

    private function migration(): object
    {
        $migration = require database_path(self::MIGRATION);
        if (! is_object($migration)) {
            $this->fail('The ENTI-1.2 migration file must return a migration instance.');
        }

        return $migration;
    }

    private function ensureMigrated(): void
    {
        if (! Schema::hasColumn('plans', 'max_warehouses')) {
            $this->migrateUp();
        }
    }
}
