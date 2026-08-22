<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;

final class UpdateProfileAction
{
    /**
     * Update user profile, theme preference and optional password
     */
    public function execute(User $user, array $validated): User
    {
        if (!empty($validated['new_password'])) {
            if (!Hash::check((string)$validated['current_password'], $user->password)) {
                throw new Exception(__('auth.current_password_incorrect') ?: 'كلمة المرور الحالية غير صحيحة');
            }
            $user->password = Hash::make((string)$validated['new_password']);
        }

        $user->name = (string)$validated['name'];
        $user->phone = (string)$validated['phone'];
        $user->email = isset($validated['email']) && $validated['email'] !== '' ? (string)$validated['email'] : null;
        $user->theme_preference = (string)($validated['theme_preference'] ?? 'dark');
        $user->save();

        return $user;
    }
}
