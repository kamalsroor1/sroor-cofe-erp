<?php

declare(strict_types=1);

namespace App\Http\Requests\StockCounts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SHOP-02: post (zero_uncounted: treat uncounted lines as 0) or cancel an open session.
 */
class PostStockCountRequest extends FormRequest
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
            'zero_uncounted' => ['sometimes', 'boolean'],
        ];
    }
}
