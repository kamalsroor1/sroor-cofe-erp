<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Auth\AccountLockout;

/**
 * When a failed login attempt trips the lockout threshold for one identifier (e.g. email),
 * lock all login identifiers (phone and email) of the matching tenant user so the lock
 * cannot be bypassed by switching identifiers.
 */
final class LockUserLoginIdentifiersAction
{
    public function execute(string $login): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $trimmed = trim($login);
        if ($trimmed === '') {
            return;
        }

        $lower = mb_strtolower($trimmed);

        $user = User::query()
            ->where(function ($query) use ($trimmed, $lower): void {
                $query->where('phone', $trimmed)
                    ->orWhere('email', $trimmed)
                    ->orWhereRaw('LOWER(email) = ?', [$lower]);
            })
            ->first();

        if ($user === null) {
            return;
        }

        AccountLockout::lockForIdentifiers($user->phone, $user->email);
    }
}
