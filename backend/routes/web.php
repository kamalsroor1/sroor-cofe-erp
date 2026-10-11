<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Auth\TelescopeAccessController;
use App\Http\Middleware\EnsureCentralContext;
use App\Services\Branding\PlatformBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Pure Vue 3 SPA + Printing & Utility Engine
|--------------------------------------------------------------------------
|
| All web routes are served by the high-performance Vue 3 Single Page
| Application (SPA). Backend API logic is isolated in routes/api.php.
|
*/

// Telescope web bridge for platform super admins. Only a short-lived, single-use signed URL
// issued by POST /api/v1/super-admin/telescope-link is accepted; bearer tokens in the query
// string are no longer honoured.
Route::get('/telescope-access', TelescopeAccessController::class)
    ->middleware('signed')
    ->name('telescope.access');

// 📄 Public Marketing Brochure & Pricing PDF Presentation
Route::get('/brochure', function () {
    return view('marketing-brochure');
})->name('marketing.brochure');

// NOTE: invoice thermal/A4 and daily-journal print routes live in routes/tenant.php
// behind auth + permission (+ store.access) middleware. Do not re-add them here.

// NOTE (W2 3F): the item-movements and reports A4 print pages and the session store switch
// (`/store/switch`) live in routes/tenant.php under domain tenancy (+ auth, permission and
// store.access). They are excluded from the SPA catch-all below so that route still wins.

// IDEN-1.11: one SPA shell, two contexts. On a platform-console host (central.admin_domains)
// every path serves the shell with <meta name="app-context" content="central"> (the tenant SPA
// is never served there); elsewhere the console paths (/super-admin*) are 404 once admin hosts
// are configured, and the tenant shell carries no app-context meta.
$spaShell = function (Request $request) {
    if (EnsureCentralContext::isAdminHost($request)) {
        return view('app', ['appContext' => 'central']);
    }

    abort_if(
        EnsureCentralContext::adminHosts() !== [] && $request->is('super-admin', 'super-admin/*'),
        404,
    );

    return view('app');
};

Route::get('/stock-transfers', $spaShell)->name('stock-transfers');

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->name('logout');

// NOTE: the unauthenticated customer/supplier/item CSV export routes were removed
// (P0 report row 7). Nothing in the SPA/desktop/mobile called them; re-expose
// exports only through /api/v1 behind auth:sanctum + permission + store scope.
Route::get('/activity-logs/export-csv', [ActivityLogController::class, 'exportCsv'])->name('activity-logs.export.csv')->middleware(['auth', 'can:logs.view']);

// 📱 PWA Manifest & Service Worker
Route::get('/manifest.json', function () {
    $baseUrl = url('/');
    // BRND-1: the platform (SaaS operator) brand lives in the CENTRAL platform_settings table.
    $branding = app(PlatformBranding::class)->get();
    $platformName = $branding->name;
    $manifest = [
        'id' => 'cloud-erp-pos-app',
        'name' => $platformName.' | '.trans('dashboard.app_badge_sub'),
        'short_name' => $platformName,
        'description' => $branding->subtitle,
        'start_url' => $baseUrl.'/',
        'scope' => $baseUrl.'/',
        'display' => 'standalone',
        'background_color' => '#020617',
        'theme_color' => '#0f172a',
        'orientation' => 'portrait-primary',
        'dir' => 'rtl',
        'lang' => 'ar',
        'prefer_related_applications' => false,
        'icons' => [
            [
                'src' => asset('logo.png'),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => asset('logo.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
        ],
    ];

    return response()->json($manifest, 200, [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
});

Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    if (! file_exists($path)) {
        return response('console.log("SW not found");', 404, ['Content-Type' => 'application/javascript']);
    }

    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache',
    ]);
});

// 🌐 Pure Vue 3 SPA Catch-All Entry Point
// Never serve the SPA shell for unknown /api/* paths (must 404), nor for the tenant print pages
// registered in routes/tenant.php (loaded after this file, so the catch-all would shadow them).
Route::get('/{any?}', $spaShell)
    ->where('any', '(?!api(?:/|$)|reports/print$|items/[^/]+/movements/print$).*')
    ->name('app');
