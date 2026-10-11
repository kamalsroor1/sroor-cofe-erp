<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Actions\SuperAdmin\ImpersonateTenantAction;
use App\Http\Requests\ImpersonateTenantRequest;
use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionFunction;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * IDEN-1.8: the Phase 0 impersonation path (App\Actions\SuperAdmin\ImpersonateTenantAction,
 * minting a stancl ImpersonationToken for a tenant user, authorised by
 * App\Http\Requests\ImpersonateTenantRequest which only checks a TENANT `admin` role) must not
 * be reachable from any HTTP route. The audited impersonation replaces it (IDEN-2.6, step-up +
 * IMPERSONATION_ENABLED flag); IDEN-2.7 (W3) deletes the legacy classes. Until then this test
 * keeps anyone from wiring them back in.
 */
final class LegacyImpersonationUnroutedTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    /** @var list<class-string> */
    private const LEGACY_CLASSES = [
        ImpersonateTenantAction::class,
        ImpersonateTenantRequest::class,
    ];

    public function test_no_route_action_references_the_legacy_impersonation_classes(): void
    {
        $routes = RouteFacade::getRoutes()->getRoutes();
        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $source = $this->routeActionSource($route);

            if ($source === null) {
                continue;
            }

            foreach (self::LEGACY_CLASSES as $class) {
                $this->assertStringNotContainsString(
                    class_basename($class),
                    $source,
                    sprintf('Route [%s %s] reaches %s.', implode('|', $route->methods()), $route->uri(), $class),
                );
            }
        }
    }

    public function test_no_application_code_outside_the_legacy_files_references_them(): void
    {
        $own = array_map(
            static fn (string $class): string => (string) realpath((string) (new ReflectionClass($class))->getFileName()),
            self::LEGACY_CLASSES,
        );

        $files = [
            ...File::allFiles(app_path()),
            ...File::allFiles(base_path('routes')),
            ...File::allFiles(base_path('bootstrap')),
        ];

        $offenders = [];

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || in_array((string) $file->getRealPath(), $own, true)) {
                continue;
            }

            $contents = $file->getContents();

            foreach (self::LEGACY_CLASSES as $class) {
                if (str_contains($contents, class_basename($class))) {
                    $offenders[] = $file->getRelativePathname().' -> '.class_basename($class);
                }
            }
        }

        $this->assertSame([], $offenders, 'Legacy impersonation classes must stay unreferenced until IDEN-2.7 deletes them.');
    }

    public function test_no_impersonation_api_route_lives_outside_the_control_plane(): void
    {
        // IDEN-2.6 adds the audited start route under /api/v1/super-admin (routes/central.php,
        // behind RequireRecentTwoFactor); the first test proves it is not the legacy action.
        $outside = array_values(array_map(
            static fn (Route $route): string => implode('|', $route->methods()).' '.$route->uri(),
            array_filter(
                RouteFacade::getRoutes()->getRoutes(),
                static fn (Route $route): bool => str_starts_with($route->uri(), 'api/')
                    && ! str_starts_with($route->uri(), 'api/v1/super-admin/')
                    && str_contains(strtolower($route->uri()), 'impersonat'),
            ),
        ));

        $this->assertSame([], $outside, 'No impersonation API route may live outside the central control plane.');
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function legacyImpersonationRequests(): array
    {
        return [
            'super-admin tenant impersonate' => ['POST', '/api/v1/super-admin/tenants/%s/impersonate'],
            'super-admin tenant impersonate (GET)' => ['GET', '/api/v1/super-admin/tenants/%s/impersonate'],
            'super-admin impersonate' => ['POST', '/api/v1/super-admin/impersonate/%s'],
            'tenant api impersonate' => ['POST', '/api/v1/tenants/%s/impersonate'],
        ];
    }

    #[DataProvider('legacyImpersonationRequests')]
    public function test_a_super_admin_cannot_mint_a_legacy_impersonation_token_over_http(string $method, string $uriPattern): void
    {
        $tenant = $this->createTenant();
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $before = ImpersonationToken::query()->count();

        $status = $this->json($method, sprintf($uriPattern, $tenant->getTenantKey()), [], $headers)->getStatusCode();

        $this->assertContains($status, [401, 404, 405], "{$method} {$uriPattern} must not be served.");
        $this->assertSame($before, ImpersonationToken::query()->count(), 'No impersonation token may be minted.');
    }

    /** Source code the route would execute: its controller class file, or the closure body. */
    private function routeActionSource(Route $route): ?string
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof Closure) {
            $reflection = new ReflectionFunction($uses);
            $file = $reflection->getFileName();

            if ($file === false) {
                return null;
            }

            $lines = file($file) ?: [];
            $start = max(0, (int) $reflection->getStartLine() - 1);
            $length = max(1, (int) $reflection->getEndLine() - $start);

            return implode('', array_slice($lines, $start, $length));
        }

        if (! is_string($uses)) {
            return null;
        }

        $class = str_contains($uses, '@') ? strstr($uses, '@', true) : $uses;

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $file = (new ReflectionClass($class))->getFileName();

        return $file === false ? null : (string) file_get_contents($file);
    }
}
