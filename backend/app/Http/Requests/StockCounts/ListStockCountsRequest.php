<?php

declare(strict_types=1);

namespace App\Http\Requests\StockCounts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SHOP-02: list / read a stocktake session (show, variance report). `stock.count` only.
 */
class ListStockCountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('stock.count');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
