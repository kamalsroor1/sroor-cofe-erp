<?php

declare(strict_types=1);

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Static guard: every literal translation key the SPA asks for
 * ($t('file.key'), t('file.key'), trans('file.key'), __('file.key') in
 * resources/js) must resolve to a string in BOTH lang/ar and lang/en.
 *
 * A missing key renders as the raw key on screen (trans() returns the key),
 * so this test fails the build instead of the user seeing "reports.total_sales".
 *
 * Dynamic keys (template literals with ${...}, variables) cannot be checked
 * statically and are skipped by the pattern.
 */
class SpaTranslationKeysExistTest extends TestCase
{
    private const KEY_CALL_PATTERN = '/(?:\$t|(?<![\w$.])t|(?<![\w$.])trans|(?<![\w$])__)\(\s*([\'"`])([a-z][a-z0-9_]*(?:\.[A-Za-z0-9_]+)+)\1/';

    /** @var array<string, array<string, mixed>|null> */
    private array $langCache = [];

    public function test_every_spa_translation_key_exists_in_ar_and_en(): void
    {
        $used = $this->collectUsedKeys();

        $this->assertNotEmpty($used, 'Scanner found no translation keys in resources/js; the pattern is probably broken.');

        $missing = [];
        foreach ($used as $key => $files) {
            foreach (['ar', 'en'] as $locale) {
                if (! $this->resolvesToString($locale, $key)) {
                    $missing[] = sprintf('%s [missing in %s] used in %s', $key, $locale, implode(', ', array_slice($files, 0, 3)));
                }
            }
        }

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Translation keys used in resources/js but missing from backend/lang. Add them to lang/ar and lang/en, then run php artisan lang:export:\n".implode("\n", $missing)
        );
    }

    /** @return array<string, list<string>> key => relative files using it */
    private function collectUsedKeys(): array
    {
        $jsRoot = resource_path('js');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($jsRoot, FilesystemIterator::SKIP_DOTS));

        $used = [];
        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (! preg_match('/\.(vue|js|ts)$/', $path) || str_contains($path, 'defaultTranslations')) {
                continue;
            }

            preg_match_all(self::KEY_CALL_PATTERN, (string) file_get_contents($path), $matches);

            $relative = ltrim(substr($path, strlen(str_replace('\\', '/', $jsRoot))), '/');
            foreach ($matches[2] as $key) {
                $used[$key][$relative] = $relative;
            }
        }

        ksort($used);

        return array_map(static fn (array $files): array => array_values($files), $used);
    }

    private function resolvesToString(string $locale, string $key): bool
    {
        [$file, $rest] = explode('.', $key, 2);

        $value = $this->loadLangFile($locale, $file);
        if ($value === null) {
            return false;
        }

        foreach (explode('.', $rest) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return is_string($value) && trim($value) !== '';
    }

    /** @return array<string, mixed>|null */
    private function loadLangFile(string $locale, string $file): ?array
    {
        $cacheKey = "{$locale}/{$file}";

        if (! array_key_exists($cacheKey, $this->langCache)) {
            $path = lang_path("{$locale}/{$file}.php");
            $data = is_file($path) ? require $path : null;
            $this->langCache[$cacheKey] = is_array($data) ? $data : null;
        }

        return $this->langCache[$cacheKey];
    }
}
