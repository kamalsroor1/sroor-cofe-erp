<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\CashShift;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\CentralPermissionsSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * QA-2 (phase-1-plan §4.14, migration decision Q10, CTO D5).
 *
 * spatie's checkPermissionTo() swallows PermissionDoesNotExist and answers false, so a
 * permission name that no seeder creates silently denies everyone except `admin`
 * (Gate::before). Every permission name the code checks must therefore exist:
 *  - tenant names in the tenant PermissionsSeeder matrix (what a provisioned tenant has);
 *  - `super_admin.*` names in the central CentralPermissionsSeeder.
 *
 * Sources: every `can:` / `permission:` / `role_or_permission:` route middleware
 * (Route::getRoutes(), so api.php, tenant.php, web.php and central.php), and every
 * ->can()/Gate::allows()/hasPermissionTo()… call in app/Policies, app/Http/Requests and
 * the user-management controller. Only dotted names are permission names; bare words
 * ('view', 'update', 'telegram') are policy abilities.
 */
final class PermissionsParityTest extends TenantTestCase
{
    /**
     * Permissions added by QA-2: no seeded equivalent existed. stores.view_all is what
     * ActiveStore / ApiTokenAuth check for `X-Store-Id: all`. Admin-only by default (D5).
     */
    private const QA2_SEEDED = ['inventory.adjust', 'daily_journal.manage', 'stores.view_all'];

    private const BACKFILL_MIGRATION = 'database/migrations/tenant/2026_10_10_070000_seed_qa2_missing_permissions.php';

    /** Controllers whose permission strings are part of the parity contract. */
    private const SCANNED_CONTROLLERS = ['app/Http/Controllers/Api/UserController.php'];

    private const PERMISSION_CALL = '/(?:->(?:can|cannot|cant|authorize|hasPermissionTo|hasAnyPermission|hasAllPermissions|checkPermissionTo)'
        .'|Gate::(?:allows|denies|check|any|none|authorize|inspect))\(\s*(\[[^\]]*\]|\'[^\']*\'|"[^"]*")/';

    private const PERMISSION_NAME = '/[\'"]([a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+)[\'"]/';

    // ------------------------------------------------------------------
    // Parity: every checked name is seeded
    // ------------------------------------------------------------------

    public function test_every_route_middleware_permission_is_seeded(): void
    {
        $usages = $this->routePermissionUsages();

        $this->assertArrayHasKey('logs.view', $usages, 'Scanner sanity: routes/api.php guards activity logs with can:logs.view.');
        $this->assertMissingIsEmpty($usages, 'route middleware');
    }

    public function test_every_form_request_and_user_controller_permission_is_seeded(): void
    {
        $usages = $this->sourcePermissionUsages(array_merge(
            $this->phpFiles('app/Http/Requests'),
            array_map(fn (string $path): string => base_path($path), self::SCANNED_CONTROLLERS),
        ));

        $this->assertArrayHasKey('customers.manage', $usages, 'Scanner sanity: StoreCustomerRequest checks customers.manage.');
        $this->assertArrayHasKey('roles.manage', $usages, 'Scanner sanity: UserController checks roles.manage.');
        $this->assertMissingIsEmpty($usages, 'FormRequests / UserController');
    }

    public function test_every_policy_permission_is_seeded(): void
    {
        $usages = $this->sourcePermissionUsages($this->phpFiles('app/Policies'));

        $this->assertArrayHasKey('invoices.view', $usages, 'Scanner sanity: InvoicePolicy checks invoices.view.');
        $this->assertMissingIsEmpty($usages, 'Policies');
    }

    /**
     * The SPA router guards pages with `meta.permission`; a name that is not seeded hides the
     * page from everyone but admin (authStore.hasPermission is a plain includes()). Names the
     * store resolves without the permission list (super_admin.access, view_telescope) are central.
     */
    public function test_every_spa_router_meta_permission_is_seeded(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/router/index.js'));

        preg_match_all('/\bpermissions?\s*:\s*(\[[^\]]*\]|\'[^\']*\'|"[^"]*")/', $source, $matches, PREG_OFFSET_CAPTURE);

        $usages = [];
        foreach ($matches[1] as [$argument, $offset]) {
            $line = substr_count(substr($source, 0, (int) $offset), "\n") + 1;
            preg_match_all(self::PERMISSION_NAME, (string) $argument, $names);
            foreach ($names[1] as $name) {
                if (in_array($name, ['super_admin.access', 'view_telescope'], true)) {
                    continue;
                }
                $usages[$name][] = 'resources/js/router/index.js:'.$line;
            }
        }

        $this->assertArrayHasKey('pos.access', $usages, 'Scanner sanity: the POS route requires pos.access.');
        $this->assertMissingIsEmpty($usages, 'resources/js/router/index.js');
    }

    public function test_scanner_extracts_every_name_of_a_permission_call_and_ignores_policy_abilities(): void
    {
        $source = <<<'PHP'
            <?php
            $a = $user->can('alpha.view') || $user->hasAnyPermission(['beta.manage', 'gamma.edit']);
            $b = Gate::allows('delta.create') && $this->user()?->can('update', $model);
            $c = $user->stores()->where('stores.id', 1)->exists();
            PHP;

        $names = array_keys($this->namesInSource($source, 'inline.php'));
        sort($names);

        $this->assertSame(['alpha.view', 'beta.manage', 'delta.create', 'gamma.edit'], $names);
    }

    // ------------------------------------------------------------------
    // Labels: lang/{ar,en}/permissions.php, never hardcoded in the seeder
    // ------------------------------------------------------------------

    public function test_every_seeded_tenant_permission_has_an_ar_and_en_label(): void
    {
        $missing = [];
        foreach ($this->seededTenantPermissions() as $name) {
            foreach (['ar', 'en'] as $locale) {
                $value = Lang::get('permissions.'.$name, [], $locale, false);
                if (! is_string($value) || $value === 'permissions.'.$name || trim($value) === '') {
                    $missing[] = "{$locale}: permissions.{$name}";
                }
            }
        }

        $this->assertSame([], $missing, "Seeded permissions without a translated label:\n".implode("\n", $missing));
    }

    public function test_permissions_seeder_holds_no_hardcoded_arabic_labels(): void
    {
        $source = (string) file_get_contents(database_path('seeders/PermissionsSeeder.php'));

        $this->assertSame(0, preg_match('/\p{Arabic}/u', $source), 'Permission labels belong in lang/{ar,en}/permissions.php.');
    }

    // ------------------------------------------------------------------
    // CTO D5: new permissions are granted to the admin role only
    // ------------------------------------------------------------------

    public function test_qa2_permissions_are_seeded_for_admin_only(): void
    {
        $tenant = $this->createTenant();

        $holders = $this->inTenant($tenant, function (): array {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $out = [];
            foreach (self::QA2_SEEDED as $name) {
                $out[$name] = Role::query()
                    ->whereHas('permissions', fn ($q) => $q->where('name', $name))
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();
            }

            return $out;
        });

        foreach (self::QA2_SEEDED as $name) {
            $this->assertSame(['admin'], $holders[$name], "{$name} must be granted to the admin role only (D5).");
        }
    }

    public function test_seeder_rerun_keeps_manual_grants_of_other_roles_intact(): void
    {
        $tenant = $this->createTenant();

        $kept = $this->inTenant($tenant, function (): bool {
            $custom = Role::findOrCreate('shift-lead', 'web');
            $custom->givePermissionTo('inventory.adjust');

            (new PermissionsSeeder)->run();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return Role::findByName('shift-lead')->hasPermissionTo('inventory.adjust');
        });

        $this->assertTrue($kept, 'Re-seeding must not revoke an owner-made grant on a custom role.');
    }

    // ------------------------------------------------------------------
    // Backfill migration for tenants provisioned before QA-2
    // ------------------------------------------------------------------

    public function test_backfill_migration_adds_missing_permissions_to_admin_only_and_is_reversible(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, function (): void {
            $this->deletePermissions(self::QA2_SEEDED);
            $this->assertSame(0, Permission::query()->whereIn('name', self::QA2_SEEDED)->count());

            $this->runBackfill('up');
            $this->runBackfill('up'); // idempotent
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (self::QA2_SEEDED as $name) {
                $this->assertSame(1, Permission::query()->where('name', $name)->where('guard_name', 'web')->count(), "{$name} backfilled once");
                $this->assertTrue(Role::findByName('admin')->hasPermissionTo($name), "admin holds {$name}");
                foreach (['cashier', 'storekeeper', 'accountant'] as $role) {
                    $this->assertFalse(Role::findByName($role)->hasPermissionTo($name), "{$role} must not receive {$name} (D5)");
                }
            }

            $this->runBackfill('down');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->assertSame(0, Permission::query()->whereIn('name', self::QA2_SEEDED)->count(), 'down() removes the backfilled permissions');
        });
    }

    public function test_backfill_migration_in_one_tenant_does_not_touch_another_tenant(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->inTenant($tenantA, fn () => $this->deletePermissions(self::QA2_SEEDED));
        $this->inTenant($tenantB, fn () => $this->deletePermissions(self::QA2_SEEDED));

        $this->inTenant($tenantA, fn () => $this->runBackfill('up'));

        $this->assertSame(
            count(self::QA2_SEEDED),
            $this->inTenant($tenantA, fn (): int => Permission::query()->whereIn('name', self::QA2_SEEDED)->count()),
        );
        $this->assertSame(
            0,
            $this->inTenant($tenantB, fn (): int => Permission::query()->whereIn('name', self::QA2_SEEDED)->count()),
            'Running the backfill inside tenant A leaked into tenant B.',
        );
    }

    // ------------------------------------------------------------------
    // Q10: daily_journal.close_shift guards opening and closing a shift
    // ------------------------------------------------------------------

    public function test_shift_open_and_close_routes_require_daily_journal_close_shift(): void
    {
        $this->assertContains('can:daily_journal.close_shift', $this->routeMiddleware('api.shifts.open'));
        $this->assertContains('can:daily_journal.close_shift', $this->routeMiddleware('api.shifts.close'));
    }

    public function test_user_with_journal_view_but_without_close_shift_cannot_open_or_close_a_shift(): void
    {
        $tenant = $this->createTenant();
        $viewer = $this->createTenantUser($tenant, null, ['daily_journal.view', 'pos.access']);
        $headers = $this->tenantHeaders($tenant, $viewer);

        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '100.000'], $headers)->assertForbidden();
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '100.000'], $headers)->assertForbidden();

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => CashShift::query()->count()), 'A forbidden open must not create a shift.');
    }

    public function test_cashier_role_with_close_shift_opens_and_closes_a_shift(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $this->attachToMainStore($tenant, $cashier);

        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '250.500'], $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(201);

        $this->assertSame('250.500', $this->inTenant($tenant, fn (): string => (string) CashShift::query()->where('user_id', $cashier->id)->value('opening_cash_balance')));

        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '250.500'], $this->tenantHeaders($tenant, $cashier))
            ->assertOk();
    }

    public function test_close_shift_granted_in_one_tenant_does_not_authorize_the_same_role_in_another(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        // Same role name in both tenants; only A's copy is granted close_shift.
        foreach ([$tenantA, $tenantB] as $tenant) {
            $this->inTenant($tenant, fn () => Role::findOrCreate('shift-viewer', 'web')->syncPermissions(['daily_journal.view', 'pos.access']));
        }
        $this->inTenant($tenantA, fn () => Role::findByName('shift-viewer')->givePermissionTo('daily_journal.close_shift'));

        $userA = $this->createTenantUser($tenantA, 'shift-viewer');
        $userB = $this->createTenantUser($tenantB, 'shift-viewer');
        $this->attachToMainStore($tenantA, $userA);

        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '10.000'], $this->tenantHeaders($tenantA, $userA))->assertStatus(201);
        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '10.000'], $this->tenantHeaders($tenantB, $userB))->assertForbidden();

        $this->assertSame(0, $this->inTenant($tenantB, fn (): int => CashShift::query()->count()));
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /**
     * @param  array<string, list<string>>  $usages  name => where it is used
     */
    private function assertMissingIsEmpty(array $usages, string $source): void
    {
        $tenantSeeded = array_flip($this->seededTenantPermissions());
        $centralSeeded = array_flip($this->seededCentralPermissions());

        $missing = [];
        foreach ($usages as $name => $where) {
            $seeded = str_starts_with($name, 'super_admin.') ? $centralSeeded : $tenantSeeded;
            if (! isset($seeded[$name])) {
                $missing[] = $name.'  <=  '.implode(', ', array_unique($where));
            }
        }
        sort($missing);

        $this->assertSame([], $missing, "Permission names checked in {$source} but never seeded:\n".implode("\n", $missing));
    }

    /** @return list<string> permission names a freshly provisioned tenant holds (web guard) */
    private function seededTenantPermissions(): array
    {
        $tenant = $this->createTenant();

        return $this->inTenant($tenant, fn (): array => Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all());
    }

    /** @return list<string> platform permissions seeded in the central database (any guard) */
    private function seededCentralPermissions(): array
    {
        $this->endTenancy();
        $this->seed(CentralPermissionsSeeder::class);

        $connection = (string) config('tenancy.database.central_connection', config('database.default'));

        return Permission::on($connection)->where('name', 'like', 'super_admin.%')->pluck('name')->unique()->values()->all();
    }

    /** @return array<string, list<string>> */
    private function routePermissionUsages(): array
    {
        $usages = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! preg_match('/^(can|permission|role_or_permission):(.+)$/', $middleware, $m)) {
                    continue;
                }

                $argument = $m[1] === 'can' ? explode(',', $m[2])[0] : $m[2];
                foreach (preg_split('/[|,]/', $argument) ?: [] as $name) {
                    $name = trim($name);
                    if (str_contains($name, '.')) {
                        $usages[$name][] = implode('|', $route->methods()).' '.$route->uri();
                    }
                }
            }
        }

        return $usages;
    }

    /**
     * @param  list<string>  $files
     * @return array<string, list<string>>
     */
    private function sourcePermissionUsages(array $files): array
    {
        $usages = [];
        foreach ($files as $file) {
            $this->assertFileExists($file);
            $relative = ltrim(str_replace([base_path(), '\\'], ['', '/'], $file), '/');

            foreach ($this->namesInSource((string) file_get_contents($file), $relative) as $name => $where) {
                $usages[$name] = array_merge($usages[$name] ?? [], $where);
            }
        }

        return $usages;
    }

    /** @return array<string, list<string>> */
    private function namesInSource(string $source, string $label): array
    {
        $usages = [];
        if (preg_match_all(self::PERMISSION_CALL, $source, $calls, PREG_OFFSET_CAPTURE) === 0) {
            return $usages;
        }

        foreach ($calls[1] as [$arguments, $offset]) {
            $line = substr_count(substr($source, 0, (int) $offset), "\n") + 1;
            preg_match_all(self::PERMISSION_NAME, (string) $arguments, $names);
            foreach ($names[1] as $name) {
                $usages[$name][] = "{$label}:{$line}";
            }
        }

        return $usages;
    }

    /** @return list<string> */
    private function phpFiles(string $relativeDir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($relativeDir), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        $this->assertNotEmpty($files, "No PHP files under {$relativeDir}");

        return $files;
    }

    /** @return list<string> */
    private function routeMiddleware(string $routeName): array
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "Route {$routeName} is not registered.");

        return array_values(array_filter($route->gatherMiddleware(), 'is_string'));
    }

    /** Run the QA-2 backfill migration's up() or down() in the current (tenant) context. */
    private function runBackfill(string $direction): void
    {
        $path = base_path(self::BACKFILL_MIGRATION);
        $this->assertFileExists($path);

        $migration = require $path;
        $this->assertInstanceOf(Migration::class, $migration);

        if ($direction === 'up' && method_exists($migration, 'up')) {
            $migration->up();

            return;
        }

        if ($direction === 'down' && method_exists($migration, 'down')) {
            $migration->down();

            return;
        }

        $this->fail("The backfill migration has no {$direction}() method.");
    }

    private function attachToMainStore(Tenant $tenant, User $user): void
    {
        $storeId = (int) $this->tenantStore($tenant)->id;
        $this->inTenant($tenant, fn () => User::query()->findOrFail($user->id)->stores()->syncWithoutDetaching([$storeId]));
    }

    /** @param list<string> $names */
    private function deletePermissions(array $names): void
    {
        Permission::query()->whereIn('name', $names)->get()->each(fn (Permission $permission) => $permission->delete());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
