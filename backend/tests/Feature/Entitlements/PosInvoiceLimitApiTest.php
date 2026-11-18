<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PLAN-13: GET /api/v1/pos/invoice-limit tells the cashier where the shop stands against its
 * monthly sales-invoice cap, before the sale (the checkout itself still answers 403).
 */
#[Group('entitlements')]
final class PosInvoiceLimitApiTest extends TenantTestCase
{
    public function test_it_reports_usage_remaining_and_the_reset_date_of_a_capped_plan(): void
    {
        $tenant = $this->tenantWithCap(150);
        $this->seedInvoices($tenant, 120);

        $this->getJson('/api/v1/pos/invoice-limit', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.limit', 150)
            ->assertJsonPath('data.used', 120)
            ->assertJsonPath('data.remaining', 30)
            ->assertJsonPath('data.percent', 80)
            ->assertJsonPath('data.reached', false)
            ->assertJsonStructure(['data' => ['resets_at']]);
    }

    public function test_it_flags_the_cap_as_reached(): void
    {
        $tenant = $this->tenantWithCap(5);
        $this->seedInvoices($tenant, 5);

        $this->getJson('/api/v1/pos/invoice-limit', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.remaining', 0)
            ->assertJsonPath('data.percent', 100)
            ->assertJsonPath('data.reached', true);
    }

    public function test_an_uncapped_plan_has_no_limit(): void
    {
        $tenant = $this->tenantWithCap(null);
        $this->seedInvoices($tenant, 3);

        $this->getJson('/api/v1/pos/invoice-limit', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.limit', null)
            ->assertJsonPath('data.remaining', null)
            ->assertJsonPath('data.reached', false);
    }

    public function test_a_planless_legacy_tenant_is_not_capped(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/pos/invoice-limit', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.limit', null)
            ->assertJsonPath('data.reached', false);
    }

    public function test_it_requires_authentication(): void
    {
        $tenant = $this->tenantWithCap(10);

        $this->getJson('/api/v1/pos/invoice-limit', $this->tenantGuestHeaders($tenant))->assertStatus(401);
    }

    public function test_a_user_without_pos_access_is_forbidden(): void
    {
        $tenant = $this->tenantWithCap(10);
        $user = $this->createTenantUser($tenant);

        $this->getJson('/api/v1/pos/invoice-limit', $this->tenantHeaders($tenant, $user))->assertStatus(403);
    }

    public function test_the_count_is_scoped_to_the_resolved_tenant(): void
    {
        $capped = $this->tenantWithCap(10);
        $other = $this->tenantWithCap(10);
        $this->seedInvoices($other, 9);

        $this->getJson('/api/v1/pos/invoice-limit', $this->tenantHeaders($capped))
            ->assertOk()
            ->assertJsonPath('data.used', 0)
            ->assertJsonPath('data.reached', false);
    }

    private function tenantWithCap(?int $cap): Tenant
    {
        $slug = 'pil-'.Str::lower(Str::random(10));
        $plan = Plan::query()->create([
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
            'max_invoices_per_month' => $cap,
            'max_storage_mb' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return $this->createTenant(['plan_id' => $plan->id]);
    }

    private function seedInvoices(Tenant $tenant, int $count): void
    {
        $this->inTenant($tenant, function () use ($tenant, $count): void {
            $customerId = DB::table('customers')->insertGetId(['name' => 'عميل سابق', 'created_at' => now(), 'updated_at' => now()]);
            $rows = [];
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'invoice_number' => 'SEED-'.Str::random(12),
                    'customer_id' => $customerId,
                    'user_id' => $this->tenantAdmin($tenant)->getKey(),
                    'store_id' => $this->tenantStore($tenant)->getKey(),
                    'invoice_date' => now()->toDateString(),
                    'payment_type' => 'cash',
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'subtotal' => '1.000',
                    'net_total' => '1.000',
                    'paid_amount' => '1.000',
                    'remaining_amount' => '0.000',
                    'total_cost' => '0.000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('invoices')->insert($rows);
        });
    }
}
