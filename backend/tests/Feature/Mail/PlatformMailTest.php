<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\PlatformMailable;
use App\Mail\TestPlatformMail;
use App\Services\Branding\PlatformBranding;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * SIGN-10: platform email foundation: queued base mailable, branded RTL/LTR layout,
 * the `mail:test` ops command and the permanent-failure alert.
 */
final class PlatformMailTest extends TestCase
{
    use RefreshDatabase;

    private const RECIPIENT = 'owner.private@Example.COM';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'branding.name' => 'BrandX Platform',
            'branding.legal_name' => 'BrandX Legal Co',
            'branding.primary_color' => '#123abc',
            'branding.support_phone' => '01000000777',
            'branding.support_email' => 'help@brandx.test',
            'branding.website_url' => 'https://brandx.test',
            'mail.from.address' => 'no-reply@brandx.test',
            'health.telegram.bot_token' => null,
            'health.telegram.chat_id' => null,
        ]);
        PlatformBranding::forget();
    }

    public function test_mail_test_command_queues_the_test_mail_without_printing_the_address(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => self::RECIPIENT])
            ->expectsOutputToContain('example.com')
            ->doesntExpectOutputToContain('owner.private')
            ->assertExitCode(0);

        Mail::assertQueued(TestPlatformMail::class, fn (TestPlatformMail $mail): bool => $mail->hasTo(self::RECIPIENT) && $mail->locale === 'ar');
        Mail::assertNothingSent();
    }

    public function test_mail_test_command_honours_the_locale_option(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => 'ops@example.org', '--locale' => 'en'])->assertExitCode(0);

        Mail::assertQueued(TestPlatformMail::class, fn (TestPlatformMail $mail): bool => $mail->locale === 'en');
    }

    public function test_mail_test_command_sends_immediately_with_now(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => 'ops@example.org', '--now' => true])->assertExitCode(0);

        Mail::assertSent(TestPlatformMail::class, fn (TestPlatformMail $mail): bool => $mail->hasTo('ops@example.org'));
        Mail::assertNothingQueued();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidEmailProvider(): array
    {
        return [
            'no at sign' => ['not-an-email'],
            'no domain' => ['owner@'],
            'spaces' => ['owner name@example.com'],
            'blank' => ['   '],
            'too long' => [str_repeat('a', 250).'@example.com'],
        ];
    }

    #[DataProvider('invalidEmailProvider')]
    public function test_mail_test_command_rejects_an_invalid_email(string $email): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => $email])->assertExitCode(1);

        Mail::assertNothingOutgoing();
    }

    public function test_mail_test_command_rejects_an_unsupported_locale(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => 'ops@example.org', '--locale' => 'fr'])->assertExitCode(1);

        Mail::assertNothingOutgoing();
    }

    public function test_platform_mail_is_queued_after_commit_with_retries(): void
    {
        $mail = new TestPlatformMail('ar');

        $this->assertInstanceOf(ShouldQueue::class, $mail);
        $this->assertInstanceOf(PlatformMailable::class, $mail);
        $this->assertTrue($mail->afterCommit);
        $this->assertSame(3, $mail->tries);
        $this->assertSame([60, 300], $mail->backoff());
    }

    public function test_unsupported_locale_falls_back_to_the_app_locale(): void
    {
        app()->setLocale('ar');

        $this->assertSame('ar', (new TestPlatformMail('fr'))->locale);
        $this->assertSame('en', (new TestPlatformMail('en'))->locale);
    }

    public function test_arabic_mail_renders_rtl_with_platform_branding(): void
    {
        $mail = new TestPlatformMail('ar');

        $mail->assertSeeInHtml('dir="rtl"', false);
        $mail->assertSeeInHtml('lang="ar"', false);
        $mail->assertSeeInHtml('BrandX Platform');
        $mail->assertSeeInHtml('#123abc', false);
        $mail->assertSeeInHtml('01000000777');
        $mail->assertSeeInHtml('help@brandx.test');
        $mail->assertSeeInHtml('BrandX Legal Co');
        $mail->assertSeeInHtml((string) __('mail.test.heading', [], 'ar'));
        $mail->assertSeeInHtml((string) __('mail.layout.support_title', [], 'ar'));
        $mail->assertSeeInHtml('/logo-light.png', false);
        $mail->assertDontSeeInHtml('dir="ltr" style="margin:0', false);
        $mail->assertSeeInText((string) __('mail.test.heading', [], 'ar'));
        $mail->assertHasSubject((string) __('mail.test.subject', ['app' => 'BrandX Platform'], 'ar'));
        $mail->assertFrom('no-reply@brandx.test', 'BrandX Platform');
    }

    public function test_english_mail_renders_ltr(): void
    {
        $mail = new TestPlatformMail('en');

        $mail->assertSeeInHtml('dir="ltr"', false);
        $mail->assertSeeInHtml('lang="en"', false);
        $mail->assertDontSeeInHtml('dir="rtl"', false);
        $mail->assertSeeInHtml('Email settings are working');
        $mail->assertSeeInHtml('BrandX Platform');
        $mail->assertHasSubject('Test message from BrandX Platform');
    }

    public function test_footer_skips_blank_support_contacts(): void
    {
        config(['branding.support_phone' => '', 'branding.support_email' => '', 'branding.website_url' => '']);
        PlatformBranding::forget();

        $mail = new TestPlatformMail('ar');

        $mail->assertDontSeeInHtml((string) __('mail.layout.phone_label', [], 'ar'));
        $mail->assertDontSeeInHtml('mailto:', false);
        $mail->assertSeeInHtml((string) __('mail.layout.support_title', [], 'ar'));
    }

    public function test_permanent_failure_logs_only_the_recipient_domain_and_alerts_telegram(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config(['health.telegram.bot_token' => 'test-token', 'health.telegram.chat_id' => '-100']);
        Log::spy();

        $mail = (new TestPlatformMail('ar'))->to(self::RECIPIENT);
        $mail->failed(new RuntimeException('550 rejected '.self::RECIPIENT));

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context): bool {
            $encoded = (string) json_encode($context);

            return $context['mailable'] === TestPlatformMail::class
                && $context['recipient_domains'] === ['example.com']
                && $context['exception'] === RuntimeException::class
                && ! str_contains(strtolower($encoded), 'owner.private')
                && ! str_contains($message, 'owner.private');
        });

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $text = (string) $request['text'];

            return str_ends_with($request->url(), '/sendMessage')
                && $request['chat_id'] === '-100'
                && str_contains($text, 'TestPlatformMail')
                && str_contains($text, 'example.com')
                && str_contains($text, 'RuntimeException')
                && ! str_contains(strtolower($text), 'owner.private')
                && ! str_contains($text, '550 rejected');
        });
    }

    public function test_permanent_failure_without_telegram_config_only_logs_and_never_throws(): void
    {
        Http::fake();
        Log::spy();

        $mail = (new TestPlatformMail('en'))->to(self::RECIPIENT);
        $mail->failed(new RuntimeException('boom'));

        Log::shouldHaveReceived('error')->once();
        Http::assertNothingSent();
    }

    public function test_a_broken_telegram_endpoint_does_not_throw_from_failed(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('down', 500)]);
        config(['health.telegram.bot_token' => 'test-token', 'health.telegram.chat_id' => '-100']);

        $mail = (new TestPlatformMail('en'))->to(self::RECIPIENT);
        $mail->failed(new RuntimeException('boom'));

        Http::assertSentCount(1);
    }
}
