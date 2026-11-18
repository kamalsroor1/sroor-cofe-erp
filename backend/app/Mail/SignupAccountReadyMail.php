<?php

declare(strict_types=1);

namespace App\Mail;

use Carbon\CarbonImmutable;

/**
 * SIGN-12: "your shop is ready", sent once when a self-signup's tenant database is ready.
 *
 * Carries the shop name, sign-in URL and login email, the plan and the trial end (if any), the
 * help URL; the support contact is in the shared layout. NEVER a password: the owner chose it.
 */
final class SignupAccountReadyMail extends PlatformMailable
{
    public function __construct(
        public readonly string $businessName,
        public readonly string $loginUrl,
        public readonly string $loginEmail,
        public readonly string $planName,
        public readonly ?CarbonImmutable $trialEndsAt,
        public readonly string $helpUrl,
        ?string $locale = null,
    ) {
        parent::__construct($locale);
    }

    protected function subjectKey(): string
    {
        return 'mail.signup_ready.subject';
    }

    protected function subjectReplace(): array
    {
        return ['business' => $this->businessName];
    }

    protected function htmlView(): string
    {
        return 'mail.platform.signup-ready';
    }

    protected function textView(): string
    {
        return 'mail.platform.signup-ready-text';
    }

    protected function viewData(): array
    {
        $locale = self::resolveLocale($this->locale);

        return [
            'businessName' => $this->businessName,
            'loginUrl' => $this->loginUrl,
            'loginEmail' => $this->loginEmail,
            'planName' => $this->planName,
            'trialEndsOn' => $this->trialEndsAt?->locale($locale)->translatedFormat('j F Y'),
            'helpUrl' => $this->helpUrl,
        ];
    }
}
