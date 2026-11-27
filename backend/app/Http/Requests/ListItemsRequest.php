<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesListingSort;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ListItemsRequest extends FormRequest
{
    use ValidatesListingSort;

    /** @var array<string, string> */
    public const SORT_COLUMNS = [
        'id' => 'id',
        'name' => 'name',
        'code' => 'code',
        'selling_price' => 'selling_price',
        'current_stock' => 'current_stock',
        'pos_sales_count' => 'pos_sales_count',
        'created_at' => 'created_at',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && (
            $user->hasRole('admin')
            || $user->can('items.view')
            || $user->can('pos.access')
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->sortRules(array_keys(self::SORT_COLUMNS));
    }
}
