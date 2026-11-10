<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Http\Requests\Pos\Concerns\ResolvesTargetStore;
use App\Models\StorePosSetting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POS-10: GET/PUT /api/v1/stores/{store}/pos-settings/shift-rule - the "require an open shift to
 * sell" switch only. settings.manage OR stores.manage, plus access to {store}. Any other key in the
 * body is ignored (only `require_shift_for_sales` is validated and used).
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

        return ['require_shift_for_sales' => ['required', 'boolean']];
    }
}
