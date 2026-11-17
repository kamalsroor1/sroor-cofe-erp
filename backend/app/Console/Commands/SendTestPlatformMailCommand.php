<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\PlatformMailable;
use App\Mail\TestPlatformMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * SIGN-10: ops tool to verify outgoing mail after configuring SMTP.
 *
 *   php artisan mail:test owner@example.com            queue it (exercises the worker too)
 *   php artisan mail:test owner@example.com --now      send synchronously and report the error
 *
 * Output never contains the SMTP credentials or the full recipient address: only the
 * recipient domain, the mailer name, and on failure the exception class plus a message whose
 * e-mail addresses and configured username/password are masked.
 */
final class SendTestPlatformMailCommand extends Command
{
    protected $signature = 'mail:test
        {email : Recipient address}
        {--locale= : ar or en (default: the app locale)}
        {--now : Send synchronously instead of queueing}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('mail.test_command.description'));
    }

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $locale = $this->option('locale');
        $locale = is_string($locale) && $locale !== '' ? $locale : null;

        if (Validator::make(['email' => $email], ['email' => ['required', 'string', 'max:254', 'email:rfc']])->fails()) {
            $this->error((string) __('mail.test_command.invalid_email'));

            return self::FAILURE;
        }

        if ($locale !== null && ! in_array($locale, PlatformMailable::SUPPORTED_LOCALES, true)) {
            $this->error((string) __('mail.test_command.invalid_locale'));

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $domain = strtolower(substr($email, (int) strrpos($email, '@') + 1));
        $replace = ['domain' => $domain, 'mailer' => $mailer];

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn((string) __('mail.test_command.log_mailer_warning', ['mailer' => $mailer]));
        }

        $mail = new TestPlatformMail($locale);

        if (! (bool) $this->option('now')) {
            Mail::to($email)->queue($mail);
            $this->info((string) __('mail.test_command.queued', $replace));
            $this->line((string) __('mail.test_command.queue_hint'));

            return self::SUCCESS;
        }

        try {
            Mail::to($email)->sendNow($mail);
        } catch (Throwable $e) {
            $this->error((string) __('mail.test_command.failed', [
                'exception' => $e::class,
                'reason' => $this->redact($e->getMessage()),
            ]));

            return self::FAILURE;
        }

        $this->info((string) __('mail.test_command.sent', $replace));

        return self::SUCCESS;
    }

    /** Mask e-mail local parts and the configured SMTP credentials in a transport error. */
    private function redact(string $message): string
    {
        foreach (['mail.mailers.smtp.password', 'mail.mailers.smtp.username'] as $key) {
            $secret = config($key);

            if (is_string($secret) && $secret !== '') {
                $message = str_replace($secret, '***', $message);
            }
        }

        $message = (string) preg_replace('/[^\s<>"\'@]+@([A-Za-z0-9.-]+)/', '***@$1', $message);

        return mb_strimwidth($message, 0, 500, '...');
    }
}
