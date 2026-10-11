<?php

declare(strict_types=1);

namespace Tests\Unit\Support\AppReleases;

use App\Support\AppReleases\SemverCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SemverCodeTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function codes(): array
    {
        return [
            'spec example' => ['1.2.3', 1002003],
            'first patch' => ['0.0.1', 1],
            'zero' => ['0.0.0', 0],
            'major only' => ['2.0.0', 2000000],
            'minor carries' => ['1.10.0', 1010000],
            'largest' => ['999.999.999', 999999999],
        ];
    }

    #[DataProvider('codes')]
    public function test_name_maps_to_code_and_back(string $name, int $code): void
    {
        $this->assertSame($code, SemverCode::fromName($name));
        $this->assertSame($name, SemverCode::toName($code));
        $this->assertTrue(SemverCode::isValid($name));
    }

    public function test_ordering_matches_semver_ordering(): void
    {
        $this->assertLessThan(SemverCode::fromName('1.10.0'), SemverCode::fromName('1.9.9'));
        $this->assertLessThan(SemverCode::fromName('2.0.0'), SemverCode::fromName('1.999.999'));
        $this->assertLessThan(SemverCode::fromName('1.0.10'), SemverCode::fromName('1.0.9'));
    }

    public function test_largest_code_fits_a_signed_32_bit_int(): void
    {
        $this->assertLessThanOrEqual(2147483647, SemverCode::fromName('999.999.999'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidNames(): array
    {
        return [
            'two parts' => ['1.2'],
            'four parts' => ['1.2.3.4'],
            'pre-release' => ['1.2.3-beta'],
            'build metadata' => ['1.2.3+5'],
            'v prefix' => ['v1.2.3'],
            'four digits' => ['1000.0.0'],
            'empty' => [''],
            'spaces' => [' 1.2.3'],
            'arabic digits' => ['١.٢.٣'],
            'trailing newline' => ["1.2.3\n"],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected(string $name): void
    {
        $this->assertFalse(SemverCode::isValid($name));
        $this->assertNull(SemverCode::tryFromName($name));

        $this->expectException(InvalidArgumentException::class);
        SemverCode::fromName($name);
    }

    public function test_out_of_range_code_has_no_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SemverCode::toName(1_000_000_000);
    }
}
