<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\LimitResource;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Entitlements\TenantEntitlementService;
use App\Support\PartyLimitCounter;
use App\Support\TenantCache;
use Database\Seeders\Catalog\FeatureCatalog;
use Database\Seeders\Catalog\PlanCatalog;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * PLAN-11: the `customers` / `suppliers` plan limits (plans.max_customers / max_suppliers,
 * NULL = unlimited) are enforced by PlanEnforcer on every path that creates one: the customer and
 * supplier forms, the POS quick-add, the import wizard (whole batch) and a trash restore. The
 * shop's walk-in customer takes no slot. A refusal is 403 `subscription.limit_reached` (the
 * existing PlanLimitExceededException contract), never a partial write.
 */
#[Group('entitlements')]
final class PartyLimitsApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    // ----------------------------------------------------------------- catalog

    public function test_the_catalog_free_plan_caps_customers_and_suppliers_and_paid_plans_do_not(): void
    {
        $catalog = collect(PlanCatalog::all())->keyBy('slug');

        $this->assertSame(50, $catalog['free']['max_customers']);
        $this->assertSame(20, $catalog['free']['max_suppliers']);
        foreach (['basic', 'pro', 'enterprise'] as $paid) {
            $this->assertNull($catalog[$paid]['max_customers']);
            $this->assertNull($catalog[$paid]['max_suppliers']);
        }

        $this->seed(PlansAndFeaturesSeeder::class);
        $free = Plan::query()->where('slug', 'free')->sole();
        $this->assertSame(50, $free->max_customers);
        $this->assertSame(20, $free->max_suppliers);
        $this->assertNull(Plan::query()->where('slug', 'basic')->sole()->max_customers);

        $this->assertSame('max_customers', LimitResource::Customers->planColumn());
        $this->assertSame('max_suppliers', LimitResource::Suppliers->planColumn());
        $this->assertContains('max_customers', Plan::LIMIT_COLUMNS);
        $this->assertContains('max_suppliers', Plan::LIMIT_COLUMNS);
    }

    public function test_the_data_migration_fills_the_free_row_only_while_its_values_are_null(): void
    {
        $this->seed(PlansAndFeaturesSeeder::class);
        $free = Plan::query()->where('slug', 'free')->sole();
        $migration = require database_path('migrations/2026_11_18_210100_apply_free_plan_party_limits.php');

        // An existing install: the new columns are NULL on the free row.
        DB::table('plans')->where('id', $free->id)->update(['max_customers' => null, 'max_suppliers' => 75]);
        $migration->up();
        $free->refresh();
        $this->assertSame(50, $free->max_customers);
        $this->assertSame(75, $free->max_suppliers, 'An operator value is never overwritten.');
        $this->assertNull(Plan::query()->where('slug', 'pro')->sole()->max_customers);

        // down() clears only what it wrote.
        $migration->down();
        $free->refresh();
        $this->assertNull($free->max_customers);
        $this->assertSame(75, $free->max_suppliers);
    }

    // ------------------------------------------------------------- enforcement

    public function test_the_51st_customer_is_refused_on_the_free_plan(): void
    {
        $tenant = $this->tenantOn($this->freePlan());
        $headers = $this->tenantHeaders($tenant);
        $this->seedCustomers($tenant, 49);

        $this->postJson('/api/v1/customers', ['name' => 'العميل رقم 50'], $headers)->assertCreated();
        $this->postJson('/api/v1/customers', ['name' => 'العميل رقم 51'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'customers')
            ->assertJsonPath('details.max', 50)
            ->assertJsonPath('message', __('subscription.errors.limit_reached', [
                'resource' => __('subscription.limit_resources.customers'), 'max' => '50',
            ]));

        $this->assertSame(50, $this->inTenant($tenant, fn (): int => PartyLimitCounter::customers()));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Customer::query()->where('name', 'العميل رقم 51')->count()));
    }

    public function test_the_21st_supplier_is_refused_on_the_free_plan(): void
    {
        $tenant = $this->tenantOn($this->freePlan());
        $headers = $this->tenantHeaders($tenant);
        $this->seedSuppliers($tenant, 19);

        $this->postJson('/api/v1/suppliers', ['name' => 'المورد رقم 20'], $headers)->assertCreated();
        $this->postJson('/api/v1/suppliers', ['name' => 'المورد رقم 21'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'suppliers')
            ->assertJsonPath('details.max', 20);

        $this->assertSame(20, $this->inTenant($tenant, fn (): int => Supplier::query()->count()));
    }

    public function test_a_basic_plan_tenant_is_unlimited(): void
    {
        $this->seed(PlansAndFeaturesSeeder::class);
        $basic = Plan::query()->where('slug', 'basic')->sole();
        $tenant = $this->tenantOn($basic);
        $headers = $this->tenantHeaders($tenant);
        $this->seedCustomers($tenant, 60);
        $this->seedSuppliers($tenant, 30);

        $this->postJson('/api/v1/customers', ['name' => 'عميل بلا حد'], $headers)->assertCreated();
        $this->postJson('/api/v1/suppliers', ['name' => 'مورد بلا حد'], $headers)->assertCreated();
        $this->postJson('/api/v1/pos/quick-customer', ['name' => 'عميل سريع'], $headers)->assertCreated();
    }

    public function test_the_pos_quick_add_customer_is_capped_too(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_customers' => 1]));
        $headers = $this->tenantHeaders($tenant);

        $this->postJson('/api/v1/pos/quick-customer', ['name' => 'أول عميل', 'phone' => '01000000701'], $headers)->assertCreated();
        $this->postJson('/api/v1/pos/quick-customer', ['name' => 'تاني عميل', 'phone' => '01000000702'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'customers');

        $this->assertSame(1, $this->inTenant($tenant, fn (): int => Customer::query()->count()));
    }

    public function test_the_walk_in_customer_takes_no_slot_and_is_never_refused(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_customers' => 2]));
        $headers = $this->tenantHeaders($tenant);
        $this->seedCustomers($tenant, 2);

        // The customers limit is full, yet the walk-in customer is created on demand.
        $walkInId = $this->inTenant($tenant, fn (): int => (int) app(ResolveWalkInCustomerAction::class)->execute()->id);
        $this->assertSame(3, $this->inTenant($tenant, fn (): int => Customer::query()->count()));
        $this->assertSame(2, $this->inTenant($tenant, fn (): int => PartyLimitCounter::customers()));

        // Only ONE walk-in-looking row is free: a second one with the placeholder phone counts.
        $this->inTenant($tenant, fn () => Customer::query()->create([
            'name' => 'شبيه النقدي', 'phone' => ResolveWalkInCustomerAction::PLACEHOLDER_PHONE, 'current_balance' => '0.000', 'is_active' => true,
        ]));
        $this->assertSame(3, $this->inTenant($tenant, fn (): int => PartyLimitCounter::customers()));
        $this->inTenant($tenant, fn () => Customer::query()->where('name', 'شبيه النقدي')->forceDelete());

        // At the limit, a real customer is still refused; the walk-in row did not cost a slot.
        $this->postJson('/api/v1/customers', ['name' => 'عميل جديد'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('details.limit', 'customers');

        $usage = collect($this->getJson('/api/v1/billing/subscription', $headers)->assertOk()->json('data.usage'))->keyBy('resource');
        $this->assertSame(2, $usage['customers']['used']);

        // Restoring a trashed walk-in while the shop has none is never refused either.
        $this->inTenant($tenant, fn () => Customer::query()->findOrFail($walkInId)->delete());
        $this->postJson("/api/v1/trash/customers/{$walkInId}/restore", [], $headers)->assertOk();
        $this->assertSame(2, $this->inTenant($tenant, fn (): int => PartyLimitCounter::customers()));
    }

    public function test_an_import_over_the_limit_is_refused_as_a_whole(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_customers' => 3, 'max_suppliers' => 2]));
        $headers = $this->tenantHeaders($tenant);
        $this->seedCustomers($tenant, 1);

        // 3 new customers on top of 1 against a limit of 3: nothing is written.
        $token = $this->previewToken($headers, 'customers', ['زبون 1', 'زبون 2', 'زبون 3']);
        $this->postJson('/api/v1/imports/customers/commit', ['token' => $token, 'skip_invalid' => false], $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'customers')
            ->assertJsonPath('details.max', 3);
        $this->assertSame(1, $this->inTenant($tenant, fn (): int => Customer::query()->count()));

        // A batch that fits is written.
        $token = $this->previewToken($headers, 'customers', ['زبون 4', 'زبون 5']);
        $this->postJson('/api/v1/imports/customers/commit', ['token' => $token, 'skip_invalid' => false], $headers)
            ->assertOk()
            ->assertJsonPath('data.created', 2);
        $this->assertSame(3, $this->inTenant($tenant, fn (): int => Customer::query()->count()));

        // Suppliers: 3 rows against a limit of 2.
        $token = $this->previewToken($headers, 'suppliers', ['مورد 1', 'مورد 2', 'مورد 3']);
        $this->postJson('/api/v1/imports/suppliers/commit', ['token' => $token, 'skip_invalid' => false], $headers)
            ->assertStatus(403)
            ->assertJsonPath('details.limit', 'suppliers');
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Supplier::query()->count()));
    }

    public function test_a_trash_restore_cannot_bypass_the_limits(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_customers' => 1, 'max_suppliers' => 1]));
        $headers = $this->tenantHeaders($tenant);

        [$customerId, $supplierId] = $this->inTenant($tenant, function (): array {
            $customer = Customer::query()->create(['name' => 'محذوف', 'current_balance' => '0.000', 'is_active' => true]);
            $customer->delete();
            $supplier = Supplier::query()->create(['name' => 'مورد محذوف', 'current_balance' => '0.000', 'is_active' => true]);
            $supplier->delete();

            return [(int) $customer->id, (int) $supplier->id];
        });

        // The deleted rows freed their slots; new ones take them.
        $this->postJson('/api/v1/customers', ['name' => 'بديل'], $headers)->assertCreated();
        $this->postJson('/api/v1/suppliers', ['name' => 'مورد بديل'], $headers)->assertCreated();

        $this->postJson("/api/v1/trash/customers/{$customerId}/restore", [], $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'customers');
        $this->postJson("/api/v1/trash/suppliers/{$supplierId}/restore", [], $headers)
            ->assertStatus(403)
            ->assertJsonPath('details.limit', 'suppliers');

        $this->assertTrue($this->inTenant($tenant, fn (): bool => Customer::onlyTrashed()->whereKey($customerId)->exists()));
        $this->assertTrue($this->inTenant($tenant, fn (): bool => Supplier::onlyTrashed()->whereKey($supplierId)->exists()));
    }

    public function test_a_tenant_above_the_limit_after_a_downgrade_keeps_its_data_and_can_still_edit(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_customers' => 1]));
        $headers = $this->tenantHeaders($tenant);
        $ids = $this->seedCustomers($tenant, 3);

        $this->getJson('/api/v1/customers', $headers)->assertOk();
        $this->putJson('/api/v1/customers/'.$ids[0], ['name' => 'اسم معدل'], $headers)->assertOk();
        $this->postJson('/api/v1/customers', ['name' => 'جديد'], $headers)->assertStatus(403);
        $this->assertSame(3, $this->inTenant($tenant, fn (): int => Customer::query()->count()));
    }

    public function test_counts_are_isolated_per_tenant(): void
    {
        $plan = $this->plan(['max_customers' => 2, 'max_suppliers' => 1]);
        $full = $this->tenantOn($plan);
        $other = $this->tenantOn($plan);
        $this->seedCustomers($full, 2);
        $this->seedSuppliers($full, 1);

        $this->postJson('/api/v1/customers', ['name' => 'ممتلئ'], $this->tenantHeaders($full))->assertStatus(403);
        $this->postJson('/api/v1/suppliers', ['name' => 'ممتلئ'], $this->tenantHeaders($full))->assertStatus(403);

        // The other shop on the same plan counts only its own rows.
        $this->postJson('/api/v1/customers', ['name' => 'محل تاني'], $this->tenantHeaders($other))->assertCreated();
        $this->postJson('/api/v1/suppliers', ['name' => 'محل تاني'], $this->tenantHeaders($other))->assertCreated();

        $usage = collect($this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($other))->json('data.usage'))->keyBy('resource');
        $this->assertSame(1, $usage['customers']['used']);
        $this->assertSame(1, $usage['suppliers']['used']);
    }

    public function test_the_authorization_check_runs_before_the_limit(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_customers' => 1]));
        $cashier = $this->createTenantUser($tenant);

        $this->postJson('/api/v1/customers', ['name' => 'بدون صلاحية'], $this->tenantHeaders($tenant, $cashier))->assertStatus(403)
            ->assertJsonMissing(['error_code' => 'subscription.limit_reached']);
        $this->postJson('/api/v1/customers', ['name' => 'ضيف'], $this->tenantGuestHeaders($tenant))->assertStatus(401);
        $this->postJson('/api/v1/customers', [], $this->tenantHeaders($tenant))->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    // ------------------------------------------------------------------- usage

    public function test_the_usage_endpoint_lists_the_new_resources(): void
    {
        $tenant = $this->tenantOn($this->freePlan());
        $headers = $this->tenantHeaders($tenant);
        $this->seedCustomers($tenant, 40);
        $this->seedSuppliers($tenant, 5);
        $this->inTenant($tenant, fn () => app(ResolveWalkInCustomerAction::class)->execute());

        $usage = collect($this->getJson('/api/v1/billing/subscription', $headers)->assertOk()->json('data.usage'))->keyBy('resource');

        $this->assertSame([
            'resource' => 'customers', 'label' => __('billing.limit_resources.customers'), 'unit' => 'count',
            'used' => 40, 'limit' => 50, 'percent' => 80, 'over_limit' => false, 'resets_at' => null,
            'overridden' => false, 'override_expires_at' => null,
        ], $usage['customers']);
        $this->assertSame(5, $usage['suppliers']['used']);
        $this->assertSame(20, $usage['suppliers']['limit']);
        $this->assertSame(25, $usage['suppliers']['percent']);
        $this->assertSame(__('billing.limit_resources.suppliers'), $usage['suppliers']['label']);
    }

    public function test_an_entitlements_cache_entry_without_the_new_limits_is_resolved_again(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_customers' => 7]));
        $id = (string) $tenant->getTenantKey();
        $key = TenantCache::keyFor($id, TenantEntitlementService::CACHE_NAMESPACE.':'.TenantCache::versionFor($id, TenantEntitlementService::CACHE_NAMESPACE));

        // An entry cached by code from before PLAN-11 (no customers / suppliers key).
        $stale = app(TenantEntitlementService::class)->resolve($id)->toArray();
        unset($stale['limits']['customers'], $stale['limits']['suppliers']);
        Cache::put($key, $stale, 3600);

        $this->assertSame(7, app(TenantEntitlementService::class)->limit($id, LimitResource::Customers));
        $this->assertNull(app(TenantEntitlementService::class)->limit($id, LimitResource::Suppliers));
        $this->assertArrayHasKey('customers', Cache::get($key)['limits']);
    }

    public function test_an_add_on_bundled_limit_raises_the_customers_limit(): void
    {
        $plan = $this->plan(['max_customers' => 2]);
        $tenant = $this->tenantOn($plan);
        $addon = Addon::query()->create([
            'key' => 'customers_pack_test', 'name_key' => 'billing.limit_resources.customers', 'type' => 'recurring',
            'bundled_limits' => ['customers' => 10], 'unit_price' => '50.000', 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->getKey(), 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'status' => 'active',
            'amount' => '100.000', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->getKey(), 'addon_id' => $addon->getKey(), 'quantity' => 1, 'unit_price' => '50.000',
            'billing_cycle' => 'monthly', 'status' => SubscriptionAddonStatus::Active, 'starts_at' => now()->subDay(),
        ]);

        $this->assertSame(12, app(TenantEntitlementService::class)->limit((string) $tenant->id, LimitResource::Customers));
    }

    // ------------------------------------------------------------- plan editor

    public function test_the_console_reads_writes_and_validates_the_new_limits(): void
    {
        $this->seedCentralPlatformRoles();
        $this->seed(PlansAndFeaturesSeeder::class);
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $plan = $this->plan(['max_customers' => 50]);
        $url = "/api/v1/super-admin/plans/{$plan->id}";

        $listed = collect($this->getJson('/api/v1/super-admin/plans', $headers)->assertOk()->json('data.plans'))->firstWhere('id', $plan->id);
        $this->assertSame(50, $listed['max_customers']);
        $this->assertNull($listed['max_suppliers']);

        $this->putJson($url, $this->planPayload(['max_customers' => 80, 'max_suppliers' => 30]), $headers)
            ->assertOk()
            ->assertJsonPath('data.max_customers', 80)
            ->assertJsonPath('data.max_suppliers', 30);

        // Omitted = untouched; null = unlimited.
        $this->putJson($url, $this->planPayload(), $headers)->assertOk();
        $this->assertSame(80, $plan->fresh()?->max_customers);
        $this->putJson($url, $this->planPayload(['max_customers' => null]), $headers)->assertOk();
        $this->assertNull($plan->fresh()?->max_customers);
        $this->assertSame(30, $plan->fresh()?->max_suppliers);

        // Whole number from 1 to 1,000,000,000.
        foreach ([0, -1, 'many', 1.5, 1000000001] as $bad) {
            $this->putJson($url, $this->planPayload(['max_customers' => $bad, 'max_suppliers' => $bad]), $headers)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['max_customers', 'max_suppliers']);
        }
        $this->assertSame(30, $plan->fresh()?->max_suppliers);

        // A plan created without them is unlimited.
        $created = $this->postJson('/api/v1/super-admin/plans', $this->planPayload(['name' => 'Custom '.Str::random(5)]), $headers)
            ->assertCreated()
            ->assertJsonPath('data.max_customers', null)
            ->assertJsonPath('data.max_suppliers', null);
        $this->assertNotNull($created->json('data.id'));

        // The public price list shows them.
        $this->seed(PlansAndFeaturesSeeder::class);
        $public = collect($this->getJson('/api/v1/public/plans')->assertOk()->json('data.plans'))->keyBy('code');
        $this->assertSame(50, $public['free']['limits']['customers'] ?? null);
        $this->assertSame(20, $public['free']['limits']['suppliers'] ?? null);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $tenantAttributes
     */
    private function tenantOn(Plan $plan, array $tenantAttributes = []): Tenant
    {
        return $this->createTenant(array_merge(['plan_id' => $plan->id, 'status' => 'active'], $tenantAttributes));
    }

    /** A plan with the catalog free plan's limits and features. */
    private function freePlan(): Plan
    {
        $free = collect(PlanCatalog::all())->firstWhere('slug', 'free');

        return $this->plan(array_intersect_key($free, array_flip([
            'max_users', 'max_stores', 'max_warehouses', 'max_vans', 'max_items', 'max_invoices_per_month',
            'max_storage_mb', 'max_customers', 'max_suppliers', 'features', 'is_free',
        ])) + ['price_monthly' => '0.000', 'price_yearly' => '0.000']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): Plan
    {
        $slug = 'party-'.Str::lower(Str::random(10));

        return Plan::query()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => array_fill_keys(FeatureCatalog::keys(), true),
            'max_users' => null,
            'max_stores' => null,
            'max_warehouses' => null,
            'max_vans' => null,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => null,
            'max_customers' => null,
            'max_suppliers' => null,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'باقة الحدود',
            'price_monthly' => '500',
            'price_yearly' => '5000',
            'max_users' => 5,
            'max_stores' => 2,
            'max_items' => null,
            'is_active' => true,
            'is_popular' => false,
            'features' => ['transfers.manage' => true],
        ], $overrides);
    }

    /**
     * @return list<int>
     */
    private function seedCustomers(Tenant $tenant, int $count): array
    {
        return $this->inTenant($tenant, function () use ($count): array {
            $ids = [];
            for ($i = 1; $i <= $count; $i++) {
                $ids[] = (int) Customer::query()->create([
                    'name' => 'عميل مسجل '.$i, 'phone' => sprintf('0101%07d', $i), 'current_balance' => '0.000', 'is_active' => true,
                ])->id;
            }

            return $ids;
        });
    }

    private function seedSuppliers(Tenant $tenant, int $count): void
    {
        $this->inTenant($tenant, function () use ($count): void {
            for ($i = 1; $i <= $count; $i++) {
                Supplier::query()->create(['name' => 'مورد مسجل '.$i, 'current_balance' => '0.000', 'is_active' => true]);
            }
        });
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<string>  $names
     */
    private function previewToken(array $headers, string $type, array $names): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pty').'.csv';
        $handle = fopen($path, 'w');
        $this->assertNotFalse($handle);
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [(string) __("import.columns.{$type}.name")], ',', '"', '');
        foreach ($names as $name) {
            fputcsv($handle, [$name], ',', '"', '');
        }
        fclose($handle);

        return (string) $this->post("/api/v1/imports/{$type}/preview", ['file' => new UploadedFile($path, $type.'.csv', null, null, true)], $headers)
            ->assertOk()
            ->json('data.token');
    }
}
