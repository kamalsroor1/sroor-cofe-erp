<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\QuickLogin;
use App\Support\QuickLoginGate;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * IDEN-4.1 contract for App\Support\QuickLogin.
 *
 * allowed() is true only when the QUICK_LOGIN_ENABLED flag is strictly true AND the
 * environment is local or testing. Every other environment (production, staging,
 * development, anything unknown) is closed, whatever the flag says.
 * Also pins the token TTL settings and the published Sanctum config, and proves the
 * auth/sanctum config stays cacheable (`php artisan config:cache`).
 */
class QuickLoginTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: string, 2: bool}>
     */
    public static function flagEnvironmentMatrix(): array
    {
        $flags = ['on' => true, 'off' => false, 'string-true' => 'true', 'one' => 1, 'null' => null];
        $environments = ['local', 'testing', 'staging', 'production', 'development', 'prod'];

        $cases = [];
        foreach ($flags as $flagName => $flag) {
            foreach ($environments as $environment) {
                $expected = $flag === true && in_array($environment, ['local', 'testing'], true);
                $cases["flag {$flagName} / env {$environment}"] = [$flag, $environment, $expected];
            }
        }

        return $cases;
    }

    #[DataProvider('flagEnvironmentMatrix')]
    public function test_allowed_matrix(mixed $flag, string $environment, bool $expected): void
    {
        config(['auth.quick_login.enabled' => $flag]);
        $this->app['env'] = $environment;

        $this->assertSame($expected, QuickLogin::allowed());
    }

    public function test_enabled_reflects_only_a_strict_true_flag(): void
    {
        config(['auth.quick_login.enabled' => true]);
        $this->assertTrue(QuickLogin::enabled());

        config(['auth.quick_login.enabled' => 'true']);
        $this->assertFalse(QuickLogin::enabled());

        config(['auth.quick_login.enabled' => false]);
        $this->assertFalse(QuickLogin::enabled());
    }

    public function test_quick_login_is_off_by_default(): void
    {
        $config = $this->freshAuthConfig(['QUICK_LOGIN_ENABLED' => null, 'QUICK_LOGIN_TOKEN_TTL' => null]);

        $this->assertFalse($config['quick_login']['enabled']);
        $this->assertSame(QuickLogin::DEFAULT_TOKEN_TTL_MINUTES, $config['quick_login']['token_ttl_minutes']);
    }

    public function test_empty_env_values_fall_back_to_safe_defaults(): void
    {
        $config = $this->freshAuthConfig([
            'QUICK_LOGIN_ENABLED' => '',
            'QUICK_LOGIN_TOKEN_TTL' => '',
            'TENANT_TOKEN_TTL_MINUTES' => '',
        ]);

        $this->assertFalse($config['quick_login']['enabled']);
        $this->assertSame(480, $config['quick_login']['token_ttl_minutes']);
        $this->assertSame(43200, $config['tokens']['tenant_ttl_minutes']);
    }

    public function test_flag_env_value_is_parsed_as_a_boolean(): void
    {
        foreach (['true' => true, '1' => true, 'on' => true, 'false' => false, '0' => false, 'no' => false, 'off' => false] as $raw => $expected) {
            $config = $this->freshAuthConfig(['QUICK_LOGIN_ENABLED' => (string) $raw]);

            $this->assertSame($expected, $config['quick_login']['enabled'], "QUICK_LOGIN_ENABLED={$raw}");
        }
    }

    public function test_ttl_env_values_are_read_as_integers(): void
    {
        $config = $this->freshAuthConfig([
            'QUICK_LOGIN_TOKEN_TTL' => '60',
            'TENANT_TOKEN_TTL_MINUTES' => '1440',
        ]);

        $this->assertSame(60, $config['quick_login']['token_ttl_minutes']);
        $this->assertSame(1440, $config['tokens']['tenant_ttl_minutes']);
    }

    public function test_tenant_token_ttl_defaults_to_thirty_days(): void
    {
        $config = $this->freshAuthConfig(['TENANT_TOKEN_TTL_MINUTES' => null]);

        // CTO Q-B10: tenant tokens live 30 days (sliding renewal is IDEN-2.2).
        $this->assertSame(30 * 24 * 60, $config['tokens']['tenant_ttl_minutes']);
    }

    public function test_token_ttl_minutes_falls_back_when_misconfigured(): void
    {
        config(['auth.quick_login.token_ttl_minutes' => 0]);
        $this->assertSame(QuickLogin::DEFAULT_TOKEN_TTL_MINUTES, QuickLogin::tokenTtlMinutes());

        config(['auth.quick_login.token_ttl_minutes' => -5]);
        $this->assertSame(QuickLogin::DEFAULT_TOKEN_TTL_MINUTES, QuickLogin::tokenTtlMinutes());

        config(['auth.quick_login.token_ttl_minutes' => 30]);
        $this->assertSame(30, QuickLogin::tokenTtlMinutes());
        $this->assertSame(30, QuickLoginGate::tokenTtlMinutes());
    }

    public function test_gate_is_closed_outside_local_and_testing_even_with_flag_on(): void
    {
        config(['auth.quick_login.enabled' => true]);
        // Only the flag is read; no tenant database is touched. The app is rebuilt per test.
        tenancy()->initialized = true;

        $this->app['env'] = 'testing';
        $this->assertTrue(QuickLoginGate::allowed(), 'Sanity: testing + tenant context + flag on is open.');

        // The tenant-context gate builds on QuickLogin::allowed(), so staging stays closed.
        $this->app['env'] = 'staging';
        $this->assertFalse(QuickLoginGate::allowed());
    }

    public function test_gate_requires_tenant_context_even_when_allowed(): void
    {
        config(['auth.quick_login.enabled' => true]);
        $this->app['env'] = 'testing';

        $this->assertTrue(QuickLogin::allowed());
        $this->assertFalse(tenancy()->initialized);
        $this->assertFalse(QuickLoginGate::allowed(), 'No tenancy initialised: the gate must stay closed.');
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy')) {
            tenancy()->initialized = false;
        }

        parent::tearDown();
    }

    public function test_sanctum_config_is_published_with_bearer_only_settings(): void
    {
        $this->assertFileExists(config_path('sanctum.php'));

        // No session guard fallback: only bearer personal access tokens authenticate.
        $this->assertSame([], config('sanctum.guard'));
        // Each token's own expires_at is the single source of truth.
        $this->assertNull(config('sanctum.expiration'));
    }

    public function test_auth_and_sanctum_config_are_cacheable(): void
    {
        foreach (['auth', 'sanctum'] as $file) {
            $config = require config_path($file.'.php');

            $this->assertIsArray($config);
            $this->assertCacheable($config, $file);

            // config:cache writes var_export() output and loads it back.
            $exported = var_export($config, true);
            $reloaded = eval('return '.$exported.';');
            $this->assertSame($config, $reloaded, "config/{$file}.php does not round-trip through var_export.");
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    private function assertCacheable(array $config, string $path): void
    {
        foreach ($config as $key => $value) {
            $this->assertNotInstanceOf(Closure::class, $value, "Closure at {$path}.{$key}");
            $this->assertIsNotObject($value, "Object at {$path}.{$key}");
            $this->assertIsNotResource($value, "Resource at {$path}.{$key}");

            if (is_array($value)) {
                $this->assertCacheable($value, $path.'.'.$key);
            }
        }
    }

    /**
     * Re-evaluate config/auth.php with the given env overrides (null = unset).
     *
     * @param  array<string, string|null>  $env
     * @return array<string, mixed>
     */
    private function freshAuthConfig(array $env): array
    {
        $backup = [];
        foreach ($env as $name => $value) {
            $backup[$name] = [
                'server' => $_SERVER[$name] ?? null,
                'env' => $_ENV[$name] ?? null,
                'putenv' => getenv($name),
            ];
            $this->setEnv($name, $value);
        }

        try {
            /** @var array<string, mixed> $config */
            $config = require config_path('auth.php');

            return $config;
        } finally {
            foreach ($backup as $name => $previous) {
                $this->restoreEnv($name, $previous);
            }
        }
    }

    private function setEnv(string $name, ?string $value): void
    {
        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);

            return;
        }

        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv($name.'='.$value);
    }

    /**
     * @param  array{server: mixed, env: mixed, putenv: string|false}  $previous
     */
    private function restoreEnv(string $name, array $previous): void
    {
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);

        if ($previous['server'] !== null) {
            $_SERVER[$name] = $previous['server'];
        }
        if ($previous['env'] !== null) {
            $_ENV[$name] = $previous['env'];
        }
        if ($previous['putenv'] !== false) {
            putenv($name.'='.$previous['putenv']);
        }
    }
}
