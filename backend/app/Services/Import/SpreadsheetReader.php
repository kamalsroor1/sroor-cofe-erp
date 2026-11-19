<?php

declare(strict_types=1);

namespace App\Services\Import;

use DateTimeInterface;
use Generator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * PRD-06: reads the first sheet of an .xlsx or a .csv into plain text cells.
 *
 * CSV: UTF-8 with or without BOM, falls back to Windows-1256 for legacy Arabic Excel exports,
 * delimiter `,` `;` or tab detected from the first line; UTF-16 with a BOM (IMP-01). Fully empty rows
 * are skipped but `row_number` always matches the line the user sees in the sheet. IMP-01: the header
 * is searched for in the first non-blank lines, so a title above it is no longer read as the header.
 */
final class SpreadsheetReader
{
    /** IMP-01: raised from 5,000; the commit writes in chunks inside one transaction. */
    public const MAX_DATA_ROWS = 20000;

    /** Physical lines scanned before giving up, so a file of blank rows cannot run to the time limit. */
    public const MAX_SCANNED_LINES = 100000;

    /** IMP-01: the header may sit below a title (shop name, report date...) within this many non-blank lines. */
    public const HEADER_SEARCH_LINES = 10;

    /**
     * @param  (callable(list<string>): int)|null  $headerScore  how many known columns a line names;
     *                                                           null = the first non-blank line is the header
     * @return array{headers: list<string>, header_row: int, rows: list<array{row_number: int, cells: list<string>}>}
     *
     * @throws ValidationException
     */
    public function read(string $path, string $extension, ?callable $headerScore = null): array
    {
        $lines = strtolower($extension) === 'xlsx' ? $this->readXlsx($path) : $this->readCsv($path);

        // 1. The first non-blank lines are buffered until the header is chosen among them.
        $candidates = [];
        $rows = [];
        $headers = [];
        $headerRow = 0;
        foreach ($lines as $number => $cells) {
            if ($number > self::MAX_SCANNED_LINES) {
                throw ValidationException::withMessages([
                    'file' => __('import.errors.too_many_rows', ['max' => self::MAX_DATA_ROWS]),
                ]);
            }

            // Blank lines never count as data rows, wherever they sit in the file.
            if ($this->isBlank($cells)) {
                continue;
            }

            if ($headerRow === 0) {
                $candidates[] = ['row_number' => $number, 'cells' => $cells];
                if (count($candidates) < self::HEADER_SEARCH_LINES) {
                    continue;
                }
                [$headers, $headerRow, $rows] = $this->splitAtHeader($candidates, $headerScore);

                continue;
            }

            $this->append($rows, $number, $cells);
        }

        if ($headerRow === 0 && $candidates !== []) {
            [$headers, $headerRow, $rows] = $this->splitAtHeader($candidates, $headerScore);
        }

        if ($headerRow === 0 || $rows === []) {
            throw ValidationException::withMessages(['file' => __('import.errors.empty_file')]);
        }

        return ['headers' => $headers, 'header_row' => $headerRow, 'rows' => $rows];
    }

    /**
     * The best-scoring candidate is the header (the earliest one on a tie); lines above it are titles
     * and are dropped, lines below it are data. No scorer, or no line naming any column: the first line.
     *
     * @param  list<array{row_number: int, cells: list<string>}>  $candidates
     * @param  (callable(list<string>): int)|null  $headerScore
     * @return array{0: list<string>, 1: int, 2: list<array{row_number: int, cells: list<string>}>}
     */
    private function splitAtHeader(array $candidates, ?callable $headerScore): array
    {
        $index = 0;
        if ($headerScore !== null) {
            $best = 0;
            foreach ($candidates as $position => $candidate) {
                $score = $headerScore($candidate['cells']);
                if ($score > $best) {
                    $best = $score;
                    $index = $position;
                }
            }
        }

        $rows = [];
        foreach (array_slice($candidates, $index + 1) as $candidate) {
            $this->append($rows, $candidate['row_number'], $candidate['cells']);
        }

        return [$candidates[$index]['cells'], $candidates[$index]['row_number'], $rows];
    }

    /**
     * @param  list<array{row_number: int, cells: list<string>}>  $rows
     * @param  list<string>  $cells
     *
     * @throws ValidationException
     */
    private function append(array &$rows, int $number, array $cells): void
    {
        // IMP-02: the readers stream EVERY line (no line cap of their own), so a file with one
        // data row past the limit is refused here instead of being silently cut at a line count.
        if (count($rows) === self::MAX_DATA_ROWS) {
            throw ValidationException::withMessages([
                'file' => __('import.errors.too_many_rows', ['max' => self::MAX_DATA_ROWS]),
            ]);
        }

        $rows[] = ['row_number' => $number, 'cells' => $cells];
    }

    /**
     * Streams the first sheet; the caller stops consuming as soon as it has seen too many data rows.
     *
     * @return Generator<int, list<string>> sheet line number => cells
     */
    private function readXlsx(string $path): Generator
    {
        $reader = new XlsxReader;

        // Only failures raised by the XLSX reader land in the catch: an exception thrown by the
        // consumer (too_many_rows) never enters the generator, it just stops pulling and the
        // finally block releases the file.
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                $number = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    yield ++$number => array_map($this->cellToString(...), $row->toArray());
                }
                break; // first sheet only
            }
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('import.errors.unreadable_file')]);
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
                // nothing left to release
            }
        }
    }

    /**
     * @return Generator<int, list<string>> line number => cells
     */
    private function readCsv(string $path): Generator
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw ValidationException::withMessages(['file' => __('import.errors.unreadable_file')]);
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } elseif (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF")) {
            // IMP-01: Excel's "Unicode text" export is UTF-16 (little-endian, tab separated).
            $encoding = str_starts_with($content, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE';
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', $encoding);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            // mbstring has no Windows-1256 (it threw a ValueError -> 500); iconv does.
            $converted = @iconv('CP1256', 'UTF-8//IGNORE', $content);
            if ($converted === false || $converted === '') {
                throw ValidationException::withMessages(['file' => __('import.errors.unreadable_file')]);
            }
            $content = $converted;
        }

        // IMP-01: the first lines decide (a title line above the header may carry no delimiter at all).
        $head = implode("\n", array_slice(explode("\n", $content, self::HEADER_SEARCH_LINES + 1), 0, self::HEADER_SEARCH_LINES));
        $delimiter = ',';
        $best = substr_count($head, ',');
        foreach ([';', "\t"] as $candidate) {
            $count = substr_count($head, $candidate);
            if ($count > $best) {
                $best = $count;
                $delimiter = $candidate;
            }
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => __('import.errors.unreadable_file')]);
        }
        fwrite($stream, $content);
        rewind($stream);

        try {
            $number = 0;
            while (($record = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                yield ++$number => array_map(static fn (?string $cell): string => trim((string) $cell), $record);
            }
        } finally {
            fclose($stream);
        }
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value)) {
            // Numeric cells arrive as float: render without exponent or float noise (max 6 decimals, then trimmed).
            $text = number_format($value, 6, '.', '');

            return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
        }

        return trim((string) $value);
    }

    /**
     * @param  list<string>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
