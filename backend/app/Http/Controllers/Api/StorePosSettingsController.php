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
     * POS-10: only the shift rule (settings.manage or stores.manage).
     */
    public function showShiftRule(ShiftRuleRequest $request): JsonResponse
    {
        $setting = $this->getStorePosSettingsAction->execute((int) $request->targetStore()->id);

        return response()->json([
            'success' => true,
            'data' => ['store_id' => (int) $setting->store_id, 'require_shift_for_sales' => (bool) $setting->require_shift_for_sales],
        ]);
    }

    public function updateShiftRule(ShiftRuleRequest $request): JsonResponse
    {
        // Only this key reaches the DTO, so no other setting can change through this endpoint.
        $dto = StorePosSettingsDTO::fromArray(['require_shift_for_sales' => (bool) $request->validated('require_shift_for_sales')]);
        $setting = $this->updateStorePosSettingsAction->execute($request->targetStore(), $dto);

        return response()->json([
            'success' => true,
            'message' => __('pos.pos_settings_saved'),
            'data' => ['store_id' => (int) $setting->store_id, 'require_shift_for_sales' => (bool) $setting->require_shift_for_sales],
        ]);
    }
}
