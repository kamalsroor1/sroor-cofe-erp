<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Http\Middleware\RestrictLivewireToConsole;
use Illuminate\Http\Request;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SEC-21 (4): Livewire ships only for the Pulse dashboard (platform console). Its endpoints are
 * registered without a host. Exploit before the fix: POST http://<shop>/livewire-<hash>/update
 * and /livewire-<hash>/upload-file were routed to Livewire on every shop domain (no known
 * exploit, but an unauthenticated upload/update surface that has nothing to do there), and
 * GET /livewire-<hash>/livewire.js answered 200 on a shop host.
 */
final class LivewireEndpointsRestrictedTest extends TenantTestCase
{
    private const ADMIN_HOST = 'admin.platform-harness.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['central.admin_domains' => [self::ADMIN_HOST]]);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function endpoints(): array
    {
        return [
            'update' => ['POST', '/update'],
            'upload' => ['POST', '/upload-file'],
            'preview' => ['GET', '/preview-file/x.png'],
            'script' => ['GET', '/livewire.js'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_livewire_endpoints_are_404_on_a_shop_host(string $method, string $suffix): void
    {
        $tenant = $this->createTenant();
        $url = $this->tenantUrl($tenant, EndpointResolver::prefix().$suffix);

        $this->json($method, $url)->assertNotFound();
    }

    #[DataProvider('endpoints')]
    public function test_livewire_endpoints_are_404_on_a_non_console_central_host_and_with_a_tenant_identifier(string $method, string $suffix): void
    {
        $this->json($method, 'http://localhost'.EndpointResolver::prefix().$suffix)->assertNotFound();

        $tenant = $this->createTenant();
        $this->json($method, 'http://'.self::ADMIN_HOST.EndpointResolver::prefix().$suffix, [], ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertNotFound();
    }

    /**
     * Security review of SEC-21: the router matches the DECODED path, so a percent-encoded
     * letter ("/%6Civewire-<hash>/update") reached Livewire while the raw-path check missed it.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function encodedPaths(): array
    {
        return [
            'encoded first letter, update' => ['POST', '/%6Civewire-{hash}/update'],
            'encoded first letter, script' => ['GET', '/%6Civewire-{hash}/livewire.js'],
            'encoded dash' => ['GET', '/livewire%2D{hash}/livewire.js'],
            'encoded slash' => ['POST', '/livewire-{hash}%2Fupload-file'],
            'upper case' => ['GET', '/LIVEWIRE-{hash}/livewire.js'],
            'double slash' => ['GET', '//livewire-{hash}/livewire.js'],
            'double encoded' => ['GET', '/%256Civewire-{hash}/livewire.js'],
        ];
    }

    #[DataProvider('encodedPaths')]
    public function test_percent_encoded_or_disguised_livewire_paths_are_404_on_a_shop_host(string $method, string $path): void
    {
        $tenant = $this->createTenant();
        $hash = substr(EndpointResolver::prefix(), strlen('/livewire-'));

        $this->json($method, $this->tenantUrl($tenant, str_replace('{hash}', $hash, $path)))->assertNotFound();
        $this->assertTrue(RestrictLivewireToConsole::isLivewirePath(Request::create('http://shop.example.test'.str_replace('{hash}', $hash, $path))), $path);
    }

    public function test_the_console_host_still_serves_livewire_for_pulse(): void
    {
        $this->get('http://'.self::ADMIN_HOST.EndpointResolver::prefix().'/livewire.js')->assertOk();
    }

    public function test_the_classic_unhashed_paths_are_matched_too(): void
    {
        foreach (['/livewire/update', '/livewire/upload-file', '/livewire'] as $path) {
            $this->assertTrue(RestrictLivewireToConsole::isLivewirePath(Request::create($path)), $path);
        }
        foreach (['/pos', '/livewirex', '/api/v1/items'] as $path) {
            $this->assertFalse(RestrictLivewireToConsole::isLivewirePath(Request::create($path)), $path);
        }
    }
}
