<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * PERF-01: which input carries the start and the end of a report / statement period. The
 * resolution matches ReportFilterDTO / CustomerStatementFilterDTO: `from_date` wins over
 * `from`, `to_date` wins over `to`. Used to validate the EFFECTIVE bounds (reversed range,
 * maximum length) on the key the client actually sent.
 */
trait ResolvesReportPeriod
{
    protected function periodStartKey(): ?string
    {
        return $this->filled('from_date') ? 'from_date' : ($this->filled('from') ? 'from' : null);
    }

    protected function periodEndKey(): ?string
    {
        return $this->filled('to_date') ? 'to_date' : ($this->filled('to') ? 'to' : null);
    }

    /**
     * `after_or_equal:<start>` on the end key the client sent, when it sent both bounds.
     *
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, list<mixed>>
     */
    protected function withEndNotBeforeStart(array $rules): array
    {
        $start = $this->periodStartKey();
        $end = $this->periodEndKey();
        if ($start !== null && $end !== null) {
            $rules[$end][] = 'after_or_equal:'.$start;
        }

        return $rules;
    }
}
