<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Providers\AppServiceProvider;
use App\Services\Branding\PlatformBranding;
use Illuminate\Support\Facades\Cache;
use Tests\TenantTestCase;

/**
 * BRND-1: Laravel itself (mail, notifications, Pulse, Telescope) reads
 * config('app.name') and config('mail.from.name'). Both must equal the platform
 * name from PlatformBranding: at boot, and again whenever tenancy is initialized
 * or ended (so a long-lived worker picks up a rename on its next tenant job).
 */
final class AppNameOverrideTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['branding.name' => 'Neutral Platform']);
    }

    public function test_boot_sets_app_name_and_mail_from_name(): void
    {
        config(['app.name' => 'Laravel', 'mail.from.name' => 'Laravel']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('Neutral Platform', config('app.name'));
        $this->assertSame('Neutral Platform', config('mail.from.name'));
    }

    public function test_stored_platform_name_wins_over_config(): void
    {
        $this->app->make(PlatformBranding::class)->update(['name' => 'Stored Platform']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('Stored Platform', config('app.name'));
        $this->assertSame('Stored Platform', config('mail.from.name'));
    }

    public function test_tenancy_initialized_reapplies_a_rename(): void
    {
        $tenant = $this->createTenant();
        $this->app->make(PlatformBranding::class)->applyToConfig();
        $this->assertSame('Neutral Platform', config('app.name'));

        $this->app->make(PlatformBranding::class)->update(['name' => 'Renamed Platform']);
        config(['app.name' => 'stale', 'mail.from.name' => 'stale']);

        $this->inTenant($tenant, function (): void {
            $this->assertSame('Renamed Platform', config('app.name'));
            $this->assertSame('Renamed Platform', config('mail.from.name'));
        });

        config(['app.name' => 'stale']);
        $this->endTenancy();
        $this->inTenant($tenant, fn () => null); // initialize + end again
        $this->assertSame('Renamed Platform', config('app.name'), 'TenancyEnded re-applies too.');
    }
}
