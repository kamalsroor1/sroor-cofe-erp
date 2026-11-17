<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\Health\Channels\PlatformTelegramChannel;
use App\Health\Notifications\PlatformMailFailedNotification;
use App\Http\Resources\BrandingResource;
use App\Services\Branding\PlatformBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * SIGN-10: base of every email the PLATFORM (SaaS operator) sends: signup code, "your account
 * is ready", reminders. Never use it for shop-to-customer mail.
 *
 *  - Queued (ShouldQueue) and afterCommit: a mail queued inside a transaction leaves only
 *    once the transaction commits, never for a row that was rolled back.
 *  - Locale fixed at construction (ar/en, else the app default), so a worker renders the
 *    language the recipient asked for, not the worker's.
 *  - From: config('mail.from.address') with the platform name (PlatformBranding) as the
 *    display name. Reply-To: the global config('mail.reply_to'), applied by the mailer.
 *  - Subject from a translation key; body inside the shared `mail.layouts.platform` layout.
 *  - Central context: rendering reads only central data (PlatformBranding pins the central
 *    connection). If a worker initialized a tenant for the job (queued from a tenant
 *    request), send() ends tenancy around the delivery and restores it afterwards.
 *  - failed(): after the last attempt, logs the class + recipient DOMAINS only (never the
 *    address or body) and alerts the platform Telegram channel. Never throws.
 */
abstract class PlatformMailable extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @var list<string> */
    public const SUPPORTED_LOCALES = ['ar', 'en'];

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(?string $locale = null)
    {
        $this->locale(self::resolveLocale($locale));
        $this->afterCommit();
    }

    /** Translation key of the subject (`:app` is always available as the platform name). */
    abstract protected function subjectKey(): string;

    /** Blade view of the HTML body; it should `@extends('mail.layouts.platform')`. */
    abstract protected function htmlView(): string;

    /** Optional plain-text alternative (improves deliverability). */
    protected function textView(): ?string
    {
        return null;
    }

    /**
     * Extra subject placeholders.
     *
     * @return array<string, string>
     */
    protected function subjectReplace(): array
    {
        return [];
    }

    /**
     * Extra view data for the concrete message.
     *
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [];
    }

    public function envelope(): Envelope
    {
        $branding = $this->branding();

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $branding->name),
            subject: (string) __($this->subjectKey(), $this->subjectReplace() + ['app' => $branding->name], $this->mailLocale()),
        );
    }

    public function content(): Content
    {
        $locale = $this->mailLocale();
        $branding = $this->branding();

        return new Content(
            view: $this->htmlView(),
            text: $this->textView(),
            with: [
                'mailLocale' => $locale,
                'mailDir' => $locale === 'ar' ? 'rtl' : 'ltr',
                'mailAlign' => $locale === 'ar' ? 'right' : 'left',
                'brand' => [
                    'name' => $branding->name,
                    'legal_name' => $branding->legalName !== '' ? $branding->legalName : $branding->name,
                    'logo_url' => BrandingResource::assetUrl($branding->logoLight),
                    'primary_color' => $branding->primaryColor,
                    'support_phone' => trim($branding->supportPhone),
                    'support_email' => trim($branding->supportEmail),
                    'website_url' => trim($branding->websiteUrl),
                ],
                'year' => now()->format('Y'),
            ] + $this->viewData(),
        );
    }

    /** @return list<int> seconds between attempts */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Deliver in central context. Tenancy is switched only when no transaction is open on the
     * current connection (ending tenancy purges the tenant connection); a queue worker never
     * has one open at this point.
     *
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer): ?SentMessage
    {
        $tenancy = tenancy();

        if (! $tenancy->initialized || DB::transactionLevel() > 0) {
            return parent::send($mailer);
        }

        $tenant = $tenancy->tenant;
        $tenancy->end();

        try {
            return parent::send($mailer);
        } finally {
            if ($tenant !== null) {
                $tenancy->initialize($tenant);
            }
        }
    }

    /** Called by SendQueuedMailable once every attempt is exhausted. */
    public function failed(Throwable $e): void
    {
        $domains = $this->recipientDomains();

        try {
            Log::error('Platform mail failed permanently.', [
                'mailable' => static::class,
                'recipient_domains' => $domains,
                'exception' => $e::class,
            ]);

            Notification::route(PlatformTelegramChannel::class, 'platform')
                ->notifyNow(new PlatformMailFailedNotification(static::class, $domains, $e::class));
        } catch (Throwable $alertError) {
            // A failing alert must never mask the original failure or crash the worker.
            Log::warning('Platform mail failure alert could not be sent.', ['exception' => $alertError::class]);
        }
    }

    /**
     * Lower-cased, unique domains of the To recipients: enough to diagnose a provider
     * problem without storing who the mail was for.
     *
     * @return list<string>
     */
    public function recipientDomains(): array
    {
        $domains = [];

        foreach ($this->to as $recipient) {
            $address = (string) ($recipient['address'] ?? '');
            $at = strrpos($address, '@');
            $domains[] = $at === false ? 'unknown' : strtolower(substr($address, $at + 1));
        }

        return array_values(array_unique($domains));
    }

    public static function resolveLocale(?string $locale): string
    {
        foreach ([$locale, app()->getLocale(), config('app.locale')] as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::SUPPORTED_LOCALES, true)) {
                return $candidate;
            }
        }

        return self::SUPPORTED_LOCALES[0];
    }

    protected function branding(): PlatformBrandingDTO
    {
        return app(PlatformBranding::class)->get();
    }

    private function mailLocale(): string
    {
        return self::resolveLocale($this->locale);
    }
}
