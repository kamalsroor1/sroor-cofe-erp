<?php

declare(strict_types=1);

namespace App\DTOs\Users;

use App\Models\User;
use SensitiveParameter;

/**
 * SELL-01: the account a temporary password was set on, and that password in plain text.
 * Lives for one response only: the controller returns it ONCE and nothing stores or logs it.
 */
final readonly class TemporaryPasswordResult
{
    public function __construct(
        public User $user,
        #[SensitiveParameter] public string $temporaryPassword,
        /** Console owner reset only: how the owner was identified, `email` or `fallback`. */
        public ?string $matchedBy = null,
    ) {}
}
