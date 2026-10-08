<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Http\Requests\Pos\Concerns\ResolvesTargetStore;
use App\Models\StorePosSetting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POSB-2: GET /api/v1/stores/{store}/pos-settings — settings.manage + access to {store}.
 */
class ShowStorePosSettingsRequest extends FormRequest
{
    use ResolvesTargetStore;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view', [StorePosSetting::class, $this->targetStore()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
