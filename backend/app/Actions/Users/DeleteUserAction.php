<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

final class DeleteUserAction
{
    /**
     * Delete user safely preventing self-deletion
     */
    public function execute(int $userId, ?int $currentAuthId = null): bool
    {
        $user = User::findOrFail($userId);

        if ($currentAuthId && $user->id === $currentAuthId) {
            throw new Exception(__('auth.cannot_delete_own_account') ?: 'لا يمكنك حذف حسابك الشخصي الحالي');
        }

        return (bool)DB::transaction(function () use ($user) {
            return $user->delete();
        });
    }
}
