<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Enums\Billing\SubscriptionAddonStatus;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Plan;
use App\Models\StoreStock;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Models\TenantBrandProfile;
use App\Services\Entitlements\TenantStorageUsage;
use App\Services\Import\ImportSessionStore;
use Carbon\CarbonImmutable;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TenantTestCase;

/**
 * PLAN-ENF: the three plan-enforcement gaps of the audit.
 *  1. max_invoices_per_month (invoices_month) on every sales-invoice creator;
 *  2. max_storage_mb (storage_mb) on the tenant upload paths (logos, import files);
 *  3. the web CSV export of the activity log behind feature:audit.logs.
 */
#[Group('entitlements')]
final class PlanUsageLimitsApiTest extends TenantTestCase
{
    // ------------------------------------------------------- invoices / month

    public function test_a_trial_tenant_is_capped_at_300_invoices_a_month_on_post_invoices(): void
    {
        $tenant = $this->trialTenant();
        [$itemId, $customerId] = $this->saleFixture($tenant);
        $this->seedInvoices($tenant, 299);
        $headers = $this->tenantHeaders($tenant);

        $this->postJson('/api/v1/invoices', $this->salePayload($itemId, $customerId), $headers)->assertStatus(201);

        $this->postJson('/api/v1/invoices', $this->salePayload($itemId, $customerId), $headers)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'invoices_month')
            ->assertJsonPath('details.max', 300)
            ->assertJsonPath('message', __('subscription.errors.limit_reached_invoices_month', ['max' => '300']));

        // The refused sale wrote nothing: 300 invoices, stock moved once (10 - 1).
        $this->inTenant($tenant, function () use ($itemId): void {
            $this->assertSame(300, DB::table('invoices')->count());
            $this->assertSame('9.000', (string) StoreStock::query()->where('item_id', $itemId)->value('quantity'));
        });
    }

    public function test_the_cap_applies_to_pos_checkout_too(): void
    {
        $tenant = $this->trialTenant();
        [$itemId, $customerId] = $this->saleFixture($tenant);
        $this->seedInvoices($tenant, 300);

        $this->postJson('/api/v1/pos/checkout', $this->salePayload($itemId, $customerId), $this->tenantHeaders($tenant))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'invoices_month')
            ->assertJsonPath('details.max', 300);

        $this->assertSame(300, $this->inTenant($tenant, fn (): int => DB::table('invoices')->count()));
    }

    public function test_an_offline_replay_of_a_saved_invoice_still_replays_at_the_cap(): void
    {
        $tenant = $this->trialTenant();
        [$itemId, $customerId] = $this->saleFixture($tenant);
        $this->seedInvoices($tenant, 299);
        $headers = $this->tenantHeaders($tenant);
        $payload = $this->salePayload($itemId, $customerId) + ['client_uuid' => (string) Str::uuid()];

        $id = $this->postJson('/api/v1/pos/checkout', $payload, $headers)->assertStatus(201)->json('data.id');

        $this->assertSame($id, $this->postJson('/api/v1/pos/checkout', $payload, $this->tenantHeaders($tenant))->assertSuccessful()->json('data.id'));
        $this->assertSame(300, $this->inTenant($tenant, fn (): int => DB::table('invoices')->count()));
    }

    public function test_cancelled_and_deleted_invoices_still_count(): void
    {
        $tenant = $this->trialTenant();
        [$itemId, $customerId] = $this->saleFixture($tenant);
        $this->seedInvoices($tenant, 200, ['status' => 'cancelled']);
        $this->seedInvoices($tenant, 100, ['deleted_at' => now()]);

        $this->postJson('/api/v1/invoices', $this->salePayload($itemId, $customerId), $this->tenantHeaders($tenant))
            ->assertStatus(403)
            ->assertJsonPath('details.limit', 'invoices_month');
    }

    public function test_the_count_starts_again_with_the_next_business_month(): void
    {
        // 23:00 on Nov 30 in Cairo (UTC+2), still November on the tenant clock.
        $this->travelTo(CarbonImmutable::parse('2026-11-30 23:00:00', 'Africa/Cairo'));
        $tenant = $this->trialTenant();
        [$itemId, $customerId] = $this->saleFixture($tenant);
        $this->seedInvoices($tenant, 300);

        $this->postJson('/api/v1/invoices', $this->salePayload($itemId, $customerId), $this->tenantHeaders($tenant))
            ->assertStatus(403);

        // 00:30 on Dec 1 in Cairo: a new month, last month's 300 no longer count.
        $this->travelTo(CarbonImmutable::parse('2026-12-01 00:30:00', 'Africa/Cairo'));

        $this->postJson('/api/v1/invoices', $this->salePayload($itemId, $customerId), $this->tenantHeaders($tenant))
            ->assertStatus(201);

        $usage = collect($this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->assertOk()->json('data.usage'))
            ->keyBy('resource');
        $this->assertSame(1, $usage['invoices_month']['used']);
        $this->assertSame(300, $usage['invoices_month']['limit']);
        $this->assertSame('count', $usage['invoices_month']['unit']);
        $this->assertSame(
            CarbonImmutable::parse('2027-01-01 00:00:00', 'Africa/Cairo')->getTimestamp(),
            CarbonImmutable::parse((string) $usage['invoices_month']['resets_at'])->getTimestamp(),
        );
    }

    public function test_a_paid_plan_without_an_invoice_cap_is_unlimited(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan(['max_invoices_per_month' => null])->id]);
        [$itemId, $customerId] = $this->saleFixture($tenant);
        $this->seedInvoices($tenant, 300);

        $this->postJson('/api/v1/invoices', $this->salePayload($itemId, $customerId), $this->tenantHeaders($tenant))
            ->assertStatus(201);

        $usage = collect($this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->json('data.usage'))->keyBy('resource');
        $this->assertArrayNotHasKey('invoices_month', $usage->all(), 'An unlimited resource has no usage row.');
    }

    public function test_a_planless_legacy_tenant_is_not_capped(): void
    {
        $tenant = $this->createTenant();
        [$itemId, $customerId] = $this->saleFixture($tenant);
        $this->seedInvoices($tenant, 300);

        $this->postJson('/api/v1/invoices', $this->salePayload($itemId, $customerId), $this->tenantHeaders($tenant))
            ->assertStatus(201);
    }

    public function test_another_tenants_invoices_never_count(): void
    {
        $plan = $this->plan(['max_invoices_per_month' => 5]);
        $full = $this->createTenant(['plan_id' => $plan->id]);
        $fresh = $this->createTenant(['plan_id' => $plan->id]);
        $this->seedInvoices($full, 5);
        [$itemId, $customerId] = $this->saleFixture($fresh);

        $this->postJson('/api/v1/invoices', $this->salePayload($itemId, $customerId), $this->tenantHeaders($fresh))
            ->assertStatus(201);
    }

    // ------------------------------------------------------------- storage

    public function test_a_logo_upload_is_refused_when_it_would_pass_the_storage_limit(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan(['max_storage_mb' => 1])->id]);
        $this->fillStorage($tenant, TenantStorageUsage::BYTES_PER_MB - 10);

        $this->post('/api/v1/settings/branding/logo/light', ['file' => $this->logo()], $this->tenantHeaders($tenant))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'storage_mb')
            ->assertJsonPath('details.max', 1)
            ->assertJsonPath('message', __('subscription.errors.limit_reached_storage_mb', ['max' => '1']));

        // Nothing stored: only the filler row.
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Media::query()->where('collection_name', 'logo_light')->count()));

        // The usage row is shown in MB (rounded up).
        $usage = collect($this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->json('data.usage'))->keyBy('resource');
        $this->assertSame(1, $usage['storage_mb']['used']);
        $this->assertSame(1, $usage['storage_mb']['limit']);
        $this->assertSame('mb', $usage['storage_mb']['unit']);
    }

    public function test_the_storage_add_on_raises_the_limit(): void
    {
        $plan = $this->plan(['max_storage_mb' => 1]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $this->fillStorage($tenant, TenantStorageUsage::BYTES_PER_MB - 10);
        $headers = $this->tenantHeaders($tenant);

        $this->post('/api/v1/settings/branding/logo/light', ['file' => $this->logo()], $headers)->assertStatus(403);

        $addon = Addon::query()->create([
            'key' => 'addon.storage_test', 'name_key' => 'billing.limit_resources.storage_mb', 'type' => 'recurring',
            'bundled_limits' => ['storage_mb' => 10240], 'unit_price' => '99.000', 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->getKey(), 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'status' => 'active',
            'amount' => '449.000', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->getKey(), 'addon_id' => $addon->getKey(), 'quantity' => 1, 'unit_price' => '99.000',
            'billing_cycle' => 'monthly', 'status' => SubscriptionAddonStatus::Active, 'starts_at' => now()->subDay(),
        ]);

        $this->post('/api/v1/settings/branding/logo/light', ['file' => $this->logo()], $this->tenantHeaders($tenant))->assertOk();

        $usage = collect($this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->json('data.usage'))->keyBy('resource');
        $this->assertSame(10241, $usage['storage_mb']['limit']);
    }

    public function test_deleting_a_logo_frees_its_space(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan(['max_storage_mb' => 1])->id]);
        $headers = $this->tenantHeaders($tenant);

        $this->post('/api/v1/settings/branding/logo/light', ['file' => $this->logo()], $headers)->assertOk();
        $logoBytes = $this->inTenant($tenant, fn (): int => (int) Media::query()->where('collection_name', 'logo_light')->sole()->size);
        // Room left: 10 bytes, less than a second logo of the same size.
        $this->fillStorage($tenant, TenantStorageUsage::BYTES_PER_MB - $logoBytes - 10);

        $this->post('/api/v1/settings/branding/logo/dark', ['file' => $this->logo()], $this->tenantHeaders($tenant))
            ->assertStatus(403)
            ->assertJsonPath('details.limit', 'storage_mb');

        // Replacing the light logo with one of the same size does not grow the usage: allowed.
        $this->post('/api/v1/settings/branding/logo/light', ['file' => $this->logo()], $this->tenantHeaders($tenant))->assertOk();

        $this->deleteJson('/api/v1/settings/branding/logo/light', [], $this->tenantHeaders($tenant))->assertOk();

        $this->post('/api/v1/settings/branding/logo/dark', ['file' => $this->logo()], $this->tenantHeaders($tenant))->assertOk();
    }

    public function test_the_legacy_settings_form_logo_is_capped_too(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan(['max_storage_mb' => 1])->id]);
        $this->fillStorage($tenant, TenantStorageUsage::BYTES_PER_MB - 10);

        $this->post('/api/v1/settings', ['company_name' => 'محمصة الحد', 'logo_light_file' => $this->logo()], $this->tenantHeaders($tenant))
            ->assertStatus(403)
            ->assertJsonPath('details.limit', 'storage_mb');

        // Nothing saved: neither the logo nor the settings of the same request.
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Media::query()->where('collection_name', 'logo_light')->count()));
        $this->assertNotSame('محمصة الحد', $this->inTenant($tenant, fn (): mixed => DB::table('settings')->where('key', 'company_name')->value('value')));
    }

    public function test_an_import_file_counts_only_while_it_is_stored(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan(['max_storage_mb' => 1])->id]);
        $fillerId = $this->fillStorage($tenant, TenantStorageUsage::BYTES_PER_MB - 10);
        $headers = $this->tenantHeaders($tenant);

        $this->post('/api/v1/imports/categories/preview', ['file' => $this->categoriesCsv()], $headers)
            ->assertStatus(403)
            ->assertJsonPath('details.limit', 'storage_mb');
        $this->assertSame([], $this->inTenant($tenant, fn (): array => Storage::disk('local')->files('imports')));

        $this->inTenant($tenant, fn () => DB::table('media')->where('id', $fillerId)->delete());

        $token = (string) $this->post('/api/v1/imports/categories/preview', ['file' => $this->categoriesCsv()], $this->tenantHeaders($tenant))
            ->assertOk()->json('data.token');
        $this->assertGreaterThan(0, $this->inTenant($tenant, fn (): int => app(TenantStorageUsage::class)->usedBytes()));

        $this->postJson('/api/v1/imports/categories/commit', ['token' => $token, 'skip_invalid' => false], $this->tenantHeaders($tenant))
            ->assertOk();
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => app(TenantStorageUsage::class)->usedBytes()));
    }

    public function test_an_abandoned_import_file_stops_counting_when_its_session_expires(): void
    {
        $tenant = $this->createTenant(['plan_id' => $this->plan(['max_storage_mb' => 1])->id]);

        $this->post('/api/v1/imports/categories/preview', ['file' => $this->categoriesCsv()], $this->tenantHeaders($tenant))->assertOk();

        $this->inTenant($tenant, function (): void {
            $this->assertGreaterThan(0, app(TenantStorageUsage::class)->usedBytes());

            // Session TTL (1 h) passed: the token is dead, the file only waits for the 24 h sweep.
            $this->travel(ImportSessionStore::TTL_SECONDS + 60)->seconds();

            $this->assertCount(1, Storage::disk('local')->files('imports'));
            $this->assertSame(0, app(TenantStorageUsage::class)->usedBytes());
        });
    }

    public function test_the_monthly_count_has_a_created_at_index(): void
    {
        $tenant = $this->createTenant();

        $this->assertTrue($this->inTenant($tenant, fn (): bool => Schema::hasIndex('invoices', 'invoices_created_at_idx')));
    }

    public function test_storage_is_not_capped_for_a_planless_tenant(): void
    {
        $tenant = $this->createTenant();
        $this->fillStorage($tenant, TenantStorageUsage::BYTES_PER_MB * 100000);

        $this->post('/api/v1/settings/branding/logo/light', ['file' => $this->logo()], $this->tenantHeaders($tenant))->assertOk();
    }

    // ------------------------------------------------------- audit CSV (web)

    public function test_the_web_activity_log_export_needs_the_audit_logs_feature(): void
    {
        $without = $this->createTenant(['plan_id' => $this->plan(['features' => ['audit.logs' => false] + array_fill_keys(FeatureCatalog::keys(), true)])->id]);
        $withPlan = $this->createTenant(['plan_id' => $this->plan()->id]);

        $this->actingAs($this->tenantAdmin($without), 'web')
            ->get($this->tenantUrl($without, '/activity-logs/export-csv'))
            ->assertForbidden();

        $this->actingAs($this->tenantAdmin($withPlan), 'web')
            ->get($this->tenantUrl($withPlan, '/activity-logs/export-csv'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_the_control_bundle_unlocks_the_web_activity_log_export(): void
    {
        $plan = $this->plan(['features' => ['audit.logs' => false] + array_fill_keys(FeatureCatalog::keys(), true)]);
        $tenant = $this->createTenant(['plan_id' => $plan->id]);

        $this->actingAs($this->tenantAdmin($tenant), 'web')
            ->get($this->tenantUrl($tenant, '/activity-logs/export-csv'))
            ->assertForbidden();

        $bundle = Addon::query()->create([
            'key' => 'bundle.control.csv_test', 'name_key' => 'plans.addons.control_bundle.name', 'type' => 'recurring',
            'feature_keys' => ['reports.advanced', 'audit.logs', 'purchases.reorder'],
            'unit_price' => '199.000', 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->getKey(), 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'status' => 'active',
            'amount' => '449.000', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->getKey(), 'addon_id' => $bundle->getKey(), 'quantity' => 1, 'unit_price' => '199.000',
            'billing_cycle' => 'monthly', 'status' => SubscriptionAddonStatus::Active, 'starts_at' => now()->subDay(),
        ]);

        $this->actingAs($this->tenantAdmin($tenant), 'web')
            ->get($this->tenantUrl($tenant, '/activity-logs/export-csv'))
            ->assertOk();
    }

    // ----------------------------------------------------------------- helpers

    private function trialTenant(): Tenant
    {
        return $this->createTenant([
            'plan_id' => $this->plan(['max_invoices_per_month' => 300, 'max_storage_mb' => 500])->id,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(10),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): Plan
    {
        $slug = 'use-'.Str::lower(Str::random(10));

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
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function saleFixture(Tenant $tenant): array
    {
        return $this->inTenant($tenant, function () use ($tenant): array {
            $item = Item::create([
                'name' => 'بن', 'code' => 'CAP-'.Str::random(6), 'unit' => 'كجم', 'is_weighted' => true,
                'cost_price' => '10.000', 'selling_price' => '20.000', 'current_stock' => '10.000', 'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $this->tenantStore($tenant)->getKey(), 'item_id' => $item->id, 'quantity' => '10.000']);
            $customer = Customer::create(['name' => 'عميل الحد', 'phone' => '01000007'.random_int(100, 999), 'current_balance' => '0.000', 'is_active' => true]);

            return [(int) $item->id, (int) $customer->id];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function salePayload(int $itemId, int $customerId): array
    {
        return [
            'customer_id' => $customerId,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $itemId, 'quantity' => '1.000', 'unit_price' => '20.000']],
        ];
    }

    /**
     * Invoices created "now" (the current business month), inserted directly.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function seedInvoices(Tenant $tenant, int $count, array $overrides = []): void
    {
        $this->inTenant($tenant, function () use ($tenant, $count, $overrides): void {
            $customerId = DB::table('customers')->insertGetId(['name' => 'عميل سابق', 'created_at' => now(), 'updated_at' => now()]);
            $rows = [];
            for ($i = 0; $i < $count; $i++) {
                $rows[] = array_merge([
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
                    'deleted_at' => null,
                ], $overrides);
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('invoices')->insert($chunk);
            }
        });
    }

    /** A `media` row of $bytes (no file): counts toward the tenant's usage. */
    private function fillStorage(Tenant $tenant, int $bytes): int
    {
        return $this->inTenant($tenant, function () use ($bytes): int {
            $profile = TenantBrandProfile::query()->createOrFirst(['singleton_key' => TenantBrandProfile::SINGLETON_KEY]);

            return (int) DB::table('media')->insertGetId([
                'model_type' => TenantBrandProfile::class,
                'model_id' => $profile->getKey(),
                'uuid' => (string) Str::uuid(),
                'collection_name' => 'filler',
                'name' => 'filler',
                'file_name' => 'filler.bin',
                'mime_type' => 'application/octet-stream',
                'disk' => 'public',
                'size' => $bytes,
                'manipulations' => '[]',
                'custom_properties' => '[]',
                'generated_conversions' => '[]',
                'responsive_images' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function logo(): UploadedFile
    {
        return UploadedFile::fake()->image('logo.png', 120, 120);
    }

    private function categoriesCsv(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cap').'.csv';
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [(string) __('import.columns.categories.name'), (string) __('import.columns.categories.icon')], ',', '"', '');
        fputcsv($handle, ['تصنيف '.Str::random(5), ''], ',', '"', '');
        fclose($handle);

        return new UploadedFile($path, 'categories.csv', null, null, true);
    }
}
