<?php

declare(strict_types=1);

namespace App\Services\Import\Handlers;

use App\Services\Import\ImportContext;
use App\Services\Import\ImportRow;
use App\Services\Import\NumberNormalizer;
use Illuminate\Validation\ValidationException;

/**
 * PRD-06: shared analysis loop of the four import types.
 * A subclass declares its columns, normalizes + validates one row, names the duplicate key
 * and knows how to write the valid rows (inside the caller's transaction).
 */
abstract class AbstractImportHandler
{
    /** Lang section: `import.columns.<key>.*` and `import.examples.<key>.*`. */
    abstract public function key(): string;

    /**
     * Column keys in template order.
     *
     * @return list<string>
     */
    abstract public function columns(): array;

    /**
     * Column keys that must be present in the file's header.
     *
     * @return list<string>
     */
    abstract protected function requiredColumns(): array;

    /** Loads whatever the duplicate check needs, once per analysis. */
    protected function prepare(): void {}

    /**
     * Normalize + validate one row.
     *
     * @param  array<string, string>  $raw  trimmed cell text by column key
     * @param  list<string>  $errors  collected translated messages
     * @return array<string, string|bool|null>
     */
    abstract protected function normalize(array $raw, array &$errors): array;

    /**
     * IMP-02: non-blocking remarks on a VALID row (the row is still imported), e.g. a sale price
     * below cost. Translated messages.
     *
     * @param  array<string, string|bool|null>  $data  the normalized row
     * @return list<string>
     */
    protected function warnings(array $data): array
    {
        return [];
    }

    /** Value that identifies the record in the tenant DB, null when the row can never be a duplicate. */
    abstract protected function duplicateKey(array $data): ?string;

    /**
     * Keys that already exist in the tenant DB.
     *
     * @param  list<string>  $keys
     * @return array<string, true>
     */
    abstract protected function existingKeys(array $keys): array;

    /**
     * Persist the valid rows. Runs inside the commit transaction; throws to roll everything back.
     *
     * @param  list<array<string, string|bool|null>>  $rows
     */
    abstract public function write(array $rows, ImportContext $context): int;

    public function label(string $column): string
    {
        return (string) __("import.columns.{$this->key()}.{$column}");
    }

    /**
     * Header labels and one example row, for the .xlsx template.
     *
     * @return array{headers: list<string>, example: list<string>}
     */
    public function template(): array
    {
        $example = [];
        foreach ($this->columns() as $column) {
            $example[] = (string) __("import.examples.{$this->key()}.{$column}");
        }

        return [
            'headers' => array_map($this->label(...), $this->columns()),
            'example' => $example,
        ];
    }

    /**
     * Analyse every row WITHOUT writing.
     *
     * @param  list<string>  $headers
     * @param  list<array{row_number: int, cells: list<string>}>  $rows
     * @return list<ImportRow>
     *
     * @throws ValidationException when a required column is missing from the header
     */
    public function analyze(array $headers, array $rows): array
    {
        $map = $this->mapColumns($headers);

        $this->prepare();

        $analysed = [];
        $keys = [];
        foreach ($rows as $row) {
            $raw = array_fill_keys($this->columns(), '');
            foreach ($map as $index => $column) {
                $raw[$column] = trim($row['cells'][$index] ?? '');
            }

            $errors = [];
            $data = $this->normalize($raw, $errors);
            $key = $errors === [] ? $this->duplicateKey($data) : null;

            $analysed[] = [$row['row_number'], $data, $errors, $key];
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $existing = $this->existingKeys(array_values(array_unique($keys)));

        $seen = [];
        $result = [];
        foreach ($analysed as [$number, $data, $errors, $key]) {
            if ($errors !== []) {
                $result[] = new ImportRow($number, $data, ImportRow::STATUS_INVALID, $errors);

                continue;
            }

            if ($key !== null && (isset($existing[$key]) || isset($seen[$key]))) {
                $result[] = new ImportRow($number, $data, ImportRow::STATUS_DUPLICATE, [__('import.errors.duplicate')]);

                continue;
            }

            if ($key !== null) {
                $seen[$key] = true;
            }
            $result[] = new ImportRow($number, $data, ImportRow::STATUS_VALID, [], $this->warnings($data));
        }

        return $result;
    }

    /**
     * Header cell index => column key. Headers are matched by Arabic label, English label or key;
     * a header row that matches nothing is read by column order.
     *
     * @param  list<string>  $headers
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    private function mapColumns(array $headers): array
    {
        $aliases = [];
        foreach ($this->columns() as $column) {
            foreach (['ar', 'en'] as $locale) {
                $aliases[$this->normalizeHeader((string) __("import.columns.{$this->key()}.{$column}", [], $locale))] = $column;
            }
            $aliases[$this->normalizeHeader($column)] = $column;
        }

        $map = [];
        foreach ($headers as $index => $header) {
            $column = $aliases[$this->normalizeHeader($header)] ?? null;
            if ($column !== null && ! in_array($column, $map, true)) {
                $map[$index] = $column;
            }
        }

        if ($map === []) {
            foreach ($this->columns() as $index => $column) {
                if ($index < count($headers)) {
                    $map[$index] = $column;
                }
            }
        }

        $missing = array_diff($this->requiredColumns(), $map);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => __('import.errors.missing_columns', [
                    'columns' => implode('، ', array_map($this->label(...), $missing)),
                ]),
            ]);
        }

        return $map;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = str_replace(['*', ':', '_', '-'], ' ', $header);

        return (string) preg_replace('/\s+/u', ' ', trim($header));
    }

    /**
     * @param  array<string, string>  $raw
     * @param  list<string>  $errors
     */
    protected function requireText(array $raw, string $column, int $max, array &$errors, bool $required = true): ?string
    {
        $value = $this->stripMarkup($raw[$column]);

        if ($value === '') {
            if ($required) {
                $errors[] = (string) __('import.errors.required', ['field' => $this->label($column)]);
            }

            return null;
        }

        if (mb_strlen($value) > $max) {
            $errors[] = (string) __('import.errors.too_long', ['field' => $this->label($column), 'max' => $max]);

            return null;
        }

        return $value;
    }

    /**
     * IMP-02: HTML/script markup is STRIPPED from imported text (`<b>بن</b>` -> `بن`, a `<script>`
     * block disappears with its content). Text that merely contains `<` (`أقل من < 5`) is kept.
     * Formula-looking text (`=HYPERLINK(...)`) is stored as typed and neutralised on export (CsvCell).
     */
    private function stripMarkup(string $value): string
    {
        if (preg_match('/<[a-z!\/?]/i', $value) !== 1) {
            return trim($value);
        }

        $value = (string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1\s*>/isu', ' ', $value);
        // Only a CLOSED tag is removed: a lone `<` (`Size<M`, code `A<B1`) is kept as typed.
        $value = (string) preg_replace('/<[a-z!\/?][^<>]*>/iu', ' ', $value);

        return trim((string) preg_replace('/\s{2,}/u', ' ', $value));
    }

    /**
     * Parse a DECIMAL(12,3) cell. Blank returns $default (null = column left empty).
     *
     * @param  array<string, string>  $raw
     * @param  list<string>  $errors
     */
    protected function number(array $raw, string $column, array &$errors, bool $allowNegative = false, ?string $default = null, bool $required = false): ?string
    {
        $value = trim($raw[$column]);

        if ($value === '') {
            if ($required) {
                $errors[] = (string) __('import.errors.required', ['field' => $this->label($column)]);
            }

            return $default;
        }

        $number = NumberNormalizer::decimal($value);
        if ($number === null) {
            $errors[] = NumberNormalizer::exceedsScale($value)
                ? (string) __('import.errors.too_many_decimals', ['field' => $this->label($column), 'max' => NumberNormalizer::SCALE])
                : (string) __('import.errors.invalid_number', ['field' => $this->label($column)]);

            return $default;
        }

        if (! $allowNegative && str_starts_with($number, '-')) {
            $errors[] = (string) __('import.errors.negative_not_allowed', ['field' => $this->label($column)]);

            return $default;
        }

        return $number;
    }
}
