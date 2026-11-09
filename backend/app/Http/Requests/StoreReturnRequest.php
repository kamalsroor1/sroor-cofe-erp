<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! ($user->hasRole('admin') || $user->can('returns.manage'))) {
            return false;
        }

        // BE-01: a return that references no source invoice/purchase needs its own permission.
        return $this->hasSource() || $user->can('returns.without_invoice');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSales = $this->input('return_type') === 'sales_return';
        $linkKey = $isSales ? 'invoice_item_id' : 'purchase_item_id';
        $hasSource = $this->hasSource();

        return [
            'return_type' => ['required', 'string', 'in:sales_return,purchase_return'],
            'customer_id' => ['nullable', 'required_if:return_type,sales_return', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'required_if:return_type,purchase_return', 'exists:suppliers,id'],
            'invoice_id' => ['nullable', 'integer', 'prohibited_unless:return_type,sales_return'],
            'purchase_id' => ['nullable', 'integer', 'prohibited_unless:return_type,purchase_return'],
            'return_date' => ['required', 'date'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            // Against a source document the line decides item and price (a client unit_price is
            // ignored); without one the client sends them, as before.
            'items.*.item_id' => [Rule::requiredIf(! $hasSource), 'nullable', 'exists:items,id'],
            'items.*.'.$linkKey => $hasSource
                ? ['required', 'integer', 'distinct']
                : ['prohibited'],
            // The other kind of link never applies to this return type.
            'items.*.'.($isSales ? 'purchase_item_id' : 'invoice_item_id') => ['prohibited'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => [Rule::requiredIf(! $hasSource), 'nullable', 'numeric', 'min:0'],
        ];
    }

    private function hasSource(): bool
    {
        return $this->filled($this->input('return_type') === 'sales_return' ? 'invoice_id' : 'purchase_id');
    }
}
