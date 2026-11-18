<?php

declare(strict_types=1);

namespace App\DTOs\Signup;

/**
 * SIGN-13 + security review: what `signup:prune-unverified` did (row counts).
 */
final readonly class PruneSignupsResult
{
    public function __construct(
        public int $deleted,
        public int $tokensRevoked,
        public int $scrubbed,
    ) {}
}
