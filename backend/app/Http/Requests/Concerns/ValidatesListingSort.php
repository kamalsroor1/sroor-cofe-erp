<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * GEN-180: Rejects unallowlisted `sort` / `sort_by` and `direction` / `sort_dir`
 * query parameters with a 422 validation response instead of silently ignoring
 * or interpolating caller input into ORDER BY.
 */
trait ValidatesListingSort
{
    /**
     * @param  list<string>  $allowedColumns
     * @return array<string, list<mixed>>
     */
    protected function sortRules(array $allowedColumns): array
    {
        return [
            'sort' => ['nullable', 'string', Rule::in($allowedColumns)],
            'sort_by' => ['nullable', 'string', Rule::in($allowedColumns)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @param  array<string, string>  $columnMap  allowlisted sort key => safe SQL column
     * @return array{column: string, direction: 'asc'|'desc', explicit: bool}
     */
    public function resolveSort(array $columnMap, string $defaultColumn = 'id', string $defaultDirection = 'desc'): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        $rawSort = $validated['sort'] ?? $validated['sort_by'] ?? null;
        $rawDirection = $validated['direction'] ?? $validated['sort_dir'] ?? null;

        $explicit = is_string($rawSort) && $rawSort !== '';
        $column = $explicit && isset($columnMap[$rawSort]) ? $columnMap[$rawSort] : $defaultColumn;

        $direction = $rawDirection === 'asc'
            ? 'asc'
            : ($rawDirection === 'desc' ? 'desc' : ($defaultDirection === 'asc' ? 'asc' : 'desc'));

        return [
            'column' => $column,
            'direction' => $direction,
            'explicit' => $explicit,
        ];
    }
}
