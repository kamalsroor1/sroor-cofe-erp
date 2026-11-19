<?php

declare(strict_types=1);

namespace App\Http\Requests\StockCounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SHOP-02: counted quantities of an open session. `counted_qty` null clears a count.
 */
class SaveStockCountLinesRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1', 'max:1000'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'lines.*.counted_qty' => ['present', 'nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
        ];
    }
}
