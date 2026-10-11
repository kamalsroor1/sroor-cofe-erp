<?php

declare(strict_types=1);

/*
 * Sroor ERP - provision ONE local test tenant through the official path
 * (ProvisionTenantAction -> TenantProvisionerService), idempotently.
 *
 * Usage (from anywhere; normally called by scripts/local/setup-local.ps1):
 *   php scripts/local/provision-local-tenant.php <slug> "<display name>" <admin-email> <admin-phone>
 *
 * The tenant admin password is read from the environment variable
 * SROOR_LOCAL_TENANT_PASSWORD (never from argv, never printed).
 *
 * Safety guards (refuses to run otherwise):
 *   - APP_ENV must be "local";
 *   - the central connection must be MySQL on 127.0.0.1/localhost with a database
 *     named sroor_local_*;
 *   - the tenant DB prefix must start with sroor_local_tenant_.
 *
 * Idempotent: an existing tenant is never re-provisioned; only its missing local
 * domains (<slug>.<CENTRAL_DOMAIN> and <slug>.localhost) are added. The same identity
 * (id = slug, same two domains) is used by database/seeders/TenantSampleSeeder
 * (SEED_DEMO_TENANT_SLUG, default "demo"), so either one may run first.
 */

use App\Actions\Tenants\ProvisionTenantAction;
use App\DTOs\CreateTenantDTO;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$backend = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'backend';

require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function fail(string $message): never
{
    fwrite(STDERR, "[provision-local-tenant] ERROR: {$message}\n");
    exit(1);
}

function info(string $message): void
{
    fwrite(STDOUT, "[provision-local-tenant] {$message}\n");
}

if ($argc < 5) {
    fail('usage: php provision-local-tenant.php <slug> "<name>" <admin-email> <admin-phone>');
}

[, $slug, $name, $email, $phone] = $argv;
$slug = strtolower(trim($slug));

if (preg_match('/^[a-z][a-z0-9-]{1,30}$/', $slug) !== 1) {
    fail('slug must be 2-31 chars: lowercase letters, digits, dashes, starting with a letter.');
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fail('admin email is not valid.');
}
if (preg_match('/^[0-9+]{6,20}$/', $phone) !== 1) {
    fail('admin phone must be 6-20 digits.');
}

// ---- Safety guards: local MySQL, sroor_local_* databases only ----------------
if (! app()->environment('local')) {
    fail('APP_ENV must be "local".');
}

$central = (string) config('tenancy.database.central_connection');
$cfg = (array) config("database.connections.{$central}");
$driver = (string) ($cfg['driver'] ?? '');
$host = strtolower((string) ($cfg['host'] ?? ''));
$database = (string) ($cfg['database'] ?? '');
$prefix = (string) config('tenancy.database.prefix');

if (! in_array($driver, ['mysql', 'mariadb'], true)) {
    fail("central connection must be mysql (got '{$driver}').");
}
if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    fail('central DB host must be local (127.0.0.1 / localhost).');
}
if (! str_starts_with($database, 'sroor_local_')) {
    fail('central database name must start with sroor_local_.');
}
if (! str_starts_with($prefix, 'sroor_local_tenant_')) {
    fail('TENANT_DB_PREFIX must start with sroor_local_tenant_.');
}

// config(), not env(): same value the app uses, and it survives `config:cache`.
$centralDomain = strtolower(trim((string) config('tenancy.central_domain')));
if ($centralDomain === '' || ! str_ends_with($centralDomain, '.test')) {
    fail('CENTRAL_DOMAIN must be a local *.test domain (e.g. sroor.test).');
}

$localDomains = ["{$slug}.{$centralDomain}", "{$slug}.localhost"];

// ---- Existing tenant: only add missing local domains --------------------------
$existing = Tenant::query()->find($slug);
if ($existing !== null) {
    foreach ($localDomains as $domain) {
        $existing->domains()->firstOrCreate(['domain' => $domain]);
    }
    info("tenant '{$slug}' already exists; domains: ".$existing->domains()->pluck('domain')->implode(', '));
    exit(0);
}

// A leftover tenant DB without a central record would be silently reused by
// CREATE DATABASE IF NOT EXISTS: stop and let the operator decide.
$tenantDb = $prefix.$slug;
$exists = DB::connection($central)->select(
    'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
    [$tenantDb],
);
if ($exists !== []) {
    fail("database {$tenantDb} exists but tenant '{$slug}' does not. Drop it with scripts/local/reset-local.ps1 first.");
}

$password = (string) getenv('SROOR_LOCAL_TENANT_PASSWORD');
if (mb_strlen($password) < 8) {
    fail('SROOR_LOCAL_TENANT_PASSWORD must be set (8+ characters).');
}

$plan = Plan::query()->where('slug', 'enterprise')->first() ?? Plan::query()->first();
if ($plan === null) {
    fail('no plan found: run "php artisan db:seed --class=PlansAndFeaturesSeeder" first.');
}

$dto = new CreateTenantDTO(
    name: $name,
    slug: $slug,
    email: strtolower($email),
    phone: $phone,
    planId: (int) $plan->id,
    password: $password,
    trialDays: 365,
);

// OPS-2: provisioning is a queued job. With QUEUE_CONNECTION=database (local .env) and
// no worker running the tenant would stay `pending`: provision inline for this script.
config(['tenancy.provisioning.queue_connection' => 'sync']);

$tenant = app(ProvisionTenantAction::class)->execute($dto);
$tenant->refresh();
if (! $tenant->isProvisioned()) {
    fail("tenant '{$slug}' was created but provisioning ended as '{$tenant->provisioningStatus()->value}'"
        .' (code: '.($tenant->provisioning_error_code ?? '-').'). Check storage/logs, then retry from the console.');
}
unset($password, $dto);

foreach ($localDomains as $domain) {
    $tenant->domains()->firstOrCreate(['domain' => $domain]);
}

info("tenant '{$slug}' provisioned on database {$tenantDb} (plan: {$plan->slug}).");
info('domains: '.$tenant->domains()->pluck('domain')->implode(', '));
info("admin login: {$email} or {$phone} (password: the one you typed).");
exit(0);
