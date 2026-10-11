<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2 3F: tenant roles/permissions are pinned to the tenant `web` guard in PermissionsSeeder and
 * TenantProvisionerService, so provisioning from a central-authenticated request (default guard
 * `central`, set by AuthenticateCentral) never stamps tenant roles with the operator guard.
 * Replaces the Auth::shouldUse('web') band-aid that lived in SuperAdminApiController::storeTenant.
 */
final class TenantProvisioningGuardPinningTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    public function test_permissions_seeder_pins_the_web_guard_whatever_the_default_guard(): void
    {
        $tenant = $this->createTenant();

        $guards = $this->inTenant($tenant, function (): array {
            Auth::shouldUse('central');
            Role::query()->delete();
            Permission::query()->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            (new PermissionsSeeder)->run();

            return [
                'roles' => Role::query()->distinct()->pluck('guard_name')->all(),
                'permissions' => Permission::query()->distinct()->pluck('guard_name')->all(),
                'admin_permissions' => Role::findByName('admin', 'web')->permissions()->count(),
            ];
        });

        $this->assertSame(['web'], $guards['roles']);
        $this->assertSame(['web'], $guards['permissions']);
        $this->assertSame(count(PermissionsSeeder::PERMISSIONS), $guards['admin_permissions']);
    }

    public function test_super_admin_provisions_a_tenant_whose_roles_are_on_the_web_guard(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Guard pinning plan',
            'slug' => 'guard-pin-'.Str::lower(Str::random(6)),
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 5,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 1000,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
        ]);

        $slug = 'guard-pin-'.Str::lower(Str::random(6));

        try {
            $this->postJson('/api/v1/super-admin/tenants', [
                'name' => 'Guard pinning store',
                'slug' => $slug,
                'email' => $slug.'@guard.test',
                'phone' => '01000007093',
                'password' => 'secret1234',
                'plan_id' => $plan->id,
                'trial_days' => 14,
            ], $this->centralHeaders($this->centralSuperAdmin()))
                ->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertFalse(tenancy()->initialized);
            $tenant = Tenant::query()->findOrFail($slug);

            $state = $this->inTenant($tenant, static function () use ($slug): array {
                $user = User::query()->where('email', $slug.'@guard.test')->firstOrFail();

                return [
                    'role_guards' => Role::query()->distinct()->pluck('guard_name')->all(),
                    'permission_guards' => Permission::query()->distinct()->pluck('guard_name')->all(),
                    'is_admin' => $user->hasRole('admin', 'web'),
                    'can_manage_settings' => $user->hasPermissionTo('settings.manage', 'web'),
                ];
            });

            $this->assertSame(['web'], $state['role_guards']);
            $this->assertSame(['web'], $state['permission_guards']);
            $this->assertTrue($state['is_admin']);
            $this->assertTrue($state['can_manage_settings']);
        } finally {
            $this->dropProvisionedTenant($slug);
        }
    }

    /** Remove a tenant created through the API (not by the harness): database, storage, rows. */
    private function dropProvisionedTenant(string $id): void
    {
        $this->endTenancy();

        $tenant = Tenant::query()->find($id);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $manager = $tenant->database()->manager();
        $database = (string) $tenant->database()->getName();

        if ($manager->databaseExists($database)) {
            gc_collect_cycles();
            $manager->deleteDatabase($tenant);
        }

        $storage = storage_path().'/'.config('tenancy.filesystem.suffix_base', 'tenant').$id;
        if (is_dir($storage)) {
            File::deleteDirectory($storage);
        }
    }
}
