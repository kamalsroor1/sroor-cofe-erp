<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\ScreenLock\UnlockScreenAction;
use App\DTOs\Auth\UnlockScreenDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UnlockScreenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * SET-02: server-side check behind the SPA idle lock screen (`pos_idle_lock_minutes`).
 */
final class ScreenLockController extends Controller
{
    public function __construct(
        private readonly UnlockScreenAction $unlockScreenAction,
    ) {}

    public function unlock(UnlockScreenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->unlockScreenAction->execute($user, UnlockScreenDTO::fromArray($request->validated()));

        return response()->json([
            'success' => true,
            'message' => __('screen_lock.unlocked'),
            'data' => ['user_id' => (int) $user->id],
        ]);
    }
}
