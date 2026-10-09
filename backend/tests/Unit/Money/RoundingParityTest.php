<?php

declare(strict_types=1);

namespace Tests\Unit\Money;

use App\Support\Money\Decimal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SETG-13: the PHP rounding helper produces, character for character, the strings in
 * the shared vectors file. tests/js/decimal-rounding.test.js runs the same file against
 * resources/js/helpers/decimal.js, so client and server can never drift apart.
 */
final class RoundingParityTest extends TestCase
{
    private const VECTORS = __DIR__.'/../../Fixtures/rounding-vectors.json';

    /**
     * @return iterable<string, array{0: string, 1: list<string>, 2: string|int}>
     */
    public static function vectors(): iterable
    {
        $decoded = json_decode((string) file_get_contents(self::VECTORS), true, 512, JSON_THROW_ON_ERROR);

        foreach ($decoded['vectors'] as $i => $vector) {
            $label = sprintf('#%d %s(%s)', $i, $vector['op'], implode(', ', $vector['args']));

            yield $label => [$vector['op'], $vector['args'], $vector['expected']];
        }
    }

    /**
     * @param  list<string>  $args
     */
    #[DataProvider('vectors')]
    public function test_php_helper_matches_the_shared_vector(string $op, array $args, string|int $expected): void
    {
        $actual = match ($op) {
            'normalize' => Decimal::normalize($args[0]),
            'add' => Decimal::add($args[0], $args[1]),
            'sub' => Decimal::sub($args[0], $args[1]),
            'mul' => Decimal::mul($args[0], $args[1]),
            'percent' => Decimal::percent($args[0], $args[1]),
            'cmp' => Decimal::cmp($args[0], $args[1]),
            default => $this->fail("Unknown op [{$op}] in the vectors file."),
        };

        $this->assertSame($expected, $actual);
    }

    public function test_the_vectors_cover_ties_and_negatives(): void
    {
        $raw = (string) file_get_contents(self::VECTORS);

        $this->assertStringContainsString('"0.0005"', $raw);
        $this->assertStringContainsString('"-0.0005"', $raw);
        $this->assertGreaterThanOrEqual(40, count(json_decode($raw, true)['vectors']));
    }

    public function test_round_settles_exact_values_half_up_at_any_scale(): void
    {
        $this->assertSame('17.203', Decimal::round('17.203125'));
        $this->assertSame('3.15', Decimal::round('3.145', 2));
        $this->assertSame('-3.15', Decimal::round('-3.145', 2));
        $this->assertSame('3', Decimal::round('2.5', 0));
        $this->assertSame('0.00', Decimal::round('-0.004', 2));
    }

    public function test_results_are_always_scale_three_strings(): void
    {
        foreach (['0', '1', '-1', '0.1', '123456789.123456'] as $value) {
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{3}$/', Decimal::normalize($value));
        }
    }
}
