<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DailyJournalPrintController;
use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\POSController;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Support\ReportStoreFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes (Isolated Database Context)
|--------------------------------------------------------------------------
| Every route in this group runs within the isolated database and storage
| context of the identified tenant.
|
| SEC-1 (M7, W2 batch 6): this file only serves the SPA shell pages and the Blade print
| pages. Every business read/write lives in /api/v1 (Sanctum Bearer token, store.active,
| policies). Removed here: the legacy session POST /login and /tenant/logout, the stancl
| /impersonate/{token} entry (+ /impersonate/leave), every POST/PUT/DELETE duplicate of the
| API, the session store switches and /theme-toggle, and the routes whose controller
| methods do not exist (invoices/{id}/edit, purchases/create, returns/create).
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // 1. SPA login page (the SPA signs in through POST /api/v1/auth/login). Named `login`:
    //    bootstrap/app.php redirects guests here.
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    });

    // 🌐 Pure Vue 3 SPA Host Route (Dual-Engine Mode)
    Route::get('/spa/{any?}', function () {
        return view('spa');
    })->where('any', '.*')->name('tenant.spa');

    // NOTE: `/invoices/{id}/print` is intentionally NOT defined here. It falls
    // through to the SPA catch-all, whose InvoicePrintView loads the invoice via
    // the authenticated, permission-checked API before calling window.print().

    // 2. Protected SPA shell pages and Blade print pages (read-only)
    Route::middleware('auth')->group(function () {
        // Dashboard (Vue 3 SPA). Named `dashboard`: bootstrap/app.php redirects users here.
        Route::get('/', fn () => view('app'))->name('dashboard');

        // POS (Vue 3 Fast Cashier Engine): SPA shell only.
        Route::get('/pos', [POSController::class, 'index'])->name('pos.index')->middleware('can:pos.access');
        Route::get('/invoices/create', [POSController::class, 'index'])->name('invoices.create')->middleware('can:pos.access');

        // Blade invoice print pages (thermal receipt / A4) — permission + policy + store access enforced
        Route::get('/invoices/{id}/print/thermal', [InvoicePrintController::class, 'thermal'])->whereNumber('id')->name('invoices.print.thermal')->middleware('can:invoices.view');
        Route::get('/invoices/{id}/print/a4', [InvoicePrintController::class, 'a4'])->whereNumber('id')->name('invoices.print.a4')->middleware('can:invoices.view');

        // Daily Journal A4 Print Route
        // BUG-01: data built by GetDailyJournalPrintDataAction (cash vouchers of the store only).
        Route::get('/daily-journal/print', DailyJournalPrintController::class)->name('daily.journal.print')->middleware(['can:daily_journal.view', 'store.access']);

        // W2 3F: moved from routes/web.php, which never initialised tenancy (500 on tenant hosts).
        // The SPA catch-all in routes/web.php excludes these two print paths so they are reachable.
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
        })->name('items.movements.print')->middleware(['can:items.view', 'store.access']);

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
                'sales' => __('reports.print_sheet.titles.sales'),
                'items' => __('reports.print_sheet.titles.items'),
                'stores' => __('reports.print_sheet.titles.stores'),
                'customers' => __('reports.print_sheet.titles.customers'),
                'expenses' => __('reports.print_sheet.titles.expenses'),
                'inventory' => __('reports.print_sheet.titles.inventory'),
                'treasury' => __('reports.print_sheet.titles.treasury'),
            ];
            $reportTitle = $titles[$tab] ?? __('reports.print_sheet.titles.general');

            $kpis = [
                ['label' => __('reports.print_sheet.period'), 'value' => __('reports.print_sheet.period_range', ['from' => $fromDate ?: __('reports.print_sheet.period_start'), 'to' => $toDate ?: now()->toDateString()])],
                ['label' => __('reports.print_sheet.scope'), 'value' => $storeName],
            ];

            $tableHeaders = [
                ['title' => __('reports.print_sheet.col_description'), 'align' => 'text-right'],
                ['title' => __('common.date'), 'align' => 'text-center'],
                ['title' => __('reports.print_sheet.col_value'), 'align' => 'text-center'],
                ['title' => __('common.status'), 'align' => 'text-center'],
            ];
            $tableRows = [];

            if ($tab === 'treasury') {
                $tableHeaders = [
                    ['title' => __('reports.print_sheet.col_account'), 'align' => 'text-right'],
                    ['title' => __('treasury.opening_balance_short'), 'align' => 'text-center'],
                    ['title' => __('reports.print_sheet.col_total_in'), 'align' => 'text-center'],
                    ['title' => __('reports.print_sheet.col_total_out'), 'align' => 'text-center'],
                    ['title' => __('reports.print_sheet.col_current_balance'), 'align' => 'text-center'],
                ];
                $tableRows = [
                    [
                        ['value' => __('treasury.cash_drawer'), 'class' => 'font-bold'],
                        ['value' => '0.000', 'class' => 'font-mono text-center'],
                        ['value' => '0.000', 'class' => 'font-mono text-center text-emerald-600'],
                        ['value' => '0.000', 'class' => 'font-mono text-center text-rose-600'],
                        ['value' => '0.000', 'class' => 'font-mono text-center font-bold'],
                    ],
                    [
                        ['value' => __('treasury.instapay'), 'class' => 'font-bold'],
                        ['value' => '0.000', 'class' => 'font-mono text-center'],
                        ['value' => '0.000', 'class' => 'font-mono text-center text-emerald-600'],
                        ['value' => '0.000', 'class' => 'font-mono text-center text-rose-600'],
                        ['value' => '0.000', 'class' => 'font-mono text-center font-bold'],
                    ],
                    [
                        ['value' => __('treasury.e_wallet'), 'class' => 'font-bold'],
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
        })->name('reports.print')->middleware(['can:reports.view', 'store.access']);

        // Activity log CSV export (same URI as routes/web.php; this tenant-initialised definition
        // replaces that one on tenant hosts).
        Route::get('/activity-logs/export-csv', [ActivityLogController::class, 'exportCsv'])->name('tenant.activity-logs.export.csv')->middleware('can:logs.view');
    });
});
