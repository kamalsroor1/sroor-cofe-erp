<?php

declare(strict_types=1);

namespace App\DTOs\Signup;

use SensitiveParameter;

/**
 * SIGN-11: what POST /api/v1/signup returns ONCE: the opaque token (only its hash is stored) and
 * what the code screen needs. A honeypot hit gets the same shape with a token that matches nothing.
 */
final readonly class SelfSignupStarted
{
    public function __construct(
        #[SensitiveParameter] public string $token,
        public string $maskedEmail,
        public string $subdomain,
        public int $codeExpiresIn,
        public int $resendAvailableIn,
    ) {}
}
