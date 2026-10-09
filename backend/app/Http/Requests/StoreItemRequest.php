<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Settings\TenantSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('items.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:items,code'],
            'category' => ['nullable', 'string', 'max:100'],
            // SETG-10: the unit must come from the tenant's unit list (settings `inventory_units`).
            'unit' => ['required', 'string', 'max:50', $this->tenantUnitRule()],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'min_selling_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // POSB-2: explicit weighed-item flag (scale labels, fractional qty).
            'is_weighted' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    private function tenantUnitRule(): Closure
    {
        $units = app(TenantSettings::class)->inventoryUnits();

        return function (string $attribute, mixed $value, Closure $fail) use ($units): void {
            if (is_string($value) && ! in_array(trim($value), $units, true)) {
                $fail(__('inventory.unit_not_in_tenant_list', ['unit' => $value]));
            }
        };
    }
}
