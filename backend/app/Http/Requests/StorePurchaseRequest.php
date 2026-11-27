<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\DocumentDate;
use App\Services\PurchaseService;
use App\Support\Money\Decimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('purchases.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date', new DocumentDate($this->user())],
            'supplier_invoice_ref' => ['nullable', 'string', 'max:100'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'items.*.cost_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'additional_expenses' => ['nullable', 'array'],
            'additional_expenses.*.title' => ['nullable', 'string', 'max:150'],
            'additional_expenses.*.amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'additional_expenses.*.allocation_method' => ['nullable', 'string', 'in:by_quantity,by_value,equal'],
            'additional_expenses.*.paid_by' => ['nullable', 'string', 'in:supplier_account,treasury_cash'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateTotalsAndPaymentBounds($validator),
        ];
    }

    private function validateTotalsAndPaymentBounds(Validator $validator): void
    {
        $errors = $validator->errors();
        if ($errors->has('items') || $errors->has('discount_amount') || $errors->has('discount') || $errors->has('paid_amount')) {
            return;
        }
        foreach ($errors->keys() as $key) {
            if (str_starts_with($key, 'items.') || str_starts_with($key, 'additional_expenses.')) {
                return;
            }
        }

        $items = $this->input('items');
        if (! is_array($items) || $items === []) {
            return;
        }

        $rawDiscount = $this->input('discount_amount');
        $discountField = 'discount_amount';
        if ($rawDiscount === null || $rawDiscount === '') {
            $rawDiscount = $this->input('discount');
            if ($rawDiscount !== null && $rawDiscount !== '') {
                $discountField = 'discount';
            }
        }

        $totals = PurchaseService::calculateTotals(
            items: $items,
            discountAmount: $rawDiscount,
            additionalExpenses: (array) $this->input('additional_expenses', []),
        );

        if (bccomp($totals['discount_amount'], $totals['subtotal'], 3) > 0) {
            $validator->errors()->add(
                $discountField,
                __('purchases.discount_exceeds_subtotal', [
                    'discount' => $totals['discount_amount'],
                    'subtotal' => $totals['subtotal'],
                ]),
            );

            return;
        }

        $paidAmount = Decimal::normalize($this->input('paid_amount') !== null ? (string) $this->input('paid_amount') : '0.000');
        if (bccomp($paidAmount, $totals['net_total'], 3) > 0) {
            $validator->errors()->add(
                'paid_amount',
                __('purchases.paid_exceeds_net_total', [
                    'paid' => $paidAmount,
                    'net' => $totals['net_total'],
                ]),
            );
        }
    }
}
