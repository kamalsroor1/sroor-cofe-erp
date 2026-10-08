<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Providers\AppServiceProvider;
use App\Support\QuickLogin;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

/**
 * IDEN-4.1 / CTO Q-B11: quick-login switched on in production must stop the app
 * from serving HTTP (fail-fast with a clear message), not just log.
 * Console processes still boot so an operator can run config:clear / config:cache.
 */
class QuickLoginProductionGuardTest extends TestCase
{
    public function test_guard_throws_for_http_in_production_with_flag_on(): void
    {
        config(['auth.quick_login.enabled' => true]);

        try {
            QuickLogin::guardAgainstProduction($this->fakeApp(production: true, console: false));
            $this->fail('The production boot guard did not throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('QUICK_LOGIN_ENABLED', $e->getMessage());
            $this->assertStringContainsString('production', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{0: mixed, 1: bool, 2: bool}>
     */
    public static function safeCombinations(): array
    {
        return [
            'production, flag off, http' => [false, true, false],
            'production, flag null, http' => [null, true, false],
            'production, flag on, console' => [true, true, true],
            'non-production, flag on, http' => [true, false, false],
            'non-production, flag off, http' => [false, false, false],
        ];
    }

    #[DataProvider('safeCombinations')]
    public function test_guard_does_not_throw_for_safe_combinations(mixed $flag, bool $production, bool $console): void
    {
        config(['auth.quick_login.enabled' => $flag]);

        QuickLogin::guardAgainstProduction($this->fakeApp($production, $console));

        $this->addToAssertionCount(1);
    }

    public function test_app_service_provider_boot_runs_the_guard(): void
    {
        config(['auth.quick_login.enabled' => true]);
        $this->app['env'] = 'production';
        $this->servingHttp();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('QUICK_LOGIN_ENABLED');

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_app_service_provider_boots_in_production_when_flag_is_off(): void
    {
        config(['auth.quick_login.enabled' => false]);
        $this->app['env'] = 'production';
        $this->servingHttp();

        (new AppServiceProvider($this->app))->boot();

        $this->addToAssertionCount(1);
    }

    public function test_production_never_allows_quick_login_even_from_console(): void
    {
        config(['auth.quick_login.enabled' => true]);
        $this->app['env'] = 'production';

        $this->assertFalse(QuickLogin::allowed());
    }

    private function fakeApp(bool $production, bool $console): Application
    {
        $app = $this->createStub(Application::class);
        $app->method('environment')->willReturnCallback(
            fn (string ...$environments): bool => $environments === ['production'] ? $production : false,
        );
        $app->method('runningInConsole')->willReturn($console);

        return $app;
    }

    /**
     * PHPUnit always runs in the console; pretend this process is serving HTTP.
     * The test application is rebuilt for every test, so nothing leaks.
     */
    private function servingHttp(): void
    {
        $property = new ReflectionProperty($this->app, 'isRunningInConsole');
        $property->setValue($this->app, false);
    }
}
