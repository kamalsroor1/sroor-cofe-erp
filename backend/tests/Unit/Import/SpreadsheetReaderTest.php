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
        $max = SpreadsheetReader::MAX_DATA_ROWS;
        $result = (new SpreadsheetReader)->read($this->write($extension, $this->sheet($max)), $extension);

        $this->assertCount($max, $result['rows']);
        $this->assertSame(['name', 'icon'], $result['headers']);
        $this->assertSame(['Cat '.$max, 'x'], $result['rows'][$max - 1]['cells']);
    }

    #[DataProvider('formats')]
    public function test_one_row_past_the_limit_is_a_too_many_rows_validation_error(string $extension): void
    {
        $max = SpreadsheetReader::MAX_DATA_ROWS;

        try {
            (new SpreadsheetReader)->read($this->write($extension, $this->sheet($max + 1)), $extension);
            $this->fail('A file with MAX_DATA_ROWS + 1 data rows must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame([__('import.errors.too_many_rows', ['max' => $max])], $e->errors()['file']);
        }
    }

    public function test_blank_lines_in_the_middle_do_not_count_and_do_not_push_rows_out(): void
    {
        // MAX_DATA_ROWS data rows + blank lines in between: a cap on raw lines would cut the tail.
        $max = SpreadsheetReader::MAX_DATA_ROWS;
        $lines = $this->sheet($max, 550);
        $this->assertGreaterThan($max + 1, count($lines));

        $result = (new SpreadsheetReader)->read($this->write('csv', $lines), 'csv');

        $this->assertCount($max, $result['rows']);
        $this->assertSame(['Cat '.$max, 'x'], $result['rows'][$max - 1]['cells']);
        // row_number is the line the user sees (header = 1, blank lines included).
        $this->assertSame(count($lines), $result['rows'][$max - 1]['row_number']);
    }

    public function test_blank_lines_never_make_a_short_file_too_long(): void
    {
        // More blank lines than the data-row limit: only the two real rows count.
        $lines = [['name', 'icon'], ['A', '']];
        for ($i = 0; $i < SpreadsheetReader::MAX_DATA_ROWS + 1000; $i++) {
            $lines[] = ['', ''];
        }
        $lines[] = ['B', ''];

        $result = (new SpreadsheetReader)->read($this->write('csv', $lines), 'csv');

        $this->assertSame([['A', ''], ['B', '']], array_column($result['rows'], 'cells'));
    }

    #[DataProvider('formats')]
    public function test_blank_lines_in_the_middle_of_an_over_limit_file_still_fail(string $extension): void
    {
        $max = SpreadsheetReader::MAX_DATA_ROWS;

        try {
            (new SpreadsheetReader)->read($this->write($extension, $this->sheet($max + 100, 600)), $extension);
            $this->fail('An over-limit file must be refused even with blank lines in between.');
        } catch (ValidationException $e) {
            $this->assertSame([__('import.errors.too_many_rows', ['max' => $max])], $e->errors()['file']);
        }
    }
}
