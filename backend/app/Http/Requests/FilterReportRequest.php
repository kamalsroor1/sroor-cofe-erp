<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('reports.view') || $this->user()?->can('reports.advanced') ?? false;
    }

    public function rules(): array
    {
        return [
            'tab'             => ['nullable', 'string'],
            'period'          => ['nullable', 'string'],
            'preset'          => ['nullable', 'string'],
            'from'            => ['nullable', 'date'],
            'to'              => ['nullable', 'date'],
            'from_date'       => ['nullable', 'date'],
            'to_date'         => ['nullable', 'date'],
            'store_id'        => ['nullable'],
            'treasury_method' => ['nullable', 'string'],
            'stock_filter'    => ['nullable', 'string'],
        ];
    }
}
