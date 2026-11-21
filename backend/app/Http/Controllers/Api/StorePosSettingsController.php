<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\POS\GetStorePosSettingsAction;
use App\Actions\POS\UpdateStorePosSettingsAction;
use App\DTOs\Pos\StorePosSettingsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ShiftRuleRequest;
use App\Http\Requests\Pos\ShowStorePosSettingsRequest;
use App\Http\Requests\Pos\UpdateStorePosSettingsRequest;
use App\Http\Resources\StorePosSettingResource;
use App\Models\StorePosSetting;
use Illuminate\Http\JsonResponse;

/**
 * POSB-2: per-store POS settings (scale-label parser + max discount percent).
 */
final class StorePosSettingsController extends Controller
{
    public function __construct(
        private readonly GetStorePosSettingsAction $getStorePosSettingsAction,
        private readonly UpdateStorePosSettingsAction $updateStorePosSettingsAction,
    ) {}

    public function show(ShowStorePosSettingsRequest $request): JsonResponse
    {
        $setting = $this->getStorePosSettingsAction->execute((int) $request->targetStore()->id);

        return response()->json([
            'success' => true,
            'data' => (new StorePosSettingResource($setting))->resolve($request),
        ]);
    }

    public function update(UpdateStorePosSettingsRequest $request): JsonResponse
    {
        $dto = StorePosSettingsDTO::fromArray($request->validated());
        $setting = $this->updateStorePosSettingsAction->execute($request->targetStore(), $dto);

        return response()->json([
            'success' => true,
            'message' => __('pos.pos_settings_saved'),
            'data' => (new StorePosSettingResource($setting))->resolve($request),
        ]);
    }

    /**
     * POS-10 / SHIFT-01: only the shift rules - the requirement and the shift mode (settings.manage
     * or stores.manage).
     */
    public function showShiftRule(ShiftRuleRequest $request): JsonResponse
    {
        $setting = $this->getStorePosSettingsAction->execute((int) $request->targetStore()->id);

        return response()->json([
            'success' => true,
            'data' => $this->shiftRule($setting),
        ]);
    }

    public function updateShiftRule(ShiftRuleRequest $request): JsonResponse
    {
        // Only these keys reach the DTO, so no other setting can change through this endpoint.
        $dto = StorePosSettingsDTO::fromArray(array_intersect_key(
            $request->validated(),
            array_flip(['require_shift_for_sales', 'shift_mode']),
        ));
        $setting = $this->updateStorePosSettingsAction->execute($request->targetStore(), $dto);

        return response()->json([
            'success' => true,
            'message' => __('pos.pos_settings_saved'),
            'data' => $this->shiftRule($setting),
        ]);
    }

    /**
     * @return array{store_id: int, require_shift_for_sales: bool, shift_mode: string}
     */
    private function shiftRule(StorePosSetting $setting): array
    {
        return [
            'store_id' => (int) $setting->store_id,
            'require_shift_for_sales' => (bool) $setting->require_shift_for_sales,
            // SHIFT-01: per_cashier | shared.
            'shift_mode' => $setting->shiftMode()->value,
        ];
    }
}
