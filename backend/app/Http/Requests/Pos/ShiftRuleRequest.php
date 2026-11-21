<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Enums\ShiftMode;
use App\Http\Requests\Pos\Concerns\ResolvesTargetStore;
use App\Models\StorePosSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POS-10: GET/PUT /api/v1/stores/{store}/pos-settings/shift-rule - the branch's shift rules only:
 * "require an open shift to sell" and (SHIFT-01) the shift mode (per_cashier | shared).
 * settings.manage OR stores.manage, plus access to {store}. A PUT sends at least one of the two;
 * any other key in the body is ignored.
 */
class ShiftRuleRequest extends FormRequest
{
    use ResolvesTargetStore;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('updateShiftRule', [StorePosSetting::class, $this->targetStore()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->isMethod('PUT')) {
            return [];
        }

        return [
            'require_shift_for_sales' => ['required_without:shift_mode', 'boolean'],
            'shift_mode' => ['required_without:require_shift_for_sales', 'string', Rule::enum(ShiftMode::class)],
        ];
    }
}
