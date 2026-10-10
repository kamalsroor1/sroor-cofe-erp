<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central data cleanup of `tenants.data` (stancl VirtualColumn).
 *
 * Until now Tenant::getCustomColumns() did not list created_at, updated_at, settings, logo,
 * address, commercial_register and tax_number, so every save copied them into the `data`
 * JSON and, on load, the copy overrode the real column:
 *  - created_at / updated_at in `data` were stale (the value loaded before the save);
 *  - `settings` (array cast) was re-encoded on every save/load cycle, so `data.settings`
 *    became a JSON string nested once more per save, and the `settings` column stayed NULL.
 *
 * For every tenant row, every key of `data` that is a real column of `tenants` is removed
 * from `data`. The column stays authoritative; only when the column is NULL and the `data`
 * copy is not (the only place the value ever lived, e.g. `settings`) is the copy moved into
 * the column. JSON columns (settings, enabled_features) are unwrapped from their nested
 * string encoding first; a copy that does not unwrap to an array is dropped.
 *
 * Idempotent: a clean row has no real-column key in `data` and is left untouched.
 * down() is a deliberate no-op: it is a data cleanup; putting stale copies back into `data`
 * would only re-introduce the bug (the old code reads the columns fine without them).
 */
return new class extends Migration
{
    /** Real columns of `tenants` holding JSON (array casts on the Tenant model). */
    private const JSON_COLUMNS = ['settings', 'enabled_features'];

    /** Never moved: the key itself and the stancl data column. */
    private const SKIPPED_COLUMNS = ['id', 'data'];

    /** Upper bound of nested encodings unwrapped from one value (one per historical save). */
    private const MAX_UNWRAP = 10000;

    public function up(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'data')) {
            return;
        }

        $columns = array_values(array_diff(Schema::getColumnListing('tenants'), self::SKIPPED_COLUMNS));

        DB::table('tenants')->select(array_merge(['id', 'data'], $columns))
            ->lazyById(100, 'id')
            ->each(function (object $row) use ($columns): void {
                $this->cleanRow($row, $columns);
            });
    }

    public function down(): void
    {
        // Data cleanup: intentionally not reversible (see the class docblock).
    }

    /**
     * @param  list<string>  $columns
     */
    private function cleanRow(object $row, array $columns): void
    {
        $data = is_string($row->data) && $row->data !== '' ? json_decode($row->data, true) : null;
        if (! is_array($data)) {
            return;
        }

        $leaked = array_values(array_intersect($columns, array_keys($data)));
        if ($leaked === []) {
            return;
        }

        $update = [];
        foreach ($leaked as $column) {
            $copy = $data[$column];
            unset($data[$column]);

            if ($row->{$column} !== null || $copy === null) {
                continue; // the column is authoritative
            }

            if (in_array($column, self::JSON_COLUMNS, true)) {
                $copy = $this->unwrapJson($copy);
                if ($copy !== null) {
                    $update[$column] = json_encode($copy, JSON_UNESCAPED_UNICODE);
                }

                continue;
            }

            if (is_scalar($copy)) {
                $update[$column] = $copy;
            }
        }

        $update['data'] = $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE);

        DB::table('tenants')->where('id', $row->id)->update($update);
    }

    /**
     * A JSON value stored as a string encoded N times, back to its array (null if it is not one).
     *
     * @return array<array-key, mixed>|null
     */
    private function unwrapJson(mixed $value): ?array
    {
        for ($i = 0; is_string($value) && $i < self::MAX_UNWRAP; $i++) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            $value = $decoded;
        }

        return is_array($value) ? $value : null;
    }
};
