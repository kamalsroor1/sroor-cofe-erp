<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Profile\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly UpdateProfileAction $updateProfileAction
    ) {}

    /**
     * Get authenticated user profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => (new UserResource($user->load(['roles', 'defaultStore'])))->resolve(),
        ]);
    }

    /**
     * Update authenticated user profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $updated = $this->updateProfileAction->execute($user, $request->validated());

            return response()->json([
                'success' => true,
                'message' => __('auth.profile_updated') ?: 'تم تحديث الملف الشخصي بنجاح',
                'data'    => (new UserResource($updated->load(['roles', 'defaultStore'])))->resolve(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
