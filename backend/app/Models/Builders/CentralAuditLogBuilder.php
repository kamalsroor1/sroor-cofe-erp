<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Exceptions\CentralAuditLogImmutableException;
use App\Models\CentralAuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query builder for the append-only central audit log (IDEN-1.5).
 *
 * Every Eloquent path that would change or remove existing rows throws, so neither
 * `$log->save()` on an existing row, `$log->delete()`, `CentralAuditLog::destroy()`
 * nor a mass `->update()/->delete()` can rewrite history. Inserts and reads are
 * untouched. Raw `DB::table()` access is out of scope here; production should also
 * deny UPDATE/DELETE on the table at the DB-grant level (ops follow-up).
 *
 * @extends Builder<CentralAuditLog>
 */
final class CentralAuditLogBuilder extends Builder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        throw new CentralAuditLogImmutableException;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int|string, mixed>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        throw new CentralAuditLogImmutableException;
    }

    /**
     * @param  string|null  $column
     */
    public function touch($column = null): int
    {
        throw new CentralAuditLogImmutableException;
    }

    /**
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     */
    public function increment($column, $amount = 1, array $extra = []): int
    {
        throw new CentralAuditLogImmutableException;
    }

    /**
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     */
    public function decrement($column, $amount = 1, array $extra = []): int
    {
        throw new CentralAuditLogImmutableException;
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function incrementEach(array $columns, array $extra = []): int
    {
        throw new CentralAuditLogImmutableException;
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function decrementEach(array $columns, array $extra = []): int
    {
        throw new CentralAuditLogImmutableException;
    }

    public function delete(): mixed
    {
        throw new CentralAuditLogImmutableException;
    }

    public function forceDelete(): mixed
    {
        throw new CentralAuditLogImmutableException;
    }

    /** Not a passthru method on the Eloquent builder, so it must be blocked explicitly. */
    public function truncate(): void
    {
        throw new CentralAuditLogImmutableException;
    }
}
