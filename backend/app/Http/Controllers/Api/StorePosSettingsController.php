<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\POS\GetStorePosSettingsAction;
use App\Actions\POS\UpdateStorePosSettingsAction;
use App\DTOs\Pos\StorePosSettingsDTO;
use App\Http\Controllers\Controller;
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
}
