<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\DTOs\CreateTenantDTO;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\TenantProvisionerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Env;
use Illuminate\Support\Str;

class TenantSampleSeeder extends Seeder
{
    private const DEMO_ADMIN_EMAIL = 'admin@demo.com';

    public function run(): void
    {
        $existing = Tenant::find('tenant_sroor');
        if ($existing) {
            $this->command->info(__('console.seed.tenant_exists', ['tenant' => 'tenant_sroor']));

            return;
        }

        $plan = Plan::where('slug', 'enterprise')->first() ?? Plan::firstOrFail();
        $provisioner = app(TenantProvisionerService::class);

        $envPassword = (string) Env::get('SEED_DEMO_TENANT_PASSWORD', '');
        $passwordFromEnv = $envPassword !== '';
        $plainPassword = $passwordFromEnv ? $envPassword : Str::password(16);

        $dto = new CreateTenantDTO(
            name: 'مؤسسة تجارة وتوزيع البضائع',
            slug: 'demo',
            email: self::DEMO_ADMIN_EMAIL,
            phone: '01000000099',
            password: $plainPassword,
            planId: $plan->id,
            trialDays: 30,
            customDomain: 'sroor.localhost'
        );

        $tenant = $provisioner->provision($dto);

        // Also add sroor.makhzani.test domain
        $tenant->domains()->firstOrCreate(['domain' => 'sroor.makhzani.test']);

        if ($this->command === null) {
            return;
        }

        $this->command->info(__('console.seed.tenant_provisioned', ['tenant' => $tenant->name]));
        $this->command->info(__('console.seed.tenant_domains', ['domains' => $tenant->domains->pluck('domain')->implode(', ')]));

        if ($passwordFromEnv) {
            $this->command->warn(__('console.seed.password_from_env', ['user' => self::DEMO_ADMIN_EMAIL]));
        } else {
            $this->command->warn(__('console.seed.generated_password', ['user' => self::DEMO_ADMIN_EMAIL, 'password' => $plainPassword]));
        }
        $this->command->warn(__('console.seed.change_password_warning'));
    }
}
