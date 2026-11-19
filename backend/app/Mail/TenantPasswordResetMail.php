<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * SIGN-15: "reset your password" link for a SHOP user (owner or staff with an email), sent by the
 * platform on the shop's behalf. The link opens the shop's own /reset-password page and expires
 * after auth.passwords.tenant_users.expire minutes; it is single-use.
 */
final class TenantPasswordResetMail extends PlatformMailable
{
    public function __construct(
        public readonly string $resetUrl,
        public readonly string $userName,
        public readonly string $shopName,
        public readonly int $expireMinutes,
        ?string $locale = null,
    ) {
        parent::__construct($locale);
    }

    protected function subjectKey(): string
    {
        return 'mail.tenant_password_reset.subject';
    }

    protected function htmlView(): string
    {
        return 'mail.platform.tenant-password-reset';
    }

    protected function textView(): string
    {
        return 'mail.platform.tenant-password-reset-text';
    }

    protected function viewData(): array
    {
        return [
            'resetUrl' => $this->resetUrl,
            'userName' => $this->userName,
            'shopName' => $this->shopName,
            'expireMinutes' => $this->expireMinutes,
        ];
    }
}
