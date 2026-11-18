<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use SensitiveParameter;

/**
 * SIGN-11: the 6-digit code that verifies a self-signup's email address.
 *
 * The code is the only secret carried; the queued payload is encrypted (ShouldBeEncrypted, honoured
 * by SendQueuedMailable), so it never sits in plain text in the jobs table.
 *
 * Security review: it goes to an address nobody has proven yet, so it carries NO user input (no
 * business or owner name, which could be used to phish through the platform's own sender): the
 * code, generic text and the platform branding only.
 */
final class SignupVerificationCodeMail extends PlatformMailable implements ShouldBeEncrypted
{
    public function __construct(
        #[SensitiveParameter] public readonly string $code,
        public readonly int $validMinutes,
        ?string $locale = null,
    ) {
        parent::__construct($locale);
    }

    protected function subjectKey(): string
    {
        return 'mail.signup_code.subject';
    }

    protected function subjectReplace(): array
    {
        return ['code' => $this->code];
    }

    protected function htmlView(): string
    {
        return 'mail.platform.signup-code';
    }

    protected function textView(): string
    {
        return 'mail.platform.signup-code-text';
    }

    protected function viewData(): array
    {
        return [
            'code' => $this->code,
            'validMinutes' => $this->validMinutes,
        ];
    }
}
