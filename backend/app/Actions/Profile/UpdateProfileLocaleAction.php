<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;

final class UpdateProfileLocaleAction
{
    /**
     * Save the user's preferred language (users.locale); nothing else on the profile is touched.
     */
    public function execute(User $user, string $locale): User
    {
        $user->locale = $locale;
        $user->save();

        return $user;
    }
}
