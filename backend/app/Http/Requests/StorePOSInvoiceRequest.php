<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesWalkInCustomer;
use App\Models\Item;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use App\Support\TenantClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StorePOSInvoiceRequest extends FormRequest
{
    use Concerns\ValidatesCheckoutPayments;
    use Concerns\ValidatesPriceOverride;
    use ResolvesWalkInCustomer;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin')
            || $this->user()?->can('pos.access')
            || $this->user()?->can('invoices.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Idempotency: accept the key from the body or the standard Idempotency-Key header.
        if (! $this->filled('client_uuid') && $this->hasHeader('Idempotency-Key')) {
            $this->merge(['client_uuid' => (string) $this->header('Idempotency-Key')]);
        }

        $this->normalizeCheckoutPayload();

        // Backward compatibility: legacy clients send a flat discount_amount.
        if ($this->input('discount_value') === null && $this->filled('discount_amount')) {
            $this->merge([
                'discount_type' => 'fixed',
                'discount_value' => $this->input('discount_amount'),
            ]);
        }

        // STOR-2 (CTO Q14): the sale is made from the active store only (store.active:concrete
        // resolved and access-checked X-Store-Id). A body store_id is access-checked (403, and
        // `all` is never a store to sell from) and otherwise ignored until STOR-4 stops sending it.
        if (ClientStoreGuard::verified($this) === ActiveStore::ALL) {
            ClientStoreGuard::deny();
        }

        $storeId = (int) app(ActiveStore::class)->requireStore()->id;

        $paymentType = $this->input('payment_type') ?? $this->input('invoice_type') ?? 'cash';
        $paymentMethod = $this->input('payment_method') ?? 'cash';
        if ($paymentMethod === 'smart_wallet') {
            $paymentMethod = 'e_wallet';
        }

        // SEC-1 (L13): no customer = walk-in customer, resolved in passedValidation() (after
        // authorize() and the rules), never here.
        $this->normalizeWalkInCustomerId();

        $this->merge([
            'store_id' => $storeId,
            // BE-08: the POS sale is always dated by the server (tenant clock, timezone + cutoff). The
            // client date is ignored: there is no back-date permission, and a stale offline clock must not
            // move revenue into a closed day or shift.
            'invoice_date' => app(TenantClock::class)->businessDate(),
            'payment_type' => $paymentType,
            'payment_method' => $paymentMethod,
        ]);
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['nullable', 'uuid'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'store_id' => ['required', 'exists:stores,id'],
            'invoice_date' => ['required', 'date'],
            'payment_type' => ['required', 'string', 'in:cash,credit,partial'],
            'payment_method' => ['required', 'string', 'in:'.self::CHECKOUT_PAYMENT_METHODS],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:500'],
            ...$this->checkoutPaymentAndExpenseRules(),
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            ...$this->saleLineNumberRules('discount'),
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            ...$this->priceOverrideRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->rejectFractionalQuantityForUnweightedItems($validator),
        ];
    }

    /**
     * POS-06: only weighted items (items.is_weighted) may be sold in fractions; every other item needs a whole quantity.
     */
    private function rejectFractionalQuantityForUnweightedItems(Validator $validator): void
    {
        $fractionalRows = [];
        foreach ((array) $this->input('items', []) as $index => $row) {
            $quantity = is_array($row) ? ($row['quantity'] ?? null) : null;
            // Plain decimals only: exponent notation and other shapes are rejected by the quantity rule itself.
            if ((is_string($quantity) || is_int($quantity) || is_float($quantity))
                && preg_match('/^\d+(\.\d+)?$/', (string) $quantity) === 1
                && bccomp(bcmod((string) $quantity, '1', 3), '0', 3) !== 0) {
                $fractionalRows[$index] = (int) ($row['item_id'] ?? 0);
            }
        }

        if ($fractionalRows === []) {
            return;
        }

        $weighted = Item::query()->whereIn('id', array_values($fractionalRows))->get()
            ->filter(fn (Item $item): bool => $item->isSoldByWeight())
            ->pluck('id')
            ->all();
        foreach ($fractionalRows as $index => $itemId) {
            if (! in_array($itemId, $weighted, true)) {
                $validator->errors()->add("items.{$index}.quantity", __('pos.quantity_must_be_whole'));
            }
        }
    }
}
