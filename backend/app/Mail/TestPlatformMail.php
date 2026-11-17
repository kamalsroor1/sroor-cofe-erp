<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * SIGN-10: the SMTP check sent by `php artisan mail:test {email}`. No data beyond branding.
 */
final class TestPlatformMail extends PlatformMailable
{
    protected function subjectKey(): string
    {
        return 'mail.test.subject';
    }

    protected function htmlView(): string
    {
        return 'mail.platform.test';
    }

    protected function textView(): string
    {
        return 'mail.platform.test-text';
    }
}
