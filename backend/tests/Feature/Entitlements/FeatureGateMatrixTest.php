<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Jobs\SendDailySummaryReportJob;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Settings\SettingSecrets;
use App\Services\TelegramService;
use Database\Seeders\Catalog\FeatureCatalog;
use Database\Seeders\Catalog\PlanCatalog;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PLAN-AUDIT (2026-11-19): every plan feature of FeatureCatalog is either
 *  - gated on its API routes by `feature:<key>` (a plan without it gets 403
 *    `subscription.feature_unavailable` on EVERY such route, checked route by route here),
 *  - or checked in code (CODE_GATED),
 *  - or knowingly enforced nowhere yet (UNENFORCED), and then it must not be sold: a non-core
 *    unenforced feature is in no catalog plan, a core one is on in every plan (only a super-admin
 *    revoke could take it away, which then has no effect).
 * A new feature, or a feature that gains enforcement, fails this test until the lists are updated.
 */
#[Group('entitlements')]
final class FeatureGateMatrixTest extends TenantTestCase
{
    /** Features enforced in code instead of (or on top of) route middleware: key => where. */
    private const CODE_GATED = [
        'reports.advanced' => 'App\Support\ProfitVisibility (profit / cost fields hidden)',
        'telegram.notifications' => 'App\Services\TelegramService::isShopEnabled() (every shop-only alert)',
    ];

    /** Catalog features with no enforcement point at all (audit 2026-11-19). */
    private const UNENFORCED = [
        // core: on in every plan; no route or screen of its own to gate (no invoice edit route, WhatsApp is a client link).
        'invoices.edit', 'whatsapp.share',
        // non-core: not built yet, sold in no plan.
        'quotations.manage', 'pos.offline', 'api.access', 'custom.domain',
    ];

    public function test_every_feature_gated_route_refuses_a_plan_without_that_feature(): void
    {
        // The throttle middleware is sorted ahead of `feature:` (middleware priority), so the
        // refused export calls still count against the exports limiter: more than its 6/min
        // report exports share one tenant + user here. Rate limiting is not what this test checks.
        config(['rate_limits.exports.per_minute' => 1000]);

        $byFeature = $this->gatedRoutes();
        $this->assertNotEmpty($byFeature);
        $checked = 0;

        foreach ($byFeature as $feature => $routes) {
            $tenant = $this->tenantWithout($feature);
            $headers = $this->tenantHeaders($tenant);

            foreach ($routes as $route) {
                $method = collect($route->methods())->first(static fn (string $m): bool => $m !== 'HEAD');
                $uri = '/'.$this->fillParameters($route);

                $response = $this->json($method, $uri, [], $headers);

                $this->assertSame(403, $response->status(), "{$method} {$uri} without {$feature}: ".$response->getContent());
                $this->assertSame('subscription.feature_unavailable', $response->json('error_code'), "{$method} {$uri}");
                $this->assertSame($feature, $response->json('details.feature'), "{$method} {$uri}");
                $checked++;
            }
        }

        $this->assertGreaterThan(50, $checked);
    }

    public function test_every_catalog_feature_is_gated_or_knowingly_unenforced_and_never_sold_empty(): void
    {
        $routeGated = array_keys($this->gatedRoutes(apiOnly: false));
        $catalog = FeatureCatalog::keys();
        $unenforced = array_values(array_diff($catalog, $routeGated, array_keys(self::CODE_GATED)));

        sort($unenforced);
        $expected = self::UNENFORCED;
        sort($expected);
        $this->assertSame($expected, $unenforced, 'The set of catalog features with no enforcement changed: update the audit lists.');

        $core = FeatureCatalog::coreKeys();
        foreach (self::UNENFORCED as $key) {
            foreach (PlanCatalog::SLUGS as $slug) {
                $this->assertSame(
                    in_array($key, $core, true),
                    PlanCatalog::features($slug)[$key],
                    "{$slug} / {$key}: an unenforced feature must be on everywhere (core) or sold nowhere.",
                );
            }
        }
    }

    public function test_the_tenant_print_pages_carry_their_plan_feature(): void
    {
        $expected = [
            'invoices.print.thermal' => 'feature:printing.thermal',
            'invoices.print.a4' => 'feature:printing.a4',
            'items.movements.print' => 'feature:items.movements',
            'tenant.activity-logs.export.csv' => 'feature:audit.logs',
        ];

        foreach ($expected as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route [{$name}] is missing.");
            $this->assertContains($middleware, $route->gatherMiddleware(), "Route [{$name}] must carry {$middleware}.");
        }
    }

    // --------------------------------------------------------- reports.export

    public function test_the_invoice_export_needs_the_report_export_feature(): void
    {
        $free = $this->tenantWithout('reports.export');
        $paid = $this->tenantOn($this->plan());

        $this->getJson('/api/v1/invoices/export', $this->tenantHeaders($free))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.feature_unavailable')
            ->assertJsonPath('details.feature', 'reports.export');

        $this->get('/api/v1/invoices/export', $this->tenantHeaders($paid))->assertOk();
    }

    public function test_the_catalog_free_plan_has_no_report_export(): void
    {
        $this->assertFalse(PlanCatalog::features('free')['reports.export']);
        foreach (['basic', 'pro', 'enterprise'] as $paid) {
            $this->assertTrue(PlanCatalog::features($paid)['reports.export']);
        }
    }

    // -------------------------------------------------- telegram.notifications

    public function test_a_shop_without_telegram_notifications_gets_no_scheduled_alert(): void
    {
        $without = $this->tenantWithout('telegram.notifications');
        $with = $this->tenantOn($this->plan());
        $this->configureTelegram($without);
        $this->configureTelegram($with);
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$without->id]])->assertSuccessful();
        Bus::assertNotDispatched(SendDailySummaryReportJob::class);

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$with->id]])->assertSuccessful();
        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 1);
    }

    public function test_a_shop_only_alert_is_never_sent_without_the_feature(): void
    {
        $without = $this->tenantWithout('telegram.notifications');
        $with = $this->tenantOn($this->plan());
        $this->configureTelegram($without);
        $this->configureTelegram($with);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->assertFalse($this->inTenant($without, fn (): bool => app(TelegramService::class)->forShopOnly()->hasShopCredentials()));
        $this->inTenant($without, fn () => app(TelegramService::class)->forShopOnly()->sendMessage('alert'));
        Http::assertNothingSent();

        $this->assertTrue($this->inTenant($with, fn (): bool => app(TelegramService::class)->forShopOnly()->hasShopCredentials()));
        $this->inTenant($with, fn () => app(TelegramService::class)->forShopOnly()->sendMessage('alert'));
        Http::assertSentCount(1);
    }

    public function test_the_telegram_test_button_needs_the_feature(): void
    {
        $tenant = $this->tenantWithout('telegram.notifications');

        $this->postJson('/api/v1/settings/telegram/test', [], $this->tenantHeaders($tenant))
            ->assertStatus(403)
            ->assertJsonPath('details.feature', 'telegram.notifications');
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Routes carrying `feature:<key>` (tenant API only by default; with $apiOnly false also the tenant
     * web print / export pages), grouped by key (a route with two keys is listed under both).
     *
     * @return array<string, list<LaravelRoute>>
     */
    private function gatedRoutes(bool $apiOnly = true): array
    {
        $byFeature = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($apiOnly && ! Str::startsWith($route->uri(), 'api/v1/')) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && Str::startsWith($middleware, 'feature:')) {
                    foreach (explode(',', Str::after($middleware, 'feature:')) as $key) {
                        $byFeature[$key][] = $route;
                    }
                }
            }
        }

        ksort($byFeature);

        return $byFeature;
    }

    private function fillParameters(LaravelRoute $route): string
    {
        return (string) preg_replace_callback('/\{(\w+)\??\}/', function (array $match) use ($route): string {
            $pattern = $route->wheres[$match[1]] ?? null;

            if ($pattern === null || preg_match('/^(?:'.$pattern.')$/', '1') === 1) {
                return '1';
            }

            // A constrained word parameter (e.g. return-sources/{type}): its first alternative.
            return (string) Str::of($pattern)->before('|')->replaceMatches('/[^\w-]/', '');
        }, $route->uri());
    }

    private function tenantWithout(string $feature): Tenant
    {
        $features = array_fill_keys(FeatureCatalog::keys(), true);
        $features[$feature] = false;

        return $this->tenantOn($this->plan(['features' => $features]));
    }

    private function tenantOn(Plan $plan): Tenant
    {
        return $this->createTenant(['plan_id' => $plan->id, 'status' => 'active']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): Plan
    {
        $slug = 'gate-'.Str::lower(Str::random(10));

        return Plan::query()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '499.000',
            'price_yearly' => '4990.000',
            'features' => array_fill_keys(FeatureCatalog::keys(), true),
            'max_users' => null,
            'max_stores' => null,
            'max_warehouses' => null,
            'max_vans' => null,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => null,
            'max_customers' => null,
            'max_suppliers' => null,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    private function configureTelegram(Tenant $tenant): void
    {
        $this->inTenant($tenant, function (): void {
            Setting::set('telegram_bot_token', SettingSecrets::encrypt('shop-token-audit'));
            Setting::set('telegram_chat_id', '-100777');
        });
    }
}
