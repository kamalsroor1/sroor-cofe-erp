<?php

declare(strict_types=1);

namespace App\DTOs\Signup;

use App\Models\SignupRequest;
use SensitiveParameter;

/**
 * SELL-01: the approved signup request, plus the owner's temporary password when the server
 * generated it (null when the operator typed one). Returned ONCE in the approve response; the
 * password itself is never stored in plain, audited or logged.
 */
final readonly class ApprovedSignupResult
{
    public function __construct(
        public SignupRequest $signup,
        public string $ownerEmail,
        #[SensitiveParameter] public ?string $temporaryPassword,
    ) {}
}
