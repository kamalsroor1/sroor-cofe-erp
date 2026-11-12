<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Support\Auth\CurrentPasswordVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UpdateProfileAction
{
    public function __construct(
        private readonly CurrentPasswordVerifier $currentPasswordVerifier,
    ) {}

    /**
     * Update user profile, theme preference and optional password.
     *
     * One transaction on the locked user row (SELL-01 review: the current password is compared
     * against fresh data, not the row loaded by the token guard). A wrong current password writes
     * nothing and is counted AFTER the transaction (SEC-05 lockout shared with
     * POST /auth/change-password), so the count can never be rolled back with it.
     */
    public function execute(User $user, array $validated): User
    {
        $changesPassword = ! empty($validated['new_password']);
        if ($changesPassword) {
            $this->currentPasswordVerifier->assertNotLocked($user);
        }
        $currentTokenId = data_get($user->currentAccessToken(), 'id'); // null when no token is bound (Sanctum docblock says non-null)

        $updated = DB::transaction(function () use ($user, $validated, $changesPassword, $currentTokenId): ?User {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($changesPassword) {
                if (! $this->currentPasswordVerifier->matches($locked, (string) $validated['current_password'])) {
                    return null;
                }
                $locked->password = Hash::make((string) $validated['new_password']);
                // SELL-01: a password the user chose themselves ends any forced change.
                $locked->forceFill(['must_change_password' => false]);
            }

            $locked->name = (string) $validated['name'];
            $locked->phone = (string) $validated['phone'];
            $locked->email = isset($validated['email']) && $validated['email'] !== '' ? (string) $validated['email'] : null;
            $locked->theme_preference = (string) ($validated['theme_preference'] ?? 'dark');
            // SETG-3: only touch the saved language when the client sent the key (null clears it).
            if (array_key_exists('locale', $validated)) {
                $locked->locale = is_string($validated['locale']) ? $validated['locale'] : null;
            }
            $locked->save();

            // SEC-03: a new password ends every other session; the token making this request stays.
            if ($changesPassword) {
                $locked->tokens()
                    ->when($currentTokenId !== null, fn ($query) => $query->whereKeyNot($currentTokenId))
                    ->delete();
            }

            return $locked;
        });

        if ($updated === null) {
            $this->currentPasswordVerifier->failed($user);
        }

        if ($changesPassword) {
            $this->currentPasswordVerifier->passed($user);
        }

        return $updated;
    }
}
