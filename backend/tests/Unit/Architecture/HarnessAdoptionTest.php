<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Tests\TenantTestCase;

/**
 * QA-4 (W2 batch 4, lane 4E): the multi-tenant harness is the only way an API test may
 * reach tenant routes. Tests\TestCase copies the tenant migrations into the central DB,
 * so a test on it can pass while production (database-per-tenant) breaks. This guard
 * fails when:
 *
 *  1. a class in tests/Feature/Api does not extend Tests\TenantTestCase;
 *  2. any tests/Feature/** file calls a tenant `/api/v1/…` path without extending
 *     Tests\TenantTestCase. Central/public paths (CENTRAL_PATH_PREFIXES) are exempt;
 *  3. a test under tests/Feature/Api is skipped or marked incomplete.
 *
 * ALLOWLIST holds justified exceptions to rule 2 only and must only shrink: an entry
 * whose file now extends TenantTestCase, or no longer calls a tenant path, fails the test
 * so it gets removed.
 */
final class HarnessAdoptionTest extends TestCase
{
    private const TESTS_DIR = __DIR__.'/../..';

    /**
     * First path segment(s) after /api/v1/ that are served by the central app or are
     * public tenant-agnostic endpoints, so a non-harness test may call them.
     */
    private const CENTRAL_PATH_PREFIXES = [
        'super-admin',
        'central',
        'ping',
        'system/translations',
        'branding',
    ];

    /**
     * path relative to tests/Feature => why it may call tenant /api/v1 paths without the harness.
     * Empty today: every such file has been migrated (AuthApiTest was the last one).
     *
     * @var array<string, string>
     */
    private const ALLOWLIST = [];

    public function test_every_api_feature_test_extends_tenant_test_case(): void
    {
        $offenders = [];

        foreach ($this->phpFiles('Feature/Api') as $relative => $path) {
            foreach ($this->declaredClasses($path) as $class) {
                if (! $this->extendsTenantTestCase($class)) {
                    $offenders[] = $relative.' ('.$class.')';
                }
            }
        }

        $this->assertNotEmpty(iterator_to_array($this->phpFiles('Feature/Api')), 'tests/Feature/Api must not be empty (path resolution broken?)');
        $this->assertSame([], $offenders, "These tests/Feature/Api classes must extend Tests\\TenantTestCase:\n".implode("\n", $offenders));
    }

    public function test_feature_tests_calling_tenant_api_paths_extend_tenant_test_case(): void
    {
        $offenders = [];
        $stale = [];
        $allowlist = $this->allowlist();

        foreach ($this->phpFiles('Feature') as $relative => $path) {
            $tenantPaths = $this->tenantApiPaths((string) file_get_contents($path));
            $classes = $this->declaredClasses($path);
            $usesHarness = $classes !== [] && array_filter($classes, fn (string $c): bool => ! $this->extendsTenantTestCase($c)) === [];
            $needsHarness = $tenantPaths !== [] && ! $usesHarness;

            if (array_key_exists($relative, $allowlist)) {
                if (! $needsHarness) {
                    $stale[] = $relative;
                }

                continue;
            }

            if ($needsHarness) {
                $offenders[] = $relative.' calls '.implode(', ', array_slice($tenantPaths, 0, 5));
            }
        }

        $this->assertSame([], $offenders, "These Feature tests call tenant /api/v1 paths without extending Tests\\TenantTestCase:\n".implode("\n", $offenders));
        $this->assertSame([], $stale, "Remove these stale ALLOWLIST entries:\n".implode("\n", $stale));
    }

    public function test_no_skipped_or_incomplete_tests_under_feature_api(): void
    {
        $offenders = [];

        foreach ($this->phpFiles('Feature/Api') as $relative => $path) {
            foreach (file($path) ?: [] as $index => $line) {
                if (preg_match('/\b(markTestSkipped|markTestIncomplete)\s*\(/', $line) === 1) {
                    $offenders[] = $relative.':'.($index + 1).'  '.trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, "Skipped/incomplete tests are not allowed under tests/Feature/Api:\n".implode("\n", $offenders));
    }

    public function test_tenant_path_detection_respects_central_prefixes(): void
    {
        $source = <<<'PHP'
            $this->getJson('/api/v1/ping');
            $this->getJson('/api/v1/system/translations?locale=ar');
            $this->getJson("/api/v1/branding");
            $this->postJson('/api/v1/super-admin/tenants', []);
            $this->getJson('/api/v1/central/tenants/resolve?code=x');
            $this->getJson('/api/v1/system/context');
            $this->getJson('/api/v1/items/'.$id);
            $this->getJson($this->tenantUrl($t, 'api/v1/auth/me'));
            PHP;

        $this->assertSame(['auth/me', 'items/', 'system/context'], $this->tenantApiPaths($source));
    }

    /**
     * Read through a method so the list keeps its declared type while it is empty.
     *
     * @return array<string, string>
     */
    private function allowlist(): array
    {
        return self::ALLOWLIST;
    }

    /**
     * Distinct tenant `/api/v1/…` paths in a source file (central/public prefixes removed).
     *
     * @return list<string>
     */
    private function tenantApiPaths(string $source): array
    {
        preg_match_all('#[\'"]/?api/v1/([A-Za-z0-9_\-/{}.]*)#', $source, $matches);

        $paths = [];
        foreach ($matches[1] as $path) {
            if ($this->isCentralPath($path)) {
                continue;
            }
            $paths[$path] = true;
        }

        $paths = array_keys($paths);
        sort($paths);

        return $paths;
    }

    private function isCentralPath(string $path): bool
    {
        foreach (self::CENTRAL_PATH_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/') || str_starts_with($path, $prefix.'?')) {
                return true;
            }
        }

        return false;
    }

    /** @param class-string $class */
    private function extendsTenantTestCase(string $class): bool
    {
        return is_subclass_of($class, TenantTestCase::class);
    }

    /**
     * Fully qualified, non-abstract classes declared in a test file (autoloaded via composer).
     *
     * @return list<class-string>
     */
    private function declaredClasses(string $path): array
    {
        $source = (string) file_get_contents($path);

        if (preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1) {
            return [];
        }

        preg_match_all('/^(?:final\s+|readonly\s+)*class\s+(\w+)/m', $source, $matches);

        $classes = [];
        foreach ($matches[1] as $short) {
            $class = trim($namespace[1]).'\\'.$short;
            $this->assertTrue(class_exists($class), "{$class} declared in {$path} is not autoloadable");
            /** @var class-string $class */
            if (! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @return iterable<string, string> path relative to tests/Feature (or tests/Feature/Api) => absolute path
     */
    private function phpFiles(string $directory): iterable
    {
        $root = realpath(self::TESTS_DIR.'/'.$directory);
        $this->assertNotFalse($root, "tests/{$directory} not found");

        $base = realpath(self::TESTS_DIR.'/Feature');
        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen((string) $base) + 1));
            $files[$relative] = $file->getPathname();
        }

        ksort($files);

        return $files;
    }
}
