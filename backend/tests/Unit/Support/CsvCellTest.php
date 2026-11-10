<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Csv\CsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvCellTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function cells(): array
    {
        return [
            'plain' => ['abc', '"abc"'],
            'null' => [null, '""'],
            'number' => [12.5, '"12.5"'],
            'inner quote doubled' => ['a "b" c', '"a ""b"" c"'],
            'comma and newline stay inside quotes' => ["a,b\nc", "\"a,b\nc\""],
            'equals formula' => ['=SUM(A1:A2)', '"\'=SUM(A1:A2)"'],
            'plus formula' => ['+1+1', '"\'+1+1"'],
            'minus formula' => ['-2+3', '"\'-2+3"'],
            'at formula' => ['@cmd', '"\'@cmd"'],
            'tab prefix' => ["\tx", "\"'\tx\""],
            'carriage return prefix' => ["\rx", "\"'\rx\""],
            'formula with quote' => ['=HYPERLINK("http://x")', '"\'=HYPERLINK(""http://x"")"'],
            'arabic untouched' => ['كافيه الأهرام', '"كافيه الأهرام"'],
            'leading space formula' => [' =1+1', '"\' =1+1"'],
            'leading newline formula' => ["\n=1+1", "\"'\n=1+1\""],
            'negative money stays numeric' => ['-5.000', '"-5.000"'],
            'negative integer stays numeric' => ['-5', '"-5"'],
            'minus text is prefixed' => ['-5abc', '"\'-5abc"'],
            'dash inside is fine' => ['a-b', '"a-b"'],
        ];
    }

    #[DataProvider('cells')]
    public function test_escape(mixed $input, string $expected): void
    {
        $this->assertSame($expected, CsvCell::escape($input));
    }

    public function test_row_joins_escaped_cells_with_crlf(): void
    {
        $this->assertSame("\"a\",\"'=b\",\"\"\r\n", CsvCell::row(['a', '=b', null]));
    }
}
