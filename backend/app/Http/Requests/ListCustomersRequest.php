<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesListingSort;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ListCustomersRequest extends FormRequest
{
    use ValidatesListingSort;

    /** @var array<string, string> */
    public const SORT_COLUMNS = [
        'id' => 'id',
        'name' => 'name',
        'current_balance' => 'current_balance',
        'created_at' => 'created_at',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && (
            $user->hasRole('admin')
            || $user->can('customers.manage')
            || $user->can('pos.access')
            || $user->can('invoices.create')
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
