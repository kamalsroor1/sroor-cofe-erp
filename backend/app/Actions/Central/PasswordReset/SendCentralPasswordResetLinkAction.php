<?php

declare(strict_types=1);

namespace App\Actions\Central\PasswordReset;

use App\Actions\Central\Data\CentralAuthSettings;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * POST /api/v1/super-admin/auth/forgot-password (IDEN-1.12).
 *
 * The caller ALWAYS gets the same answer: this action returns nothing and never throws for
 * an unknown, inactive or throttled email. Only an active operator gets a mail, queued
 * (so the response time does not depend on the mailer either), with a link built from
 * central.password_reset_url, never from the request Host header.
 *
 * Tokens come from the `central_users` broker (Fortify's config('fortify.passwords')),
 * stored hashed in the central `central_password_reset_tokens`. Every request is audited
 * `password_reset_requested` with its real outcome (recordAttempt: survives rollbacks).
 *
 * Timing: an unknown or inactive email does the broker's expensive work too (one bcrypt hash
 * of a random token + the reset-token lookup, see simulateBrokerWork()), so the response time
 * does not tell whether the account exists. What remains different: a real account also writes
 * the token row and pushes one mail job onto the queue (a few ms of I/O, no mail sending).
 */
final class SendCentralPasswordResetLinkAction
{
    public function __construct(
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $user = CentralUser::query()->where('email', $email)->first();

        if (! $user instanceof CentralUser || ! $user->is_active) {
            $this->simulateBrokerWork($email);
            $this->audit($email, $user, $user === null ? 'unknown_email' : 'inactive');

            return;
        }

        $baseUrl = trim((string) config('central.password_reset_url', ''));

        if ($baseUrl === '') {
            Log::warning('Central password reset requested but central.password_reset_url (CENTRAL_PASSWORD_RESET_URL) is not configured; no mail sent.');
            $this->audit($email, $user, 'reset_url_not_configured');

            return;
        }

        $status = CentralAuthSettings::passwordBroker()->sendResetLink(
            ['email' => $email],
            function (CentralUser $user, string $token) use ($baseUrl): void {
                Mail::to($user->email)->queue($this->mail($user, $this->resetUrl($baseUrl, $token, $user->email)));
            },
        );

        $this->audit($email, $user, $status === PasswordBroker::RESET_LINK_SENT ? 'sent' : 'throttled');
    }

    /**
     * Same cost as PasswordBroker::sendResetLink for an existing account: the token-table read
     * done by the throttle check and the bcrypt hash of a fresh token. Nothing is written.
     */
    private function simulateBrokerWork(string $email): void
    {
        $connection = (string) config('auth.passwords.central_users.connection', (new CentralUser)->getConnectionName());
        $table = (string) config('auth.passwords.central_users.table', 'central_password_reset_tokens');

        DB::connection($connection)->table($table)->where('email', $email)->exists();

        Hash::make(Str::random(64));
    }

    private function resetUrl(string $baseUrl, string $token, string $email): string
    {
        return $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').http_build_query([
            'token' => $token,
            'email' => $email,
        ]);
    }

    private function mail(CentralUser $user, string $url): Mailable
    {
        $minutes = (int) config('auth.passwords.central_users.expire', 30);

        $html = '<p>'.e(__('central_auth.password_reset_mail.greeting', ['name' => $user->name])).'</p>'
            .'<p>'.e(__('central_auth.password_reset_mail.intro')).'</p>'
            .'<p><a href="'.e($url).'">'.e(__('central_auth.password_reset_mail.action')).'</a></p>'
            .'<p>'.e(__('central_auth.password_reset_mail.expiry', ['minutes' => $minutes])).'</p>'
            .'<p>'.e(__('central_auth.password_reset_mail.ignore')).'</p>';

        return (new Mailable)
            ->subject((string) __('central_auth.password_reset_mail.subject'))
            ->html($html);
    }

    private function audit(string $email, ?CentralUser $user, string $outcome): void
    {
        $this->auditLogger->recordAttempt(
            CentralAuditEvent::PasswordResetRequested,
            ['email' => $email, 'outcome' => $outcome],
            subject: $user,
        );
    }
}
