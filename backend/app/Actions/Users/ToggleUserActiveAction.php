<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Exception;

final class ToggleUserActiveAction
{
    /**
     * Toggle active state for user preventing disabling own account
     */
    public function execute(int $userId, ?int $currentAuthId = null): User
    {
        $user = User::findOrFail($userId);

        if ($currentAuthId && $user->id === $currentAuthId) {
            throw new Exception(__('auth.cannot_disable_own_account') ?: 'لا يمكنك تعطيل حسابك الشخصي الحالي');
        }

        $user->update(['is_active' => !$user->is_active]);

        return $user;
    }
}
