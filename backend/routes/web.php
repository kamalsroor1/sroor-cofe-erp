<?php

use Illuminate\Support\Facades\Route;
use App\Models\Invoice;

/*
|--------------------------------------------------------------------------
| Web Routes - Pure Vue 3 SPA + Printing & Utility Engine
|--------------------------------------------------------------------------
|
| All web routes are served by the high-performance Vue 3 Single Page
| Application (SPA). Backend API logic is isolated in routes/api.php.
|
*/

// 📄 Public Marketing Brochure & Pricing PDF Presentation
Route::get('/brochure', function () {
    return view('marketing-brochure');
})->name('marketing.brochure');

// 🖨️ Thermal Receipt Printing Route (Cashier Fast Print)
Route::get('/invoices/{id}/print/thermal', function ($id) {
    $invoice = Invoice::with(['customer', 'items.item', 'additionalExpenses'])->findOrFail($id);
    return view('layouts.print-thermal', compact('invoice'));
})->name('invoices.print.thermal');

// 🖨️ Standard A4 Tax & Commercial Invoice Print
Route::get('/invoices/{id}/print/a4', function ($id) {
    $invoice = Invoice::with(['customer', 'items.item', 'additionalExpenses'])->findOrFail($id);
    return view('layouts.print-a4', compact('invoice'));
})->name('invoices.print.a4');

// 🖨️ Daily Journal A4 Detailed Accounting Print
Route::get('/daily-journal/print', function (\Illuminate\Http\Request $request) {
    $date = $request->query('date', now()->toDateString());
    $storeId = $request->query('store_id', 'all');
    $storeFilter = ($storeId !== 'all' && is_numeric($storeId)) ? (int)$storeId : null;

    $storeName = 'كافة الفروع والعربيات';
    if ($storeFilter) {
        $st = \App\Models\Store::find($storeFilter);
        if ($st) $storeName = $st->name;
    }

    $invoices = Invoice::with(['customer', 'store'])
        ->whereDate('invoice_date', $date)
        ->where('status', 'confirmed')
        ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter))
        ->latest('id')
        ->get();

    $invoicesCount = $invoices->count();
    $totalSales = (string)($invoices->sum('total_amount') ?: '0.000');
    $cashSales = (string)($invoices->where('payment_type', 'cash')->sum('total_amount') ?: '0.000');
    $creditSales = (string)($invoices->where('payment_type', 'credit')->sum('total_amount') ?: '0.000');
    $partialSales = (string)($invoices->where('payment_type', 'partial')->sum('total_amount') ?: '0.000');
    $partialPaid = (string)($invoices->where('payment_type', 'partial')->sum('paid_amount') ?: '0.000');

    $customerPayments = (string)(\App\Models\Payment::whereDate('payment_date', $date)->whereNotNull('customer_id')->sum('amount') ?: '0.000');
    $totalCashCollected = bcadd((string)bcadd($cashSales, $partialPaid, 3), (string)$customerPayments, 3);

    $expenses = \App\Models\Expense::with('store')
        ->whereDate('expense_date', $date)
        ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter))
        ->get();
    $totalExpenses = (string)($expenses->sum('amount') ?: '0.000');

    $supplierPayments = \App\Models\Payment::with('supplier')
        ->whereDate('payment_date', $date)
        ->whereNotNull('supplier_id')
        ->get();
    $totalSupplierPaid = (string)($supplierPayments->sum('amount') ?: '0.000');

    $totalOutflows = bcadd($totalExpenses, $totalSupplierPaid, 3);
    $netCashToday = bcsub((string)$totalCashCollected, $totalOutflows, 3);

    $shiftsOnDate = \App\Models\CashShift::with(['user', 'store'])
        ->whereDate('opened_at', $date)
        ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter))
        ->latest('id')
        ->get();

    $openingCashBalance = $shiftsOnDate->count() > 0 ? (string)$shiftsOnDate->first()->opening_cash_balance : '0.000';
    $expectedCashInDrawer = bcadd($openingCashBalance, $netCashToday, 3);

    return view('layouts.print-daily-journal-a4', compact(
        'date', 'storeName', 'invoices', 'invoicesCount', 'totalSales',
        'cashSales', 'creditSales', 'partialSales', 'customerPayments',
        'totalCashCollected', 'expenses', 'totalExpenses', 'supplierPayments',
        'totalSupplierPaid', 'netCashToday', 'openingCashBalance',
        'expectedCashInDrawer', 'shiftsOnDate'
    ));
})->name('daily.journal.print');

// 🖨️ Item Movements Audit Ledger Print
Route::get('/items/{id}/movements/print', function ($id, \Illuminate\Http\Request $request) {
    $item = \App\Models\Item::withTrashed()->findOrFail($id);
    $storeId = ($request->query('store_id') && $request->query('store_id') !== 'all') ? (int)$request->query('store_id') : null;
    $fromDate = $request->query('from');
    $toDate = $request->query('to');
    $filterType = $request->query('type');

    $inTypes = ['purchase_in', 'stock_deposit_in', 'stock_adjustment_in', 'cancellation_in', 'transfer_in', 'sales_return_in', 'purchase_restore_in'];
    $outTypes = ['sales_out', 'waste_out', 'stock_adjustment_out', 'transfer_out', 'purchase_cancel_out', 'purchase_return_out'];
    $adjTypes = ['stock_adjustment_in', 'stock_adjustment_out', 'stock_deposit_in'];

    $storeName = 'كافة الفروع والمخازن';
    if ($storeId) {
        $st = \App\Models\Store::find($storeId);
        if ($st) $storeName = $st->name;
    }

    $baseQuery = \App\Models\StockMovement::with(['user', 'store'])
        ->where('item_id', $item->id)
        ->when($storeId, fn($q) => $q->where('store_id', $storeId))
        ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
        ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
        ->when($filterType === 'in', fn($q) => $q->whereIn('movement_type', $inTypes))
        ->when($filterType === 'out', fn($q) => $q->whereIn('movement_type', $outTypes))
        ->when($filterType === 'adjustments', fn($q) => $q->whereIn('movement_type', $adjTypes));

    $allMovements = (clone $baseQuery)->get();
    $totalIn = '0.000';
    $totalOut = '0.000';
    foreach ($allMovements as $mov) {
        if (in_array($mov->movement_type, $inTypes)) {
            $totalIn = bcadd($totalIn, (string)$mov->quantity, 3);
        } elseif (in_array($mov->movement_type, $outTypes)) {
            $totalOut = bcadd($totalOut, (string)$mov->quantity, 3);
        }
    }
    $netMovement = bcsub($totalIn, $totalOut, 3);
    $currentScopeStock = $storeId
        ? (string)(\App\Models\StoreStock::where('store_id', $storeId)->where('item_id', $item->id)->value('quantity') ?: '0.000')
        : (string)$item->current_stock;

    $movements = $baseQuery->oldest('created_at')->get();

    return view('layouts.print-item-movements-a4', compact(
        'item', 'storeName', 'fromDate', 'toDate', 'movements',
        'totalIn', 'totalOut', 'netMovement', 'currentScopeStock'
    ));
})->name('items.movements.print');

// 📊 CSV & Excel Export Routes
Route::get('/items/{id}/export-movements-csv', [App\Http\Controllers\ExportController::class, 'exportItemMovements'])->name('items.movements.export');
Route::get('/customers/{id}/export-csv', [App\Http\Controllers\ExportController::class, 'exportCustomerStatement'])->name('customers.export.csv');
Route::get('/suppliers/{id}/export-csv', [App\Http\Controllers\ExportController::class, 'exportSupplierStatement'])->name('suppliers.export.csv');
Route::get('/items/export-csv', [App\Http\Controllers\ExportController::class, 'exportInventory'])->name('items.export.csv');
Route::get('/activity-logs/export-csv', [\App\Http\Controllers\ActivityLogController::class, 'exportCsv'])->name('activity-logs.export.csv');

// 📱 PWA Manifest & Service Worker
Route::get('/manifest.json', function () {
    $baseUrl = url('/');
    $manifest = [
        'id' => 'sroor-erp-pos-app',
        'name' => 'سرور كوفي ERP | منصة إدارة الفواتير والمخزون',
        'short_name' => 'سرور POS',
        'description' => 'منصة سرور السحابية لإدارة مبيعات وفواتير ومخزون المؤسسات والمطاحن',
        'start_url' => $baseUrl . '/',
        'scope' => $baseUrl . '/',
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
    if (!file_exists($path)) {
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
})->where('any', '.*')->name('app');
