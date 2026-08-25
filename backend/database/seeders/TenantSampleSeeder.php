<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;
use App\Models\Tenant;
use App\DTOs\CreateTenantDTO;
use App\Services\TenantProvisionerService;

class TenantSampleSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Tenant::find('tenant_sroor');
        if ($existing) {
            $this->command->info("Tenant 'tenant_sroor' already exists.");
            return;
        }

        $plan = Plan::where('slug', 'enterprise')->first() ?? Plan::first();
        $provisioner = app(TenantProvisionerService::class);

        $dto = new CreateTenantDTO(
            name: 'مؤسسة تجارة وتوزيع البضائع',
            slug: 'demo',
            email: 'admin@demo.com',
            phone: '01012316954',
            password: 'password',
            planId: $plan->id,
            trialDays: 30,
            customDomain: 'sroor.localhost'
        );

        $tenant = $provisioner->provision($dto);

        // Also add sroor.makhzani.test domain
        $tenant->domains()->firstOrCreate(['domain' => 'sroor.makhzani.test']);

        $this->command->info("✅ Successfully provisioned tenant: {$tenant->name}");
        $this->command->info("🌐 Domains: " . $tenant->domains->pluck('domain')->implode(', '));
    }
}
