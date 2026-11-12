<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Support\Http\CorsOrigins;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SEC-21 (small item): CORS used the framework default `allowed_origins: ['*']`. Exploit before
 * the fix: any web page (Origin: https://evil.example) calling the API got
 * `Access-Control-Allow-Origin: *`, so a script on any site could read every unauthenticated API
 * answer and any answer it could get a token for. Now the allow-list comes from env
 * (CORS_ALLOWED_ORIGINS / CORS_ALLOWED_ORIGIN_PATTERNS, parsed by CorsOrigins); with nothing
 * configured, production allows no cross-origin caller and dev keeps "*".
 *
 * The values below mirror the production setup the owner named (the main host plus the shop
 * subdomains); the subdomain pattern is an assumption to confirm.
 */
final class CorsAllowListTest extends TenantTestCase
{
    private const PUBLIC_URL = '/api/v1/public/plans';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cors.allowed_origins' => CorsOrigins::origins('https://ibraa.app, https://ibraa.app/', []),
            'cors.allowed_origins_patterns' => CorsOrigins::patterns('https://*.ibraa.app'),
        ]);
    }

    public function test_an_allowed_origin_is_echoed_back(): void
    {
        $this->getJson(self::PUBLIC_URL, ['Origin' => 'https://ibraa.app'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://ibraa.app');
    }

    public function test_a_shop_subdomain_matches_the_pattern_including_the_preflight(): void
    {
        $this->getJson(self::PUBLIC_URL, ['Origin' => 'https://cafe-one.ibraa.app'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://cafe-one.ibraa.app');

        $this->call('OPTIONS', self::PUBLIC_URL, [], [], [], [
            'HTTP_ORIGIN' => 'https://cafe-one.ibraa.app',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization, x-tenant',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://cafe-one.ibraa.app');
    }

    /** @return array<string, array{0: string}> */
    public static function disallowedOrigins(): array
    {
        return [
            'other site' => ['https://evil.example'],
            'suffix trick' => ['https://ibraa.app.evil.example'],
            'look-alike' => ['https://evilibraa.app'],
            'nested label' => ['https://a.b.ibraa.app'],
            'plain http' => ['http://ibraa.app'],
            'http subdomain' => ['http://cafe.ibraa.app'],
            'null origin' => ['null'],
        ];
    }

    #[DataProvider('disallowedOrigins')]
    public function test_a_disallowed_origin_gets_no_allow_origin_header(string $origin): void
    {
        $response = $this->getJson(self::PUBLIC_URL, ['Origin' => $origin]);
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'), $origin);

        $preflight = $this->call('OPTIONS', self::PUBLIC_URL, [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $this->assertFalse($preflight->headers->has('Access-Control-Allow-Origin'), $origin);
    }

    public function test_the_config_file_reads_the_lists_from_env_with_a_closed_production_default(): void
    {
        $this->assertSame([], CorsOrigins::origins(null, []));
        $this->assertSame(['*'], CorsOrigins::origins('', ['*']));
        $this->assertSame(['https://a.example', 'https://b.example'], CorsOrigins::origins(' https://a.example ,https://b.example/,, ', []));
        // A wildcard in the exact list would become php-cors' loose ".*": it is dropped.
        $this->assertSame([], CorsOrigins::origins('https://*.example.com', []));
        $this->assertSame([], CorsOrigins::patterns('https://example.com/*, *, javascript:alert(1)'));
        $this->assertSame(1, preg_match(CorsOrigins::patterns('https://*.example.com:8443')[0], 'https://shop.example.com:8443'));
        $this->assertSame(0, preg_match(CorsOrigins::patterns('https://*.example.com:8443')[0], 'https://shop.example.com'));

        $config = require config_path('cors.php');
        $this->assertFalse($config['supports_credentials']);
        $this->assertNotContains('*', (array) $this->productionConfig()['allowed_origins']);
        $this->assertSame([], $this->productionConfig()['allowed_origins_patterns']);
    }

    /**
     * config/cors.php evaluated as in production with no CORS_* variable set.
     *
     * @return array<string, mixed>
     */
    private function productionConfig(): array
    {
        $saved = [];
        foreach (['APP_ENV' => 'production', 'CORS_ALLOWED_ORIGINS' => '', 'CORS_ALLOWED_ORIGIN_PATTERNS' => ''] as $key => $value) {
            $saved[$key] = $_ENV[$key] ?? null;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }

        try {
            return require config_path('cors.php');
        } finally {
            foreach ($saved as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key], $_SERVER[$key]);
                    putenv($key);
                } else {
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                    putenv($key.'='.$value);
                }
            }
        }
    }
}
