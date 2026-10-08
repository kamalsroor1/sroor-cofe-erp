<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Services\Pos\InvalidScaleBarcodeException;
use App\Services\Pos\ScaleBarcodeConfig;
use App\Services\Pos\ScaleBarcodeParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * POSB-2: the scale-label parser is pure (no DB, no container) and is driven by the
 * shared vectors file tests/Fixtures/scale-barcodes.json, which the Phase 2 frontend
 * parser must also pass.
 */
final class ScaleBarcodeParserTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../../Fixtures/scale-barcodes.json';

    /**
     * @return array<string, mixed>
     */
    private static function fixture(): array
    {
        $raw = file_get_contents(self::FIXTURE);
        self::assertIsString($raw);

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2: array<string, mixed>|null}>
     */
    public static function vectors(): iterable
    {
        $fixture = self::fixture();

        foreach ($fixture['cases'] as $case) {
            yield (string) $case['name'] => [
                array_merge($fixture['defaults'], $case['config']),
                (string) $case['barcode'],
                $case['expected'],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $expected
     */
    #[DataProvider('vectors')]
    public function test_parses_shared_vector(array $config, string $barcode, ?array $expected): void
    {
        $parser = new ScaleBarcodeParser;
        $scaleConfig = ScaleBarcodeConfig::fromArray($config);

        if (is_array($expected) && isset($expected['error'])) {
            try {
                $parser->parse($barcode, $scaleConfig);
                $this->fail('Expected InvalidScaleBarcodeException for '.$barcode);
            } catch (InvalidScaleBarcodeException $e) {
                $this->assertSame($expected['error'], $e->translationKey);
            }

            return;
        }

        $result = $parser->parse($barcode, $scaleConfig);

        if ($expected === null) {
            $this->assertNull($result);

            return;
        }

        $this->assertNotNull($result);
        $this->assertSame($expected, $result->toArray());
    }

    public function test_amounts_are_strings_with_scale_three(): void
    {
        $result = (new ScaleBarcodeParser)->parse('2034567012501', ScaleBarcodeConfig::defaults()->with(['scale_barcode_enabled' => true]));

        $this->assertNotNull($result);
        $this->assertIsString($result->quantity);
        $this->assertMatchesRegularExpression('/^\d+\.\d{3}$/', (string) $result->quantity);
    }

    public function test_defaults_match_the_fixture_defaults_except_enabled_flag(): void
    {
        $defaults = ScaleBarcodeConfig::defaults()->toArray();
        $fixtureDefaults = self::fixture()['defaults'];

        // Scale labels are opt-in per store; everything else matches the fixture defaults.
        $this->assertFalse($defaults['scale_barcode_enabled']);
        $fixtureDefaults['scale_barcode_enabled'] = false;

        $this->assertSame($fixtureDefaults, $defaults);
    }

    public function test_every_vector_is_a_string_barcode(): void
    {
        foreach (self::fixture()['cases'] as $case) {
            $this->assertIsString($case['barcode'], (string) $case['name']);
        }
    }
}
