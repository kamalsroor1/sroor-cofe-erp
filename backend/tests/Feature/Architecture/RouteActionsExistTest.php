<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * SETG-7 (tenant-settings-catalog §5 #15): routes/tenant.php registered six settings
 * routes pointing at SettingController methods that do not exist (500 on hit). Guard
 * against any route — in any routes file — pointing at a missing controller method.
 */
final class RouteActionsExistTest extends TenantTestCase
{
    /**
     * Legacy debt outside SETG-7's scope: routes/tenant.php web routes pointing at
     * methods that were never ported to the API controllers. Recorded so no NEW broken
     * route can slip in; remove entries as routes/tenant.php is cleaned up (IDEN-2.7).
     *
     * @var list<string>
     */
    private const KNOWN_BROKEN_LEGACY = [
        'App\Http\Controllers\Api\InvoiceController@edit',
        'App\Http\Controllers\Api\InvoiceController@update',
        'App\Http\Controllers\Api\InvoiceController@destroy',
        'App\Http\Controllers\Api\InvoiceController@restore',
        'App\Http\Controllers\Api\PurchaseController@create',
        'App\Http\Controllers\Api\ReturnController@create',
        'App\Http\Controllers\Api\DailyJournalController@openShift',
        'App\Http\Controllers\Api\DailyJournalController@closeShift',
        'App\Http\Controllers\Api\DailyJournalController@storeExpense',
    ];

    public function test_every_controller_route_points_to_an_existing_method(): void
    {
        $missing = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue; // closures and invokable-less actions
            }

            [$class, $method] = explode('@', $action, 2);
            if (in_array($action, self::KNOWN_BROKEN_LEGACY, true)) {
                continue;
            }

            if (! class_exists($class) || ! method_exists($class, $method)) {
                $missing[] = implode('|', $route->methods()).' '.$route->uri().' -> '.$action;
            }
        }

        $this->assertSame([], $missing, "Routes point to missing controller methods:\n".implode("\n", $missing));
    }

    public function test_broken_legacy_settings_routes_are_gone(): void
    {
        foreach ([
            'settings.telegram.daily_summary',
            'settings.telegram.low_stock',
            'settings.telegram.overdue_shifts',
            'settings.telegram.backup',
            'settings.backup.download',
            'settings.clear_cache',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Route {$name} must not be registered");
        }
    }
}
