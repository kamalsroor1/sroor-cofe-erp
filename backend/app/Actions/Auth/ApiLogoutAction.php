<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\ActivityLogService;

final class ApiLogoutAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    /**
     * Revoke current Sanctum token and logout
     */
    public function execute(User $user): void
    {
        // 1. Revoke current Sanctum token
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        } elseif (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        // 2. Clear api_token column
        $user->update(['api_token' => null]);

        // 3. Log event
        $this->activityLogService->log(
            module: 'auth',
            action: 'api_logout',
            description: "تسجيل خروج API للمستخدم [{$user->name}]",
            subject: $user,
            userId: $user->id
        );
    }
}
