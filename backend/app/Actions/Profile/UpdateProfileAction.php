<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Support\RateLimitKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
            // SEC-05: a session token must not become a password-guessing oracle; same budget
            // as the login lockout (5 wrong current passwords per user per 15 minutes).
            $limiterKey = 'profile-current-password|'.RateLimitKey::scope().'|'.$user->getKey();
            $maxAttempts = max(1, (int) config('rate_limits.tenant_login.account_lockout_attempts', 5));
            if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
                throw ValidationException::withMessages([
                    'current_password' => __('auth.account_locked', [
                        'minutes' => (int) ceil(RateLimiter::availableIn($limiterKey) / 60),
                    ]),
                ]);
            }

            if (! Hash::check((string) $validated['current_password'], $user->password)) {
                RateLimiter::hit($limiterKey, max(1, (int) config('rate_limits.tenant_login.account_lockout_minutes', 15)) * 60);

                throw ValidationException::withMessages([
                    'current_password' => __('auth.current_password_incorrect'),
                ]);
            }
            RateLimiter::clear($limiterKey);
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
