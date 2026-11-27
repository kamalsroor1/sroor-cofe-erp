<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Escapes SQL LIKE wildcards (`%`, `_`) and the escape character itself (`!`) so
 * user-supplied search terms match literally on both MySQL/MariaDB and SQLite.
 *
 * Uses `!` with an explicit `ESCAPE '!'` clause rather than backslash because SQLite
 * has no default LIKE escape character and MySQL string literals interpret `\`.
 */
final class LikePattern
{
    public const ESCAPE_CHAR = '!';

    private function __construct() {}

    public static function escape(string $value): string
    {
        return strtr($value, [
            self::ESCAPE_CHAR => self::ESCAPE_CHAR.self::ESCAPE_CHAR,
            '%' => self::ESCAPE_CHAR.'%',
            '_' => self::ESCAPE_CHAR.'_',
        ]);
    }

    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }

    public static function prefix(string $value): string
    {
        return self::escape($value).'%';
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     */
    public static function whereContains(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        string $value,
        string $boolean = 'and',
    ): void {
        $wrapped = self::wrapColumn($query, $column);
        $query->whereRaw("{$wrapped} LIKE ? ESCAPE '!'", [self::contains($value)], $boolean);
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     */
    public static function orWhereContains(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        string $value,
    ): void {
        self::whereContains($query, $column, $value, 'or');
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     */
    public static function wherePrefix(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        string $value,
        string $boolean = 'and',
    ): void {
        $wrapped = self::wrapColumn($query, $column);
        $query->whereRaw("{$wrapped} LIKE ? ESCAPE '!'", [self::prefix($value)], $boolean);
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     */
    public static function orWherePrefix(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        string $value,
    ): void {
        self::wherePrefix($query, $column, $value, 'or');
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     * @param  non-empty-list<string>  $columns
     */
    public static function whereContainsAny(
        EloquentBuilder|QueryBuilder $query,
        array $columns,
        string $value,
        string $boolean = 'and',
    ): void {
        $query->where(static function ($q) use ($columns, $value): void {
            foreach ($columns as $index => $column) {
                self::whereContains($q, $column, $value, $index === 0 ? 'and' : 'or');
            }
        }, boolean: $boolean);
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     */
    private static function wrapColumn(EloquentBuilder|QueryBuilder $query, string $column): string
    {
        $grammar = $query instanceof EloquentBuilder
            ? $query->getQuery()->getGrammar()
            : $query->getGrammar();

        return $grammar->wrap($column);
    }
}
