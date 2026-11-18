<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\PlatformMailable;
use App\Mail\TestPlatformMail;
use App\Services\Branding\PlatformBranding;
use Illuminate\Support\Facades\Mail;
use Tests\TenantTestCase;

/**
 * SIGN-10 / PLAN-10 review: PlatformMailable::send() delivers in central context and, when it was
 * called from inside an initialized tenant (a worker running a job queued from a tenant request),
 * restores THE SAME tenant afterwards.
 */
final class PlatformMailTenancyTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'array',
            'mail.from.address' => 'no-reply@brandx.test',
            'branding.name' => 'BrandX Platform',
        ]);
        PlatformBranding::forget();
    }

    public function test_sending_from_a_tenant_renders_centrally_and_restores_the_same_tenant(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();

        RecordingTenancyPlatformMail::$seen = [];
        tenancy()->initialize($tenant);
        $this->assertSame((string) $tenant->getTenantKey(), (string) tenant()?->getTenantKey());

        $mail = new RecordingTenancyPlatformMail('en');
        Mail::to('owner@example.com')->sendNow($mail);

        $this->assertSame([false], RecordingTenancyPlatformMail::$seen, 'The body is rendered with tenancy ended.');
        $this->assertTrue(tenancy()->initialized, 'Tenancy is restored after the delivery.');
        $this->assertSame((string) $tenant->getTenantKey(), (string) tenant()?->getTenantKey());
        $this->assertNotSame((string) $other->getTenantKey(), (string) tenant()?->getTenantKey());

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
    }

    public function test_sending_in_central_context_stays_central(): void
    {
        $this->assertFalse(tenancy()->initialized);

        Mail::to('owner@example.com')->sendNow(new TestPlatformMail('ar'));

        $this->assertFalse(tenancy()->initialized);
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());
    }
}

/** Records whether tenancy is initialized while the platform mail is rendered. */
final class RecordingTenancyPlatformMail extends PlatformMailable
{
    /** @var list<bool> */
    public static array $seen = [];

    protected function subjectKey(): string
    {
        return 'mail.test.subject';
    }

    protected function htmlView(): string
    {
        return 'mail.platform.test';
    }

    protected function viewData(): array
    {
        self::$seen[] = tenancy()->initialized;

        return [];
    }
}
