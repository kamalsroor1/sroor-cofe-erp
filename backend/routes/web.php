<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Auth\TelescopeAccessController;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Support\ReportStoreFilter;
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

// 🖨️ Item Movements Audit Ledger Print
Route::get('/items/{id}/movements/print', function ($id, Request $request) {
    $item = Item::withTrashed()->findOrFail($id);
    $storeId = ReportStoreFilter::resolve($request, $request->user()); // access-checked on the value used
    $fromDate = $request->query('from');
    $toDate = $request->query('to');
    $filterType = $request->query('type');

    $inTypes = ['purchase_in', 'stock_deposit_in', 'stock_adjustment_in', 'cancellation_in', 'transfer_in', 'sales_return_in', 'purchase_restore_in'];
    $outTypes = ['sales_out', 'waste_out', 'stock_adjustment_out', 'transfer_out', 'purchase_cancel_out', 'purchase_return_out'];
    $adjTypes = ['stock_adjustment_in', 'stock_adjustment_out', 'stock_deposit_in'];

    $storeName = trans('common.all_stores');
    if ($storeId) {
        $st = Store::find($storeId);
        if ($st) {
            $storeName = $st->name;
        }
    }

    $baseQuery = StockMovement::with(['user', 'store'])
        ->where('item_id', $item->id)
        ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
        ->when($fromDate, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
        ->when($toDate, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
        ->when($filterType === 'in', fn ($q) => $q->whereIn('movement_type', $inTypes))
        ->when($filterType === 'out', fn ($q) => $q->whereIn('movement_type', $outTypes))
        ->when($filterType === 'adjustments', fn ($q) => $q->whereIn('movement_type', $adjTypes));

    $allMovements = (clone $baseQuery)->get();
    $totalIn = '0.000';
    $totalOut = '0.000';
    foreach ($allMovements as $mov) {
        if (in_array($mov->movement_type, $inTypes)) {
            $totalIn = bcadd($totalIn, (string) $mov->quantity, 3);
        } elseif (in_array($mov->movement_type, $outTypes)) {
            $totalOut = bcadd($totalOut, (string) $mov->quantity, 3);
        }
    }
    $netMovement = bcsub($totalIn, $totalOut, 3);
    $currentScopeStock = $storeId
        ? (string) (StoreStock::where('store_id', $storeId)->where('item_id', $item->id)->value('quantity') ?: '0.000')
        : (string) $item->current_stock;

    $movements = $baseQuery->oldest('created_at')->get();

    return view('layouts.print-item-movements-a4', compact(
        'item', 'storeName', 'fromDate', 'toDate', 'movements',
        'totalIn', 'totalOut', 'netMovement', 'currentScopeStock'
    ));
})->name('items.movements.print')->middleware(['auth', 'can:items.view', 'store.access']);

// 🖨️ General Reports A4 Print
Route::get('/reports/print', function (Request $request) {
    $tab = $request->query('tab', 'sales');
    $storeId = ReportStoreFilter::resolve($request, $request->user()); // access-checked on the value used
    $fromDate = $request->query('from');
    $toDate = $request->query('to');

    $storeName = trans('common.all_stores');
    if ($storeId) {
        $st = Store::find($storeId);
        if ($st) {
            $storeName = $st->name;
        }
    }

    $titles = [
        'sales' => 'تقرير المبيعات والفواتير',
        'items' => 'تقرير حركة وأرباح الأصناف',
        'stores' => 'تقرير مقارنة أداء الفروع والمخازن',
        'customers' => 'تقرير مبيعات ومديونيات العملاء',
        'expenses' => 'تقرير المصروفات والنفقات التشغيلية',
        'inventory' => 'تقرير تقييم وجرد المخزون',
        'treasury' => 'تقرير الخزائن والسيولة وسجل التحويلات المالية',
    ];
    $reportTitle = $titles[$tab] ?? 'تقرير عام للنظام';

    $kpis = [
        ['label' => 'الفترة الزمنية', 'value' => ($fromDate ?: 'البداية').' إلى '.($toDate ?: now()->toDateString())],
        ['label' => 'النطاق', 'value' => $storeName],
    ];

    $tableHeaders = [
        ['title' => 'البيان / الوصف', 'align' => 'text-right'],
        ['title' => 'التاريخ', 'align' => 'text-center'],
        ['title' => 'القيمة', 'align' => 'text-center'],
        ['title' => 'الحالة', 'align' => 'text-center'],
    ];
    $tableRows = [];

    if ($tab === 'treasury') {
        $tableHeaders = [
            ['title' => 'الخزينة / الحساب', 'align' => 'text-right'],
            ['title' => 'رصيد البداية', 'align' => 'text-center'],
            ['title' => 'إجمالي الوارد', 'align' => 'text-center'],
            ['title' => 'إجمالي المنصرف', 'align' => 'text-center'],
            ['title' => 'الرصيد الحالي', 'align' => 'text-center'],
        ];
        $tableRows = [
            [
                ['value' => 'درج النقدية (كاش)', 'class' => 'font-bold'],
                ['value' => '0.000', 'class' => 'font-mono text-center'],
                ['value' => '0.000', 'class' => 'font-mono text-center text-emerald-600'],
                ['value' => '0.000', 'class' => 'font-mono text-center text-rose-600'],
                ['value' => '0.000', 'class' => 'font-mono text-center font-bold'],
            ],
            [
                ['value' => 'إنستاباي (InstaPay)', 'class' => 'font-bold'],
                ['value' => '0.000', 'class' => 'font-mono text-center'],
                ['value' => '0.000', 'class' => 'font-mono text-center text-emerald-600'],
                ['value' => '0.000', 'class' => 'font-mono text-center text-rose-600'],
                ['value' => '0.000', 'class' => 'font-mono text-center font-bold'],
            ],
            [
                ['value' => 'المحافظ الذكية', 'class' => 'font-bold'],
                ['value' => '0.000', 'class' => 'font-mono text-center'],
                ['value' => '0.000', 'class' => 'font-mono text-center text-emerald-600'],
                ['value' => '0.000', 'class' => 'font-mono text-center text-rose-600'],
                ['value' => '0.000', 'class' => 'font-mono text-center font-bold'],
            ],
        ];
    }

    return view('layouts.print-report-a4', compact(
        'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows'
    ));
})->name('reports.print')->middleware(['auth', 'can:reports.view', 'store.access']);

Route::get('/stock-transfers', function () {
    return view('app');
})->name('stock-transfers');

// Session store switch: authenticated, and only to an active store the user may access.
$switchSessionStore = function (Request $request) {
    $raw = $request->input('store_id');
    $storeId = (is_int($raw) || (is_string($raw) && ctype_digit($raw))) ? (int) $raw : 0;
    $user = $request->user();

    $allowed = $storeId > 0
        && Store::whereKey($storeId)->where('is_active', true)->exists()
        && ($user->hasRole('admin')
            || (int) $user->default_store_id === $storeId
            || $user->stores()->where('stores.id', $storeId)->exists());

    abort_unless($allowed, 403, __('common.store_access_denied'));

    session(['current_store_id' => $storeId]);

    return response()->json(['success' => true, 'store_id' => $storeId]);
};

Route::post('/store/switch', $switchSessionStore)->name('store.switch')->middleware('auth');

Route::post('/stores/switch', $switchSessionStore)->middleware('auth');

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
    $platformName = Setting::get('platform_name') ?: Setting::get('app_name') ?: config('app.name', 'منظومة ERP');
    $manifest = [
        'id' => 'cloud-erp-pos-app',
        'name' => $platformName.' | '.trans('dashboard.app_badge_sub'),
        'short_name' => $platformName,
        'description' => Setting::get('platform_subtitle', 'منظومة سحابية متكاملة لإدارة المبيعات والمخزون والفروع'),
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
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '(?!api(?:/|$)).*')->name('app'); // never serve the SPA shell for unknown /api/* paths (must 404)
