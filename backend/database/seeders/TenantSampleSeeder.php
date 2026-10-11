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

/**
 * Sample tenant for local / demo databases, idempotent.
 *
 * Same identity as scripts/local/provision-local-tenant.php: the tenant id and slug are
 * SEED_DEMO_TENANT_SLUG (default "demo"), its domains are "<slug>.<tenancy.central_domain>"
 * (added by the provisioner) and "<slug>.localhost". A tenant already holding that id or
 * slug (e.g. created by scripts/local/setup-local.ps1) is left untouched.
 */
class TenantSampleSeeder extends Seeder
{
    public const DEFAULT_SLUG = 'demo';

    private const DEMO_ADMIN_EMAIL = 'admin@demo.com';

    /** Same demo admin phone as scripts/local/setup-local.ps1 and the e2e suites (e2e/utils/e2e-user.js). */
    public const DEMO_ADMIN_PHONE = '01000000201';

    public function run(): void
    {
        // OPS-2: provisioning is a queued job. With QUEUE_CONNECTION=database (local .env)
        // and no worker the demo tenant would stay `pending`; seeding provisions it inline.
        config(['tenancy.provisioning.queue_connection' => 'sync']);

        $slug = self::slug();

        $existing = Tenant::query()->whereKey($slug)->orWhere('slug', $slug)->first();
        if ($existing !== null) {
            $this->command->info(__('console.seed.tenant_exists', ['tenant' => $existing->getTenantKey()]));

            return;
        }

        $plan = Plan::where('slug', 'enterprise')->first() ?? Plan::firstOrFail();
        $provisioner = app(TenantProvisionerService::class);

        $envPassword = (string) Env::get('SEED_DEMO_TENANT_PASSWORD', '');
        $passwordFromEnv = $envPassword !== '';
        $plainPassword = $passwordFromEnv ? $envPassword : Str::password(16);

        $dto = new CreateTenantDTO(
            name: 'مؤسسة تجارة وتوزيع البضائع',
            slug: $slug,
            email: self::DEMO_ADMIN_EMAIL,
            phone: self::DEMO_ADMIN_PHONE,
            password: $plainPassword,
            planId: $plan->id,
            trialDays: 30,
            customDomain: $slug.'.localhost'
        );

        $tenant = $provisioner->provision($dto);

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

    /** Tenant id/slug: SEED_DEMO_TENANT_SLUG (slugified) or "demo". */
    public static function slug(): string
    {
        // Seed-time operator value: process environment (Env::get), like SEED_DEMO_TENANT_PASSWORD.
        $slug = Str::slug((string) Env::get('SEED_DEMO_TENANT_SLUG', ''));

        return $slug !== '' ? $slug : self::DEFAULT_SLUG;
    }
}
