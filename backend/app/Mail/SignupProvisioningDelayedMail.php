<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * SIGN-12: sent once when a verified self-signup could not be provisioned automatically: the
 * team was alerted and is on it; no action is needed from the owner. No technical detail.
 */
final class SignupProvisioningDelayedMail extends PlatformMailable
{
    public function __construct(
        public readonly string $businessName,
        ?string $locale = null,
    ) {
        parent::__construct($locale);
    }

    protected function subjectKey(): string
    {
        return 'mail.signup_delayed.subject';
    }

    protected function subjectReplace(): array
    {
        return ['business' => $this->businessName];
    }

    protected function htmlView(): string
    {
        return 'mail.platform.signup-delayed';
    }

    protected function textView(): string
    {
        return 'mail.platform.signup-delayed-text';
    }

    protected function viewData(): array
    {
        return ['businessName' => $this->businessName];
    }
}
