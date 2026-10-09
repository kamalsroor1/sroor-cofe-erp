<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Item;
use App\Services\Settings\TenantSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('items.edit') ?? false;
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
            'cost_price' => ['required', 'numeric', 'min:0'],
            'min_selling_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
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
