<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\POS\GetPosQuickKeysAction;
use App\Actions\POS\ReplacePosQuickKeysAction;
use App\DTOs\Pos\QuickKeyDTO;
use App\Enums\PosQuickKeyColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ListPosQuickKeysRequest;
use App\Http\Requests\Pos\ReplacePosQuickKeysRequest;
use App\Http\Resources\PosQuickKeyResource;
use App\Models\PosQuickKey;
use Illuminate\Http\JsonResponse;

/**
 * POSB-6: per-store POS quick keys (read for the active store, full replace per store).
 */
final class PosQuickKeyController extends Controller
{
    public function __construct(
        private readonly GetPosQuickKeysAction $getPosQuickKeysAction,
        private readonly ReplacePosQuickKeysAction $replacePosQuickKeysAction,
    ) {}

    public function index(ListPosQuickKeysRequest $request): JsonResponse
    {
        $storeId = (int) $request->activeStore()->id;
        $keys = $this->getPosQuickKeysAction->execute($storeId);

        return response()->json([
            'success' => true,
            'data' => PosQuickKeyResource::collection($keys)->resolve($request),
            'meta' => $this->meta($storeId),
        ]);
    }

    public function replace(ReplacePosQuickKeysRequest $request): JsonResponse
    {
        $store = $request->targetStore();
        $keys = $this->replacePosQuickKeysAction->execute($store, QuickKeyDTO::collection($request->validated('keys', [])));

        return response()->json([
            'success' => true,
            'message' => __('pos.quick_keys_saved'),
            'data' => PosQuickKeyResource::collection($keys)->resolve($request),
            'meta' => $this->meta((int) $store->id),
        ]);
    }

    /**
     * @return array{store_id: int, max_pages: int, max_position: int, max_keys: int, colors: list<string>}
     */
    private function meta(int $storeId): array
    {
        return [
            'store_id' => $storeId,
            'max_pages' => PosQuickKey::MAX_PAGES,
            'max_position' => PosQuickKey::MAX_POSITION,
            'max_keys' => PosQuickKey::MAX_KEYS,
            'colors' => PosQuickKeyColor::values(),
        ];
    }
}
