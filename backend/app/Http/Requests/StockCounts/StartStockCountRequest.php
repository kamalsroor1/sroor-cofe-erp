<?php

declare(strict_types=1);

namespace App\Http\Requests\StockCounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SHOP-02: start a session in the active store (X-Store-Id; never a body store id).
 */
class StartStockCountRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'is_blind' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
