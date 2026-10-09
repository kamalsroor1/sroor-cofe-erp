<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    private const DEFAULT_SUPER_ADMIN_PHONE = '01000000001';

    private const DEFAULT_SUPER_ADMIN_EMAIL = 'superadmin@sroor.test';

    public function run(): void
    {
        // 1. Roles & permissions matrix and SaaS plans
        $this->call(CentralPermissionsSeeder::class);
        $this->call(PlansAndFeaturesSeeder::class);
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // 2. Platform super admin. Identity and password come from the environment;
        //    an existing user is never modified (password included).
        $this->seedSuperAdmin()->syncRoles([$superAdminRole, $adminRole]);

        // 3. Base demo/sample tenant (isolated store & database)
        $this->call(TenantSampleSeeder::class);
    }

    private function seedSuperAdmin(): User
    {
        // Operator-supplied seed-time values: read straight from the process environment
        // (Env::get, not config()) so `SEED_*=... php artisan db:seed` works even with a cached config.
        $phone = (string) (Env::get('SEED_SUPER_ADMIN_PHONE') ?: self::DEFAULT_SUPER_ADMIN_PHONE);
        $email = (string) (Env::get('SEED_SUPER_ADMIN_EMAIL') ?: self::DEFAULT_SUPER_ADMIN_EMAIL);
        $envPassword = (string) Env::get('SEED_SUPER_ADMIN_PASSWORD', '');
        $passwordFromEnv = $envPassword !== '';
        $plainPassword = $passwordFromEnv ? $envPassword : Str::password(16);

        $user = User::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Super Admin',
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'is_active' => true,
            ]
        );

        if ($user->wasRecentlyCreated && $this->command !== null) {
            if ($passwordFromEnv) {
                $this->command->warn(__('console.seed.password_from_env', ['user' => $phone]));
            } else {
                $this->command->warn(__('console.seed.generated_password', ['user' => $phone, 'password' => $plainPassword]));
            }
            $this->command->warn(__('console.seed.change_password_warning'));
        }

        return $user;
    }
}
