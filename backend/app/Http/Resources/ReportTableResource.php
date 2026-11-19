<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SHOP-05: a tabular report (rows + totals over the whole result, optional paging meta and
 * as-of date). The rows are already shaped scale-3 money strings from the report Action.
 *
 * @property array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, total?: int, as_of?: string} $resource
 */
final class ReportTableResource extends JsonResource
{
    public function __construct(array $resource, private readonly ?int $page = null, private readonly ?int $perPage = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;
        $total = (int) ($report['total'] ?? count($report['rows']));
        $perPage = $this->perPage ?? max(1, $total);

        $out = [
            'success' => true,
            'data' => $report['rows'],
            'totals' => $report['totals'],
            'meta' => [
                'current_page' => $this->page ?? 1,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            ],
        ];
        if (isset($report['as_of'])) {
            $out['as_of'] = $report['as_of'];
        }

        return $out;
    }
}
