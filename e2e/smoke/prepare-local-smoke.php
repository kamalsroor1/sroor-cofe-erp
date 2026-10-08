<?php

declare(strict_types=1);

/*
 * Builds the THROWAWAY database for the local Playwright smoke run (e2e/smoke).
 *
 * Invoked by serve-local-smoke.mjs with the environment from smoke-env.mjs. It refuses to run unless:
 *  - APP_ENV is not production,
 *  - the default connection is sqlite and points at E2E_SMOKE_CENTRAL_DB (a temp file),
 *  - the tenant DB prefix is the dedicated e2e prefix.
 * It never touches backend/database/database.sqlite or any existing tenant DB.
 */

use App\DTOs\CreateTenantDTO;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockService;
use App\Services\TenantProvisionerService;
use Database\Seeders\CentralPermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

$backend = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'backend';

require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function smoke_fail(string $message): never
{
    fwrite(STDERR, "[e2e-smoke] {$message}\n");
    exit(1);
}

function smoke_env(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        smoke_fail("missing environment variable {$key}");
    }

    return $value;
}

// ---- Guards -------------------------------------------------------------------------------------
if (app()->environment('production')) {
    smoke_fail('refusing to run with APP_ENV=production');
}

$centralDb = smoke_env('E2E_SMOKE_CENTRAL_DB');
$default = (string) config('database.default');
if ($default !== 'sqlite' || realpath((string) config('database.connections.sqlite.database')) !== realpath($centralDb)) {
    smoke_fail('central connection is not the throwaway sqlite file; aborting');
}
if (str_starts_with((string) realpath($centralDb), (string) realpath($backend))) {
    smoke_fail('throwaway central DB must live outside the repo');
}
if (config('tenancy.database.prefix') !== 'e2e_smoke_') {
    smoke_fail('tenant DB prefix must be e2e_smoke_');
}

$tenantId = smoke_env('E2E_SMOKE_TENANT_ID');

// ---- Central schema + platform data -------------------------------------------------------------
Artisan::call('migrate', ['--force' => true]);
(new CentralPermissionsSeeder)->run();
(new PlansAndFeaturesSeeder)->run();

$superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

$superAdmin = User::create([
    'name' => 'E2E Super Admin',
    'email' => 'e2e-superadmin@smoke.localhost',
    'phone' => smoke_env('E2E_SMOKE_SUPERADMIN_PHONE'),
    'password' => Hash::make(smoke_env('E2E_SMOKE_SUPERADMIN_PASSWORD')),
    'is_active' => true,
]);
$superAdmin->syncRoles([$superAdminRole, $adminRole]);
app(PermissionRegistrar::class)->forgetCachedPermissions();

// ---- Tenant (DB created + migrated by the TenantCreated pipeline) -------------------------------
$plan = Plan::where('slug', 'enterprise')->first() ?? Plan::firstOrFail();

$tenant = app(TenantProvisionerService::class)->provision(new CreateTenantDTO(
    name: 'محل اختبار سموك',
    slug: $tenantId,
    email: 'e2e-admin@smoke.localhost',
    phone: smoke_env('E2E_SMOKE_TENANT_PHONE'),
    planId: (int) $plan->id,
    password: smoke_env('E2E_SMOKE_TENANT_PASSWORD'),
    customDomain: null,
    trialDays: 30,
));

// ---- Tenant demo data: one sellable item with opening stock in the main store -------------------
$tenant->run(function (): void {
    DB::transaction(function (): void {
        $store = Store::where('is_main', true)->firstOrFail();

        $item = Item::create([
            'code' => smoke_env('E2E_SMOKE_ITEM_CODE'),
            'name' => smoke_env('E2E_SMOKE_ITEM_NAME'),
            'unit' => 'kg',
            'selling_price' => smoke_env('E2E_SMOKE_ITEM_PRICE'),
            'min_selling_price' => smoke_env('E2E_SMOKE_ITEM_PRICE'),
            'cost_price' => '80.000',
            'weighted_avg_cost' => '80.000',
            'current_stock' => '0.000',
            'is_active' => true,
            'is_pos_pinned' => true,
        ]);

        app(StockService::class)->addStock(
            item: $item,
            quantity: smoke_env('E2E_SMOKE_ITEM_STOCK'),
            unitCost: '80.000',
            source: $store,
            documentNumber: 'E2E-SMOKE-OPENING',
            movementType: 'initial_balance',
            notes: 'e2e smoke opening balance',
            storeId: (int) $store->id,
        );
    });
});

fwrite(STDOUT, json_encode([
    'tenant' => $tenant->id,
    'tenants_total' => Tenant::count(),
], JSON_UNESCAPED_UNICODE).PHP_EOL);
