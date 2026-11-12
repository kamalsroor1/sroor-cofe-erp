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
    }

    /**
     * Security review of SEC-21 (8): "*" was the default for every APP_ENV other than exactly
     * "production" (staging, "Production", "prod"…). Now only local and testing get it.
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function environments(): array
    {
        return [
            'local' => ['local', ['*']],
            'testing' => ['testing', ['*']],
            'production' => ['production', []],
            'staging' => ['staging', []],
            'mis-cased production' => ['Production', []],
            'prod' => ['prod', []],
            'empty' => ['', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('environments')]
    public function test_the_wildcard_default_is_for_local_and_testing_only(string $env, array $expected): void
    {
        $config = $this->configFor($env);
        $this->assertSame($expected, $config['allowed_origins'], $env);
        $this->assertSame([], $config['allowed_origins_patterns']);
    }

    public function test_configured_origins_win_in_every_environment(): void
    {
        $config = $this->configFor('local', 'https://ibraa.app', 'https://*.ibraa.app');
        $this->assertSame(['https://ibraa.app'], $config['allowed_origins']);
        $this->assertCount(1, $config['allowed_origins_patterns']);
    }

    /**
     * config/cors.php evaluated with the given APP_ENV and CORS_* variables.
     *
     * @return array<string, mixed>
     */
    private function configFor(string $appEnv, string $origins = '', string $patterns = ''): array
    {
        $saved = [];
        foreach (['APP_ENV' => $appEnv, 'CORS_ALLOWED_ORIGINS' => $origins, 'CORS_ALLOWED_ORIGIN_PATTERNS' => $patterns] as $key => $value) {
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
