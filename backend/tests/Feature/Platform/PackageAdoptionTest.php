<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Composer\InstalledVersions;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passkeys\PasskeysServiceProvider;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PKG-1: the Phase 1 package spike.
 *
 * Locks in the adoption decisions recorded in
 * docs/03-architecture/package-adoption-plan.md ("نتيجة الـ spike"):
 *  - every approved package is installed (Sentry chosen over Nightwatch, Q-O2);
 *  - nothing a package ships is switched on before its consumer task wires it:
 *    no Fortify routes (IDEN-1.12 registers the provider for the central guard),
 *    no package migrations auto-loaded into the central or tenant DB (PKG-2 /
 *    IDEN-1.5 / OPS-* place their tables explicitly), no public health route,
 *    Horizon dashboard closed outside `local`;
 *  - Pennant is an API layer only (store `array`, Q-E1): no `features` table;
 *  - Sentry never sends PII by default and is inert without a DSN.
 */
final class PackageAdoptionTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function approvedComposerPackages(): array
    {
        return [
            'pennant' => ['laravel/pennant'],
            'fortify' => ['laravel/fortify'],
            'horizon' => ['laravel/horizon'],
            'backup' => ['spatie/laravel-backup'],
            'activitylog' => ['spatie/laravel-activitylog'],
            'medialibrary' => ['spatie/laravel-medialibrary'],
            'health' => ['spatie/laravel-health'],
            'sentry' => ['sentry/sentry-laravel'],
        ];
    }

    #[DataProvider('approvedComposerPackages')]
    public function test_approved_package_is_installed(string $package): void
    {
        $this->assertTrue(InstalledVersions::isInstalled($package), "{$package} must be installed (PKG-1).");
    }

    public function test_only_sentry_is_installed_for_error_tracking(): void
    {
        // Q-O2 decided in PKG-1: Sentry, not Nightwatch ("install the chosen one only").
        $this->assertFalse(InstalledVersions::isInstalled('laravel/nightwatch'));
    }

    public function test_vueuse_core_is_a_frontend_dependency(): void
    {
        /** @var array{dependencies?: array<string, string>} $package */
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('@vueuse/core', $package['dependencies'] ?? []);
    }

    public function test_pennant_is_an_api_layer_on_the_array_store(): void
    {
        $this->assertSame('array', config('pennant.default'));

        // Resolving a feature must not touch the database (there is no `features` table).
        Feature::define('pkg-1-probe', static fn (): bool => true);

        $this->assertTrue(Feature::for('tenant-probe')->active('pkg-1-probe'));
    }

    public function test_fortify_is_not_booted_until_its_consumer_registers_it(): void
    {
        $this->assertFalse($this->app->providerIsLoaded(FortifyServiceProvider::class));

        $this->assertFalse($this->app->providerIsLoaded(PasskeysServiceProvider::class));

        $fortifyRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn (RoutingRoute $route): bool => str_starts_with((string) $route->getActionName(), 'Laravel\\Fortify\\')
                || str_starts_with((string) $route->getActionName(), 'Laravel\\Passkeys\\'))
            ->map(static fn (RoutingRoute $route): string => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $fortifyRoutes, 'Fortify / Passkeys must not expose login, register or 2FA routes on any host.');
    }

    public function test_no_package_migrations_are_auto_loaded(): void
    {
        $vendorPaths = collect(app('migrator')->paths())
            ->filter(static fn (string $path): bool => str_contains(str_replace('\\', '/', $path), '/vendor/'))
            ->values()
            ->all();

        $this->assertSame([], $vendorPaths, 'Package tables are placed explicitly in database/migrations{,/tenant} (PKG-2 range 0500xx).');
    }

    public function test_health_package_exposes_no_route_by_default(): void
    {
        $healthRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn (RoutingRoute $route): bool => str_starts_with((string) $route->getActionName(), 'Spatie\\Health\\'))
            ->all();

        $this->assertSame([], $healthRoutes);
    }

    public function test_horizon_dashboard_is_closed_outside_local(): void
    {
        $this->assertFalse($this->app->environment('local'));

        $this->get('/horizon/api/stats')->assertForbidden();
    }

    public function test_sentry_sends_no_pii_and_is_inert_without_a_dsn(): void
    {
        $this->assertFalse(config('sentry.send_default_pii'));
        $this->assertEmpty(config('sentry.dsn'));
    }

    public function test_consumer_commands_are_available(): void
    {
        $commands = array_keys(Artisan::all());

        // OPS-3 (`horizon:terminate` on deploy) and OPS-5 (backups).
        $this->assertContains('horizon:terminate', $commands);
        $this->assertContains('backup:run', $commands);
    }
}
