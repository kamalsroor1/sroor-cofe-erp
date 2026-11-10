<?php

declare(strict_types=1);

namespace App\Services\Import;

use DateTimeInterface;
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

            if (! $this->isBlank($cells)) {
                $rows[] = ['row_number' => $number, 'cells' => $cells];
            }

            if (count($rows) > self::MAX_DATA_ROWS) {
                throw ValidationException::withMessages([
                    'file' => __('import.errors.too_many_rows', ['max' => self::MAX_DATA_ROWS]),
                ]);
            }
        }

        if ($this->isBlank($headers) || $rows === []) {
            throw ValidationException::withMessages(['file' => __('import.errors.empty_file')]);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array<int, list<string>> sheet line number => cells
     */
    private function readXlsx(string $path): array
    {
        $reader = new XlsxReader;
        $lines = [];

        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                $number = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $number++;
                    $lines[$number] = array_map($this->cellToString(...), $row->toArray());

                    // Stop early on absurd sheets: MAX + header + 1 sentinel row is enough to reject.
                    if ($number > self::MAX_DATA_ROWS + 1) {
                        break;
                    }
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

        return $lines;
    }

    /**
     * @return array<int, list<string>>
     */
    private function readCsv(string $path): array
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

        $lines = [];
        $number = 0;
        while (($record = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $number++;
            $lines[$number] = array_map(static fn (?string $cell): string => trim((string) $cell), $record);

            if ($number > self::MAX_DATA_ROWS + 1) {
                break;
            }
        }
        fclose($stream);

        return $lines;
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
