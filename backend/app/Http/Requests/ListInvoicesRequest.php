<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesListingSort;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ListInvoicesRequest extends FormRequest
{
    use ValidatesListingSort;

    /** @var array<string, string> */
    public const SORT_COLUMNS = [
        'id' => 'id',
        'invoice_number' => 'invoice_number',
        'invoice_date' => 'invoice_date',
        'net_total' => 'net_total',
        'created_at' => 'created_at',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && (
            $user->hasRole('admin')
            || $user->can('invoices.view')
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
