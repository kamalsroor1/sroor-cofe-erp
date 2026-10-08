<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P0-POS-0: ar and en must expose the same key set for the POS / invoice
 * translation files, so new checkout validation and idempotency messages
 * never fall back to a raw key in one locale.
 */
class LangKeyParityTest extends TestCase
{
    public static function langFileProvider(): array
    {
        return [
            'invoices' => ['invoices'],
            'pos' => ['pos'],
        ];
    }

    #[DataProvider('langFileProvider')]
    public function test_ar_and_en_have_identical_key_sets(string $file): void
    {
        $ar = $this->flatten($this->load('ar', $file));
        $en = $this->flatten($this->load('en', $file));

        $missingInEn = array_values(array_diff(array_keys($ar), array_keys($en)));
        $missingInAr = array_values(array_diff(array_keys($en), array_keys($ar)));

        $this->assertSame([], $missingInEn, "Keys present in lang/ar/{$file}.php but missing in lang/en/{$file}.php");
        $this->assertSame([], $missingInAr, "Keys present in lang/en/{$file}.php but missing in lang/ar/{$file}.php");
    }

    #[DataProvider('langFileProvider')]
    public function test_no_translation_value_is_empty(string $file): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach ($this->flatten($this->load($locale, $file)) as $key => $value) {
                $this->assertIsString($value, "lang/{$locale}/{$file}.php [{$key}] must be a string");
                $this->assertNotSame('', trim($value), "lang/{$locale}/{$file}.php [{$key}] is empty");
            }
        }
    }

    private function load(string $locale, string $file): array
    {
        $path = lang_path("{$locale}/{$file}.php");
        $this->assertFileExists($path);

        $data = require $path;
        $this->assertIsArray($data, "{$path} must return an array");

        return $data;
    }

    /** @return array<string,mixed> */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $out += $this->flatten($value, $full);
            } else {
                $out[$full] = $value;
            }
        }

        return $out;
    }
}
