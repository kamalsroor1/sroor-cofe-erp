<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Profile\UpdateProfileAction;
use App\Actions\Profile\UpdateProfileLocaleAction;
use App\Actions\Users\SetUserPosPinAction;
use App\DTOs\Users\SetUserPosPinDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileLocaleRequest;
use App\Http\Requests\UpdateProfilePosPinRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\SafeJsonError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly UpdateProfileAction $updateProfileAction,
        private readonly SetUserPosPinAction $setUserPosPinAction,
        private readonly UpdateProfileLocaleAction $updateProfileLocaleAction,
    ) {}

    /**
     * Get authenticated user profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => (new UserResource($user->load(['roles', 'defaultStore'])))->resolve(),
        ], 200);
    }

    /**
     * Update authenticated user profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401);
        }

        try {
            $updated = $this->updateProfileAction->execute($user, $request->validated());

            return response()->json([
                'success' => true,
                'message' => __('auth.profile_updated'),
                'data' => (new UserResource($updated->load(['roles', 'defaultStore'])))->resolve(),
            ], 200);
        } catch (Throwable $e) {
            // SEC-1 (L4): 422/403/404 pass through; anything else is logged, never echoed.
            return SafeJsonError::respond($e);
        }
    }

    /**
     * BE-08: set (or clear with null) the signed-in user's manager approval PIN.
     */
    public function updatePosPin(UpdateProfilePosPinRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 401);
        }

        $user = $this->setUserPosPinAction->execute(SetUserPosPinDTO::fromArray((int) $user->id, $request->validated()), $user);

        return response()->json([
            'success' => true,
            'message' => __('pos.pos_pin_updated'),
            'has_pos_pin' => $user->pos_pin !== null,
        ], 200);
    }

    /**
     * Save only the authenticated user's preferred language
     */
    public function updateLocale(UpdateProfileLocaleRequest $request): JsonResponse
    {
        $user = $this->updateProfileLocaleAction->execute($request->user(), $request->validated('locale'));

        return response()->json([
            'success' => true,
            'data' => ['locale' => $user->locale],
        ], 200);
    }
}
