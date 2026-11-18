<?php

declare(strict_types=1);

namespace App\Services\Signup;

/**
 * Security review: FreeShopGuard found an existing shop (or in-flight signup) for an identity.
 * `emailOwnedSlug` is the workspace code of the matched shop ONLY when it was matched by the
 * canonical email (that mailbox owns it, so its sign-in link may be mailed there); null for a
 * phone-only match or an in-flight signup. Never exposed to the client.
 */
final readonly class FreeShopMatch
{
    public function __construct(public ?string $emailOwnedSlug) {}
}
