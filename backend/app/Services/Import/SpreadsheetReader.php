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
 * delimiter `,` `;` or tab detected from the header line. Fully empty rows are skipped but
 * `row_number` always matches the line the user sees in the sheet (header = 1).
 */
final class SpreadsheetReader
{
    public const MAX_DATA_ROWS = 5000;

    /** Physical lines scanned before giving up, so a file of blank rows cannot run to the time limit. */
    public const MAX_SCANNED_LINES = 50000;

    /**
     * @return array{headers: list<string>, rows: list<array{row_number: int, cells: list<string>}>}
     *
     * @throws ValidationException
     */
    public function read(string $path, string $extension): array
    {
        $lines = strtolower($extension) === 'xlsx' ? $this->readXlsx($path) : $this->readCsv($path);

        $headers = [];
        $rows = [];
        foreach ($lines as $number => $cells) {
            if ($number === 1) {
                $headers = $cells;

                continue;
            }

            if ($number > self::MAX_SCANNED_LINES) {
                throw ValidationException::withMessages([
                    'file' => __('import.errors.too_many_rows', ['max' => self::MAX_DATA_ROWS]),
                ]);
            }

            // Blank lines never count as data rows, wherever they sit in the file.
            if ($this->isBlank($cells)) {
                continue;
            }

            // IMP-02: the readers stream EVERY line (no line cap of their own), so a file with one
            // data row past the limit is refused here instead of being silently cut at a line count.
            if (count($rows) === self::MAX_DATA_ROWS) {
                throw ValidationException::withMessages([
                    'file' => __('import.errors.too_many_rows', ['max' => self::MAX_DATA_ROWS]),
                ]);
            }

            $rows[] = ['row_number' => $number, 'cells' => $cells];
        }

        if ($this->isBlank($headers) || $rows === []) {
            throw ValidationException::withMessages(['file' => __('import.errors.empty_file')]);
        }

        return ['headers' => $headers, 'rows' => $rows];
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
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1256');
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = ',';
        $best = substr_count($firstLine, ',');
        foreach ([';', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);
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
