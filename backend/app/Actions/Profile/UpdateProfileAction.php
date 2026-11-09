<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UpdateProfileAction
{
    /**
     * Update user profile, theme preference and optional password
     */
    public function execute(User $user, array $validated): User
    {
        $passwordChanged = false;

        if (! empty($validated['new_password'])) {
            if (! Hash::check((string) $validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => __('auth.current_password_incorrect'),
                ]);
            }
            $user->password = Hash::make((string) $validated['new_password']);
            $passwordChanged = true;
        }

        $user->name = (string) $validated['name'];
        $user->phone = (string) $validated['phone'];
        $user->email = isset($validated['email']) && $validated['email'] !== '' ? (string) $validated['email'] : null;
        $user->theme_preference = (string) ($validated['theme_preference'] ?? 'dark');
        // SETG-3: only touch the saved language when the client sent the key (null clears it).
        if (array_key_exists('locale', $validated)) {
            $user->locale = is_string($validated['locale']) ? $validated['locale'] : null;
        }
        $user->save();

        // SEC-03: a new password ends every other session; the token making this request stays.
        if ($passwordChanged) {
            $currentId = data_get($user->currentAccessToken(), 'id'); // null when no token is bound (Sanctum docblock says non-null)
            $user->tokens()
                ->when($currentId !== null, fn ($query) => $query->whereKeyNot($currentId))
                ->delete();
        }

        return $user;
    }
}
