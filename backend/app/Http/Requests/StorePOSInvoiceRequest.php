<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePOSInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.access') ?? true;
    }

    protected function prepareForValidation(): void
    {
        $storeId = $this->input('store_id')
            ?? $this->header('X-Store-Id')
            ?? session('current_store_id')
            ?? $this->user()?->getCurrentStore()?->id
            ?? \App\Models\Store::first()?->id;

        $paymentType = $this->input('payment_type') ?? $this->input('invoice_type') ?? 'cash';
        $paymentMethod = $this->input('payment_method') ?? 'cash';
        if ($paymentMethod === 'smart_wallet') {
            $paymentMethod = 'e_wallet';
        }

        $customerId = $this->input('customer_id');
        if (empty($customerId) || $customerId === 'null' || $customerId === null) {
            $defaultCustomer = \App\Models\Customer::where('name', 'نقدي عام')
                ->orWhere('name', 'عميل نقدي')
                ->orWhere('phone', '0000000000')
                ->first();

            if (!$defaultCustomer) {
                $defaultCustomer = \App\Models\Customer::create([
                    'name' => 'عميل نقدي',
                    'phone' => '0000000000',
                    'current_balance' => 0,
                    'price_tier' => 'retail',
                    'is_active' => true,
                ]);
            }
            $customerId = $defaultCustomer->id;
        }

        $this->merge([
            'customer_id' => $customerId ? (int)$customerId : null,
            'store_id' => $storeId ? (int)$storeId : null,
            'invoice_date' => $this->input('invoice_date') ?? now()->toDateString(),
            'payment_type' => $paymentType,
            'payment_method' => $paymentMethod,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'store_id' => 'required|exists:stores,id',
            'invoice_date' => 'required|date',
            'payment_type' => 'required|in:cash,credit,partial',
            'payment_method' => 'nullable|string|in:cash,instapay,e_wallet,smart_wallet,visa,bank_transfer',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'additional_expenses' => 'nullable|array',
            'additional_expenses.*.title' => 'nullable|string|max:150',
            'additional_expenses.*.amount' => 'nullable|numeric|min:0',
        ];
    }
}
