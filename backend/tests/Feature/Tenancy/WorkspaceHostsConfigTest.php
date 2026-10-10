<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Support\PlatformHosts;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TenantTestCase;

/**
 * The platform's hosts come from configuration only, so they survive `config:cache` and
 * the same code serves production (https, baraa-solutions.com) and the local Laragon setup
 * (http, sroor.test):
 *  - CENTRAL_DOMAIN is read in config/tenancy.php only (tenancy.central_domain);
 *  - tenant default hosts are "<slug>.<tenancy.central_domain>";
 *  - the SPA shell exposes the central hosts (<meta name="central-domains">) without the
 *    platform-console hosts, which are rendered (<meta name="admin-domains">) on the console only.
 */
final class WorkspaceHostsConfigTest extends TenantTestCase
{
    private const ADMIN_HOST = 'admin.hosts-harness.test';

    public function test_central_domain_env_is_never_read_outside_config_files(): void
    {
        $offenders = [];

        $files = [
            ...File::allFiles(app_path()),
            ...File::allFiles(base_path('routes')),
            ...File::allFiles(base_path('bootstrap')),
            ...File::allFiles(database_path('seeders')),
            ...File::allFiles(resource_path('views')),
        ];

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match("/env\\(\\s*['\"]CENTRAL_DOMAIN['\"]/", $file->getContents()) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, "Read config('tenancy.central_domain') instead of env('CENTRAL_DOMAIN').");
    }

    public function test_config_exposes_the_central_domain_and_lists_it_as_central(): void
    {
        $domain = config('tenancy.central_domain');

        $this->assertIsString($domain);
        $this->assertNotSame('', $domain);
        $this->assertContains($domain, (array) config('tenancy.central_domains'));
    }

    public function test_tenant_hosts_hang_off_the_configured_central_domain(): void
    {
        config(['tenancy.central_domain' => 'sroor.test']);
        $this->assertSame('demo.sroor.test', PlatformHosts::tenantHost('Demo'));

        config(['tenancy.central_domain' => '']);
        $this->assertSame('demo.baraa-solutions.com', PlatformHosts::tenantHost('demo'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function appUrls(): array
    {
        return [
            'local http' => ['http://sroor.test', 'http://demo.sroor.test'],
            'https' => ['https://platform.example.test', 'https://demo.sroor.test'],
            'no scheme defaults to https' => ['not a url', 'https://demo.sroor.test'],
        ];
    }

    #[DataProvider('appUrls')]
    public function test_scheme_follows_the_app_url_outside_production(string $appUrl, string $expectedOrigin): void
    {
        config(['app.url' => $appUrl]);

        $this->assertSame($expectedOrigin, PlatformHosts::origin('demo.sroor.test'));
    }

    public function test_the_tenant_spa_shell_exposes_central_hosts_but_never_admin_hosts(): void
    {
        config([
            'central.admin_domains' => [self::ADMIN_HOST],
            'tenancy.central_domains' => ['localhost', 'sroor.test', self::ADMIN_HOST],
        ]);

        $html = (string) $this->get('http://localhost/login')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="central-domains" content="localhost,sroor.test">', $html);
        $this->assertStringNotContainsString(self::ADMIN_HOST, $html);
        $this->assertStringNotContainsString('name="admin-domains"', $html);
    }

    public function test_the_platform_console_shell_exposes_its_admin_hosts(): void
    {
        config([
            'central.admin_domains' => [self::ADMIN_HOST],
            'tenancy.central_domains' => ['localhost', 'sroor.test', self::ADMIN_HOST],
        ]);

        $html = (string) $this->get('http://'.self::ADMIN_HOST.'/super-admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="admin-domains" content="'.self::ADMIN_HOST.'">', $html);
        $this->assertStringContainsString('<meta name="central-domains" content="localhost,sroor.test">', $html);
    }
}
