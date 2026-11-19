<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Mail\PlatformMailable;
use App\Mail\TenantPasswordResetMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\Auth\TenantPasswordBroker;
use App\Support\PlatformHosts;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Timebox;

/**
 * SIGN-15: POST /api/v1/auth/forgot-password on a shop host.
 *
 * The caller ALWAYS gets the same answer: this action returns nothing and never throws for an
 * unknown, inactive, deleted or throttled email, nor when no shop is resolved. Only an ACTIVE user
 * of the CURRENT shop whose email matches gets a mail (queued TenantPasswordResetMail, so the
 * response time does not depend on the mailer).
 *
 *  - Tokens: the per-tenant broker (TenantPasswordBroker): hashed in the shop's own
 *    `password_reset_tokens`, valid auth.passwords.tenant_users.expire minutes (30), one live
 *    token per email (a new request replaces it), at most one new token per 60 s.
 *  - Link: built from the shop's own domain (first domain row, else "<slug>.<base domain>"),
 *    never from the request Host header, so a forged Host cannot steal the token.
 *  - Timing: the whole call runs inside a Timebox, so unknown and real emails answer alike.
 *  - Only a sent link is activity-logged (no token, no URL).
 */
final class SendTenantPasswordResetLinkAction
{
    /** Minimum duration of every call (microseconds). */
    private const MIN_DURATION_US = 300_000;

    public function __construct(
        private readonly TenantPasswordBroker $brokers,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(string $email, ?string $requestLocale = null): void
    {
        (new Timebox)->call(function () use ($email, $requestLocale): void {
            $this->send(mb_strtolower(trim($email)), $requestLocale);
        }, self::MIN_DURATION_US);
    }

    private function send(string $email, ?string $requestLocale): void
    {
        // Without a resolved shop, App\Models\User would read the CENTRAL database: answer the same 200.
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;

        if (! $tenant instanceof Tenant || $email === '') {
            return;
        }

        $baseUrl = $this->resetPageUrl($tenant);
        $minutes = TenantPasswordBroker::expireMinutes();

        // Every outcome (sent, throttled, unknown) answers the caller the same; the status is not used.
        $this->brokers->make()->sendResetLink(
            // is_active is a credential: an inactive account is "not found" (same answer, no mail).
            ['email' => $email, 'is_active' => true],
            function (User $user, string $token) use ($baseUrl, $minutes, $requestLocale, $tenant): string {
                $url = $baseUrl.'?'.http_build_query(['token' => $token, 'email' => $user->email]);

                Mail::to((string) $user->email)->queue(new TenantPasswordResetMail(
                    resetUrl: $url,
                    userName: (string) $user->name,
                    shopName: (string) $tenant->name,
                    expireMinutes: $minutes,
                    locale: PlatformMailable::resolveLocale($user->locale ?: $requestLocale),
                ));

                $this->activityLogService->log(
                    module: 'auth',
                    action: 'password_reset_requested',
                    description: __('auth.password_reset.activity_requested', ['name' => $user->name]),
                    subject: $user,
                    userId: (int) $user->id,
                );

                return PasswordBroker::RESET_LINK_SENT;
            },
        );
    }

    private function resetPageUrl(Tenant $tenant): string
    {
        $host = $tenant->domains()->orderBy('id')->value('domain');

        return PlatformHosts::url(
            is_string($host) && $host !== '' ? $host : PlatformHosts::tenantHost((string) $tenant->slug),
            '/reset-password',
        );
    }
}
