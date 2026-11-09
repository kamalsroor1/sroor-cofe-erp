<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Item;
use App\Services\Settings\TenantSettings;
use App\Support\CostVisibility;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateItemRequest extends FormRequest
{
    use Concerns\ValidatesItemPriceTiers;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('items.edit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Only users who can see the cost may change it; for everyone else the key is silently ignored.
        if (! CostVisibility::allows($this->user())) {
            $this->replace(Arr::except($this->all(), ['cost_price']));
        }
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateWholesaleAgainstFloor($validator, Item::query()->find($this->route('id')));
    }

    public function rules(): array
    {
        $itemId = $this->route('id') ?? $this->route('item');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:items,code,'.$itemId],
            'category' => ['nullable', 'string', 'max:100'],
            // SETG-10: a unit from the tenant's list; a legacy item may keep its current unit unchanged.
            'unit' => ['required', 'string', 'max:50', $this->tenantUnitRule($itemId)],
            // Optional on update: a missing/null cost keeps the stored one (see prepareForValidation).
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'min_selling_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            // BE-08: separate from the floor; 0 / empty = not set (retail applies).
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // POSB-2: explicit weighed-item flag (scale labels, fractional qty).
            'is_weighted' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    private function tenantUnitRule(mixed $itemId): Closure
    {
        $units = app(TenantSettings::class)->inventoryUnits();
        $currentUnit = is_numeric($itemId)
            ? Item::query()->whereKey((int) $itemId)->value('unit')
            : null;

        return function (string $attribute, mixed $value, Closure $fail) use ($units, $currentUnit): void {
            if (! is_string($value)) {
                return;
            }

            $unit = trim($value);
            if ($currentUnit !== null && $unit === trim((string) $currentUnit)) {
                return;
            }

            if (! in_array($unit, $units, true)) {
                $fail(__('inventory.unit_not_in_tenant_list', ['unit' => $value]));
            }
        };
    }
}
