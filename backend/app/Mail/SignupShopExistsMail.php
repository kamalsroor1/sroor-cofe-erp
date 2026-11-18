<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Security review (SIGN-13): sent to a self-signup's VERIFIED address when its email or phone
 * already has a shop (FreeShopGuard). The API only answers a generic 422; this mail tells the
 * mailbox owner what happened. It never says which identity matched; the sign-in link is included
 * only when the shop belongs to this very mailbox (matched on the canonical email), otherwise the
 * owner is pointed to support (contacts in the platform layout).
 */
final class SignupShopExistsMail extends PlatformMailable
{
    public function __construct(
        public readonly ?string $loginUrl,
        ?string $locale = null,
    ) {
        parent::__construct($locale);
    }

    protected function subjectKey(): string
    {
        return 'mail.signup_shop_exists.subject';
    }

    protected function htmlView(): string
    {
        return 'mail.platform.signup-shop-exists';
    }

    protected function textView(): string
    {
        return 'mail.platform.signup-shop-exists-text';
    }

    protected function viewData(): array
    {
        return ['loginUrl' => $this->loginUrl];
    }
}
