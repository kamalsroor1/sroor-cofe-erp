<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Services\Import\SpreadsheetReader;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * IMP-02: the row limit counts DATA rows (blank lines excluded) and one row past it is refused
 * for CSV and XLSX alike; nothing is ever cut silently.
 */
class SpreadsheetReaderTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * @param  list<list<string>>  $lines
     */
    private function write(string $extension, array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'srt').'.'.$extension;
        $this->files[] = $path;

        if ($extension === 'xlsx') {
            $writer = new Writer;
            $writer->openToFile($path);
            foreach ($lines as $line) {
                $writer->addRow(Row::fromValues($line));
            }
            $writer->close();

            return $path;
        }

        $handle = fopen($path, 'w');
        foreach ($lines as $line) {
            fputcsv($handle, $line, ',', '"', '');
        }
        fclose($handle);

        return $path;
    }

    /**
     * @return list<list<string>>
     */
    private function sheet(int $dataRows, int $blankEvery = 0): array
    {
        $lines = [['name', 'icon']];
        for ($i = 1; $i <= $dataRows; $i++) {
            if ($blankEvery > 0 && $i % $blankEvery === 0) {
                $lines[] = ['', ''];
            }
            $lines[] = ['Cat '.$i, 'x'];
        }

        return $lines;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function formats(): array
    {
        return ['csv' => ['csv'], 'xlsx' => ['xlsx']];
    }

    #[DataProvider('formats')]
    public function test_exactly_the_limit_is_read_completely(string $extension): void
    {
        $result = (new SpreadsheetReader)->read($this->write($extension, $this->sheet(SpreadsheetReader::MAX_DATA_ROWS)), $extension);

        $this->assertCount(5000, $result['rows']);
        $this->assertSame(['name', 'icon'], $result['headers']);
        $this->assertSame(['Cat 5000', 'x'], $result['rows'][4999]['cells']);
    }

    #[DataProvider('formats')]
    public function test_one_row_past_the_limit_is_a_too_many_rows_validation_error(string $extension): void
    {
        try {
            (new SpreadsheetReader)->read($this->write($extension, $this->sheet(SpreadsheetReader::MAX_DATA_ROWS + 1)), $extension);
            $this->fail('A file with 5,001 data rows must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame([__('import.errors.too_many_rows', ['max' => 5000])], $e->errors()['file']);
        }
    }

    public function test_blank_lines_in_the_middle_do_not_count_and_do_not_push_rows_out(): void
    {
        // 5,000 data rows + 9 blank lines in between: the old line cap read only 4,992 of them.
        $lines = $this->sheet(SpreadsheetReader::MAX_DATA_ROWS, 550);
        $this->assertGreaterThan(5001, count($lines));

        $result = (new SpreadsheetReader)->read($this->write('csv', $lines), 'csv');

        $this->assertCount(5000, $result['rows']);
        $this->assertSame(['Cat 5000', 'x'], $result['rows'][4999]['cells']);
        // row_number is the line the user sees (header = 1, blank lines included).
        $this->assertSame(count($lines), $result['rows'][4999]['row_number']);
    }

    public function test_blank_lines_never_make_a_short_file_too_long(): void
    {
        $lines = [['name', 'icon'], ['A', '']];
        for ($i = 0; $i < 6000; $i++) {
            $lines[] = ['', ''];
        }
        $lines[] = ['B', ''];

        $result = (new SpreadsheetReader)->read($this->write('csv', $lines), 'csv');

        $this->assertSame([['A', ''], ['B', '']], array_column($result['rows'], 'cells'));
    }

    #[DataProvider('formats')]
    public function test_blank_lines_in_the_middle_of_an_over_limit_file_still_fail(string $extension): void
    {
        $this->expectException(ValidationException::class);

        (new SpreadsheetReader)->read($this->write($extension, $this->sheet(5100, 600)), $extension);
    }
}
