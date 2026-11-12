<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesReportPeriod;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FilterReportRequest extends FormRequest
{
    use ResolvesReportPeriod;

    /**
     * PERF-01: the longest period a report may cover (a leap year). Longer custom ranges are a
     * 422 instead of a request that scans years of documents.
     */
    public const MAX_PERIOD_DAYS = 366;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('reports.view') ?? false;
    }

    public function rules(): array
    {
        // PERF-01: a reversed range (start after end) is a 422, never an empty or wrong report.
        return $this->withEndNotBeforeStart([
            'tab' => ['nullable', 'string'],
            'period' => ['nullable', 'string'],
            'preset' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'store_id' => ['nullable'],
            'treasury_method' => ['nullable', 'string'],
            'stock_filter' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // Same resolution as ReportFilterDTO: from_date|from, to_date|to (default today).
                $fromKey = $this->periodStartKey();
                if ($fromKey === null) {
                    return; // a preset: at most this_year
                }
                $toKey = $this->periodEndKey();

                $from = CarbonImmutable::parse((string) $this->input($fromKey))->startOfDay();
                $today = app(TenantClock::class)->businessDate();
                $to = $toKey !== null
                    ? CarbonImmutable::parse((string) $this->input($toKey))->startOfDay()
                    : CarbonImmutable::parse($today)->startOfDay();

                // No end date: the period ends today, so a start after today is reversed too.
                if ($toKey === null && $from->greaterThan($to)) {
                    $validator->errors()->add($fromKey, __('validation.before_or_equal', ['attribute' => $fromKey, 'date' => $today]));

                    return;
                }

                if ((int) $from->diffInDays($to) + 1 > self::MAX_PERIOD_DAYS) {
                    $validator->errors()->add($toKey ?? $fromKey, __('reports.period_too_long', ['days' => self::MAX_PERIOD_DAYS]));
                }
            },
        ];
    }
}
