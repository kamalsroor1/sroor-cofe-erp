<?php

declare(strict_types=1);

use App\Models\Domain;
use App\Models\Tenant;
use App\Services\Tenancy\SafeMySQLDatabaseManager;
use Database\Seeders\TenantDatabaseSeeder;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Features\UniversalRoutes;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;
use Stancl\Tenancy\UUIDGenerator;

// Base domain of the platform (env CENTRAL_DOMAIN): tenant subdomains are "<slug>.<domain>".
// An empty value falls back to the production domain. Read it through
// config('tenancy.central_domain') / App\Support\PlatformHosts, never env() at runtime.
$centralDomain = strtolower(trim((string) env('CENTRAL_DOMAIN', ''))) ?: 'baraa-solutions.com';

return [
    'tenant_model' => Tenant::class,

    'central_domain' => $centralDomain,
    'id_generator' => UUIDGenerator::class,

    'domain_model' => Domain::class,

    /**
     * The list of domains hosting your central app.
     *
     * Only relevant if you're using the domain or subdomain identification middleware.
     */
    // IDEN-1.11: the platform-console host(s) from CENTRAL_ADMIN_DOMAINS (same parsing as
    // config/central.php) are central too, so no tenant lookup / tenant-miss throttling ever
    // runs on them. Appended last: code reads central_domains.0 and .2 by index.
    'central_domains' => array_values(array_unique(array_merge([
        '127.0.0.1',
        'localhost',
        'baraa-solutions.com',
        'www.baraa-solutions.com',
        'sroor.test',
        $centralDomain,
    ], array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('CENTRAL_ADMIN_DOMAINS', '')),
    )))))),

    /**
     * Tenancy bootstrappers are executed when tenancy is initialized.
     * Their responsibility is making Laravel features tenant-aware.
     *
     * To configure their behavior, see the config keys below.
     */
    'bootstrappers' => [
        DatabaseTenancyBootstrapper::class,
        // Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        FilesystemTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
    ],

    /**
     * Database tenancy config. Used by DatabaseTenancyBootstrapper.
     */
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),

        /**
         * Connection used as a "template" for the dynamically created tenant database connection.
         * Note: don't name your template connection tenant. That name is reserved by package.
         */
        'template_tenant_connection' => null,

        /**
         * Tenant database names are created like this:
         * prefix + tenant_id + suffix.
         */
        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
        'suffix' => env('TENANT_DB_SUFFIX', (env('DB_CONNECTION') === 'sqlite' || config('database.default') === 'sqlite') ? '.sqlite' : ''),

        /**
         * TenantDatabaseManagers are classes that handle the creation & deletion of tenant databases.
         */
        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql' => SafeMySQLDatabaseManager::class,
            'mariadb' => SafeMySQLDatabaseManager::class,
            'pgsql' => PostgreSQLDatabaseManager::class,

        /**
         * Use this database manager for MySQL to have a DB user created for each tenant database.
         * You can customize the grants given to these users by changing the $grants property.
         */
            // 'mysql' => Stancl\Tenancy\TenantDatabaseManagers\PermissionControlledMySQLDatabaseManager::class,

        /**
         * Disable the pgsql manager above, and enable the one below if you
         * want to separate tenant DBs by schemas rather than databases.
         */
            // 'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class, // Separate by schema instead of database
        ],
    ],

    /**
     * Cache tenancy config. Used by CacheTenancyBootstrapper.
     *
     * This works for all Cache facade calls, cache() helper
     * calls and direct calls to injected cache stores.
     *
     * Each key in cache will have a tag applied on it. This tag is used to
     * scope the cache both when writing to it and when reading from it.
     *
     * You can clear cache selectively by specifying the tag.
     */
    'cache' => [
        'tag_base' => 'tenant', // This tag_base, followed by the tenant_id, will form a tag that will be applied on each cache call.
    ],

    /**
     * Filesystem tenancy config. Used by FilesystemTenancyBootstrapper.
     * https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers/#filesystem-tenancy-boostrapper.
     */
    'filesystem' => [
        /**
         * Each disk listed in the 'disks' array will be suffixed by the suffix_base, followed by the tenant_id.
         */
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
            // 's3',
        ],

        /**
         * Use this for local disks.
         *
         * See https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers/#filesystem-tenancy-boostrapper
         */
        'root_override' => [
            // Disks whose roots should be overridden after storage_path() is suffixed.
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],

        /**
         * Should storage_path() be suffixed.
         *
         * Note: Disabling this will likely break local disk tenancy. Only disable this if you're using an external file storage service like S3.
         *
         * For the vast majority of applications, this feature should be enabled. But in some
         * edge cases, it can cause issues (like using Passport with Vapor - see #196), so
         * you may want to disable this if you are experiencing these edge case issues.
         */
        'suffix_storage_path' => true,

        /**
         * By default, asset() calls are made multi-tenant too. You can use global_asset() and mix()
         * for global, non-tenant-specific assets. However, you might have some issues when using
         * packages that use asset() calls inside the tenant app. To avoid such issues, you can
         * disable asset() helper tenancy and explicitly use tenant_asset() calls in places
         * where you want to use tenant-specific assets (product images, avatars, etc).
         */
        'asset_helper_tenancy' => false,
    ],

    /**
     * Redis tenancy config. Used by RedisTenancyBootstrapper.
     *
     * Note: You need phpredis to use Redis tenancy.
     *
     * Note: You don't need to use this if you're using Redis only for cache.
     * Redis tenancy is only relevant if you're making direct Redis calls,
     * either using the Redis facade or by injecting it as a dependency.
     */
    'redis' => [
        'prefix_base' => 'tenant', // Each key in Redis will be prepended by this prefix_base, followed by the tenant id.
        'prefixed_connections' => [ // Redis connections whose keys are prefixed, to separate one tenant's keys from another.
            // 'default',
        ],
    ],

    /**
     * Features are classes that provide additional functionality
     * not needed for tenancy to be bootstrapped. They are run
     * regardless of whether tenancy has been initialized.
     *
     * See the documentation page for each class to
     * understand which ones you want to enable.
     */
    'features' => [
        UserImpersonation::class,
        // Stancl\Tenancy\Features\TelescopeTags::class,
        UniversalRoutes::class,
        // Stancl\Tenancy\Features\TenantConfig::class, // https://tenancyforlaravel.com/docs/v3/features/tenant-config
        // Stancl\Tenancy\Features\CrossDomainRedirect::class, // https://tenancyforlaravel.com/docs/v3/features/cross-domain-redirect
        // Stancl\Tenancy\Features\ViteBundler::class,
    ],

    /**
     * Should tenancy routes be registered.
     *
     * Tenancy routes include tenant asset routes. By default, this route is
     * enabled. But it may be useful to disable them if you use external
     * storage (e.g. S3 / Dropbox) or have a custom asset controller.
     */
    'routes' => true,

    /**
     * Parameters used by the tenants:migrate command.
     */
    'migration_parameters' => [
        '--force' => true, // This needs to be true to run migrations in production.
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    /**
     * Parameters used by the tenants:seed command.
     */
    'seeder_parameters' => [
        '--class' => TenantDatabaseSeeder::class, // tenant-safe root seeder (never the central DatabaseSeeder)
        // '--force' => true, // This needs to be true to seed tenant databases in production
    ],

    /**
     * OPS-2: queued tenant provisioning (App\Jobs\ProvisionTenantJob).
     *
     * - connection: Laravel DB connection used for CREATE/DROP DATABASE and the per-tenant
     *   user on MySQL/MariaDB (config/database.php `provisioner`, the `sroor_provisioner`
     *   account of vps-runbook.md §5). Never root in production.
     * - queue_connection / queue: where the job runs. The job may take up to 900 s, so the
     *   queue connection's `retry_after` must be larger than that (a duplicate delivery is
     *   dropped by WithoutOverlapping, but it is still wasted work). null = the default.
     * - per_tenant_db_user: create one MySQL user per tenant, granted only on its own
     *   database (escaped GRANT target). Required where the app account has no access to
     *   tenant databases (the VPS). Off by default: dev/CI/shared hosting reuse DB_*.
     * - db_user_host: host part of the per-tenant user ('localhost' on the VPS socket setup).
     * - unique_for: seconds the job's unique lock lives (never below every attempt + backoff,
     *   3 x 900 + 30 + 120 = 2850). A pending/running tenant without activity for that long is
     *   "stale" (lost dispatch, dead worker) and may be retried by a super-admin.
     *
     * Read at runtime through config() only (never env() outside this file).
     */
    'provisioning' => [
        'connection' => env('TENANT_PROVISIONER_CONNECTION', 'provisioner'),
        'queue_connection' => env('TENANT_PROVISIONING_QUEUE_CONNECTION') ?: null,
        'queue' => env('TENANT_PROVISIONING_QUEUE') ?: 'default',
        'per_tenant_db_user' => filter_var(env('TENANT_DB_PER_TENANT_USER', false), FILTER_VALIDATE_BOOL),
        'db_user_host' => env('TENANT_DB_USER_HOST') ?: 'localhost',
        'unique_for' => max(2850, (int) (env('TENANT_PROVISIONING_UNIQUE_FOR') ?: 3600)),
    ],
];
