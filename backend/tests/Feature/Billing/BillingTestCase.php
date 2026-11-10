<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Branding\PlatformBranding;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * Shared fixtures of the manual SaaS billing tests (PRD-03 / PRD-04): plans, tenants on a plan,
 * a temporary `central_private` root (so no test writes under storage/app) and receipt files.
 * Payment numbers/addresses here are obvious dummies.
 */
abstract class BillingTestCase extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    protected string $centralRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralRoot = storage_path('framework/testing/billing-'.Str::lower(Str::random(10)));
        config([
            'filesystems.disks.central_private.root' => $this->centralRoot.'/central/private',
            'filesystems.disks.central_public.root' => $this->centralRoot.'/central/public',
        ]);
        Storage::forgetDisk(['central_private', 'central_public']);

        $this->beforeApplicationDestroyed(function (): void {
            File::deleteDirectory($this->centralRoot);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function plan(array $attributes = []): Plan
    {
        $slug = 'bill-'.Str::lower(Str::random(10));

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
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $tenantAttributes
     */
    protected function tenantOn(Plan $plan, array $tenantAttributes = []): Tenant
    {
        return $this->createTenant(array_merge(['plan_id' => $plan->id], $tenantAttributes));
    }

    protected function setTaxRate(string $rate): void
    {
        $this->endTenancy();
        app(PlatformBranding::class)->update(['billing_tax_rate' => $rate]);
    }

    protected function receipt(string $name = 'receipt.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 40, 40);
    }

    /**
     * Issue an invoice straight in the central DB (bypassing HTTP) for tests that start later in the flow.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function pendingInvoice(Tenant $tenant, array $overrides = []): BillingInvoice
    {
        $this->endTenancy();

        return BillingInvoice::query()->create(array_merge([
            'number' => 'SUB-TEST-'.Str::upper(Str::random(8)),
            'tenant_id' => $tenant->getKey(),
            'type' => 'service',
            'status' => 'pending',
            'lines' => [['type' => 'service', 'description' => 'Setup', 'quantity' => 1, 'unit_price' => '100.000', 'amount' => '100.000']],
            'subtotal' => '100.000',
            'discount' => '0.000',
            'tax_rate' => '0.000',
            'tax' => '0.000',
            'total' => '100.000',
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ], $overrides));
    }

    /**
     * @return array<string, string>
     */
    protected function multipart(array $headers): array
    {
        return $headers + ['Accept' => 'application/json'];
    }
}
