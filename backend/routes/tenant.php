<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

use App\Models\Invoice;

/*
|--------------------------------------------------------------------------
| Tenant Routes (Isolated Database Context)
|--------------------------------------------------------------------------
| Every route in this group runs within the isolated database and storage
| context of the identified tenant.
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // 1. Guest Authentication Routes (Inertia.js + Vue 3)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store']);
    });

    // 1.1 Tenant Impersonation by Super Admin
    Route::get('/impersonate/{token}', function (string $token) {
        session(['is_impersonating' => true, 'impersonated_by_super' => true]);
        return \Stancl\Tenancy\Features\UserImpersonation::makeResponse($token);
    })->name('impersonate');

    Route::post('/impersonate/leave', function () {
        Auth::logout();
        session()->forget(['is_impersonating', 'impersonated_by_super']);
        session()->invalidate();
        session()->regenerateToken();

        $centralDomain = env('CENTRAL_DOMAIN', 'localhost');
        $port = request()->getPort() ? (':' . request()->getPort()) : '';
        $scheme = request()->getScheme();

        return \Inertia\Inertia::location("{$scheme}://{$centralDomain}{$port}/admin/super/tenants");
    })->name('impersonate.leave')->middleware('auth');

    // 2. Logout Route
    Route::post('/tenant/logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])->name('tenant.logout')->middleware('auth');

    // 🌐 Pure Vue 3 SPA Host Route (Dual-Engine Mode)
    Route::get('/spa/{any?}', function () {
        return view('spa');
    })->where('any', '.*')->name('tenant.spa');

    // 🖨️ Thermal Receipt Printing Route (Cashier Fast Print & Popup Windows)
    Route::get('/invoices/{id}/print', function ($id) {
        $invoice = \App\Models\Invoice::with(['customer', 'items.item', 'additionalExpenses'])->findOrFail($id);
        return view('layouts.print-thermal', compact('invoice'));
    })->name('invoices.print.default');

    Route::get('/invoices/{id}/print/thermal', function ($id) {
        $invoice = \App\Models\Invoice::with(['customer', 'items.item', 'additionalExpenses'])->findOrFail($id);
        return view('layouts.print-thermal', compact('invoice'));
    })->name('invoices.print.thermal');

    // 🖨️ Standard A4 Tax & Commercial Invoice Print
    Route::get('/invoices/{id}/print/a4', function ($id) {
        $invoice = \App\Models\Invoice::with(['customer', 'items.item', 'additionalExpenses'])->findOrFail($id);
        return view('layouts.print-a4', compact('invoice'));
    })->name('invoices.print.a4');

    // 3. Protected POS, ERP & Inventory Routes
    Route::middleware('auth')->group(function () {
        // Dashboard (Inertia.js + Vue 3 SPA)
        Route::get('/', fn() => view('app'))->name('dashboard');

        // Invoices & POS (Vue 3 Fast Cashier Engine)
        Route::get('/pos', [\App\Http\Controllers\POSController::class, 'index'])->name('pos.index')->middleware('can:pos.access');
        Route::get('/invoices/create', [\App\Http\Controllers\POSController::class, 'index'])->name('invoices.create')->middleware('can:pos.access');
        Route::post('/pos/invoices', [\App\Http\Controllers\POSController::class, 'store'])->name('pos.invoices.store')->middleware('can:pos.access');
        Route::post('/pos/customers', [\App\Http\Controllers\POSController::class, 'storeCustomer'])->name('pos.customers.store')->middleware('can:pos.access');
        Route::get('/pos/customer-last-price', [\App\Http\Controllers\POSController::class, 'getCustomerLastPrice'])->name('pos.customer_last_price')->middleware('can:pos.access');

        Route::get('/invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'index'])->name('invoices.index')->middleware('can:invoices.view');
        Route::get('/invoices/{id}', [\App\Http\Controllers\Api\InvoiceController::class, 'show'])->name('invoices.show')->middleware('can:invoices.view');
        Route::get('/invoices/{id}/edit', [\App\Http\Controllers\Api\InvoiceController::class, 'edit'])->name('invoices.edit')->middleware('can:invoices.edit');
        Route::put('/invoices/{id}', [\App\Http\Controllers\Api\InvoiceController::class, 'update'])->name('invoices.update')->middleware('can:invoices.edit');
        Route::post('/invoices/{id}/cancel', [\App\Http\Controllers\Api\InvoiceController::class, 'cancel'])->name('invoices.cancel')->middleware('can:invoices.cancel');
        Route::delete('/invoices/{id}', [\App\Http\Controllers\Api\InvoiceController::class, 'destroy'])->name('invoices.destroy')->middleware('can:invoices.delete');
        Route::post('/invoices/{id}/restore', [\App\Http\Controllers\Api\InvoiceController::class, 'restore'])->name('invoices.restore')->middleware('can:trash.access');

        // Daily Journal A4 Print Route
        Route::get('/daily-journal/print', function (\Illuminate\Http\Request $request) {
            $date = $request->query('date', now()->toDateString());
            $storeId = $request->query('store_id', 'all');
            $storeFilter = ($storeId !== 'all' && is_numeric($storeId)) ? (int)$storeId : null;

            $storeName = 'كافة الفروع والعربيات';
            if ($storeFilter) {
                $st = \App\Models\Store::find($storeFilter);
                if ($st) $storeName = $st->name;
            }

            // Invoices
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
        })->name('daily.journal.print')->middleware('can:daily_journal.view');

        // Items & Inventory Movements
        Route::get('/items', [\App\Http\Controllers\Api\ItemController::class, 'index'])->name('items.index')->middleware('can:items.view');
        Route::post('/items', [\App\Http\Controllers\Api\ItemController::class, 'store'])->name('items.store')->middleware('can:items.manage');
        Route::put('/items/{id}', [\App\Http\Controllers\Api\ItemController::class, 'update'])->name('items.update')->middleware('can:items.manage');
        Route::delete('/items/{id}', [\App\Http\Controllers\Api\ItemController::class, 'destroy'])->name('items.destroy')->middleware('can:items.manage');
        Route::get('/items/{id}/movements', [\App\Http\Controllers\Api\ItemController::class, 'movements'])->name('items.movements')->middleware('can:items.view');

        // Multi-Store, Vans & Warehouse Management
        Route::get('/stores', [\App\Http\Controllers\Api\StoreController::class, 'index'])->name('stores')->middleware('can:stores.manage');
        Route::post('/stores', [\App\Http\Controllers\Api\StoreController::class, 'store'])->name('stores.store')->middleware('can:stores.manage');
        Route::post('/stores/switch', [\App\Http\Controllers\Api\StoreController::class, 'switchStore']);
        Route::put('/stores/{id}', [\App\Http\Controllers\Api\StoreController::class, 'update'])->name('stores.update')->middleware('can:stores.manage');
        Route::post('/stores/{id}/toggle-active', [\App\Http\Controllers\Api\StoreController::class, 'toggleActive'])->name('stores.toggle_active')->middleware('can:stores.manage');
        Route::post('/stores/{id}/assign-users', [\App\Http\Controllers\Api\StoreController::class, 'assignUsers'])->name('stores.assign_users')->middleware('can:stores.manage');
        Route::delete('/stores/{id}', [\App\Http\Controllers\Api\StoreController::class, 'destroy'])->name('stores.destroy')->middleware('can:stores.manage');
        Route::get('/store-stocks', [\App\Http\Controllers\Api\StoreController::class, 'stocks'])->name('store-stocks')->middleware('can:items.view');

        // Customers & Statements
        Route::get('/customers', [\App\Http\Controllers\Api\CustomerController::class, 'index'])->name('customers.index')->middleware('can:customers.manage');
        Route::post('/customers', [\App\Http\Controllers\Api\CustomerController::class, 'store'])->name('customers.store')->middleware('can:customers.manage');
        Route::put('/customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'update'])->name('customers.update')->middleware('can:customers.manage');
        Route::delete('/customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy'])->name('customers.destroy')->middleware('can:customers.manage');
        Route::post('/customers/{id}/toggle-active', [\App\Http\Controllers\Api\CustomerController::class, 'toggleActive'])->name('customers.toggle_active')->middleware('can:customers.manage');
        Route::post('/customers/{id}/payments', [\App\Http\Controllers\Api\CustomerController::class, 'collectPayment'])->name('customers.payments')->middleware('can:customers.manage');
        Route::get('/customers/{id}/statement', [\App\Http\Controllers\Api\CustomerController::class, 'statement'])->name('customers.statement')->middleware('can:customers.statement');

        // Suppliers & Purchases & Statements
        Route::get('/suppliers', [\App\Http\Controllers\Api\SupplierController::class, 'index'])->name('suppliers.index')->middleware('can:suppliers.manage');
        Route::post('/suppliers', [\App\Http\Controllers\Api\SupplierController::class, 'store'])->name('suppliers.store')->middleware('can:suppliers.manage');
        Route::put('/suppliers/{id}', [\App\Http\Controllers\Api\SupplierController::class, 'update'])->name('suppliers.update')->middleware('can:suppliers.manage');
        Route::delete('/suppliers/{id}', [\App\Http\Controllers\Api\SupplierController::class, 'destroy'])->name('suppliers.destroy')->middleware('can:suppliers.manage');
        Route::post('/suppliers/{id}/pay', [\App\Http\Controllers\Api\SupplierController::class, 'pay'])->name('suppliers.pay')->middleware('can:suppliers.manage');
        Route::post('/suppliers/{id}/toggle-active', [\App\Http\Controllers\Api\SupplierController::class, 'toggleActive'])->name('suppliers.toggle_active')->middleware('can:suppliers.manage');
        Route::get('/suppliers/{id}/statement', [\App\Http\Controllers\Api\SupplierController::class, 'statement'])->name('suppliers.statement')->middleware('can:suppliers.statement');
        Route::get('/purchases', [\App\Http\Controllers\Api\PurchaseController::class, 'index'])->name('purchases.index')->middleware('can:purchases.view');
        Route::get('/purchases/create', [\App\Http\Controllers\Api\PurchaseController::class, 'create'])->name('purchases.create')->middleware('can:purchases.create');
        Route::post('/purchases', [\App\Http\Controllers\Api\PurchaseController::class, 'store'])->name('purchases.store')->middleware('can:purchases.create');
        Route::post('/purchases/{id}/cancel', [\App\Http\Controllers\Api\PurchaseController::class, 'cancel'])->name('purchases.cancel')->middleware('can:purchases.delete');
        Route::get('/purchases/smart-reorder', [\App\Http\Controllers\Api\PurchaseController::class, 'smartReorder'])->name('purchases.reorder')->middleware('can:purchases.view');

        // Returns & Reversals
        Route::get('/returns', [\App\Http\Controllers\Api\ReturnController::class, 'index'])->name('returns.index')->middleware('can:returns.manage');
        Route::get('/returns/create', [\App\Http\Controllers\Api\ReturnController::class, 'create'])->name('returns.create')->middleware('can:returns.manage');
        Route::post('/returns', [\App\Http\Controllers\Api\ReturnController::class, 'store'])->name('returns.store')->middleware('can:returns.manage');
        Route::delete('/returns/{id}', [\App\Http\Controllers\Api\ReturnController::class, 'destroy'])->name('returns.destroy')->middleware('can:returns.manage');

        // Financial & Profit Reports (Admin & Accountant / reports.view)
        Route::get('/reports', [\App\Http\Controllers\Api\ReportController::class, 'comprehensive'])->name('reports.index')->middleware('can:reports.view');
        Route::get('/reports/export-abc', [\App\Http\Controllers\Api\ReportController::class, 'inventory'])->name('reports.export.abc')->middleware('can:reports.view');

        // Operational Expenses & Supplies
        Route::get('/expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'index'])->name('expenses.index')->middleware('can:expenses.manage');
        Route::post('/expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'store'])->name('expenses.store')->middleware('can:expenses.manage');
        Route::put('/expenses/{id}', [\App\Http\Controllers\Api\ExpenseController::class, 'update'])->name('expenses.update')->middleware('can:expenses.manage');
        Route::delete('/expenses/{id}', [\App\Http\Controllers\Api\ExpenseController::class, 'destroy'])->name('expenses.destroy')->middleware('can:expenses.manage');

        // Coffee Blending Master & Roastery Recipe
        Route::get('/coffee-blender', [\App\Http\Controllers\Api\CoffeeBlenderController::class, 'calculate'])->name('coffee.blender')->middleware('can:items.create');
        Route::post('/coffee-blender/invoice', [\App\Http\Controllers\Api\CoffeeBlenderController::class, 'createInvoice'])->name('coffee.blender.invoice')->middleware('can:items.create');

        // Daily Journal & Cashier Shifts (يوم بيوم)
        Route::get('/daily-journal', [\App\Http\Controllers\Api\DailyJournalController::class, 'index'])->name('daily.journal')->middleware('can:daily_journal.view');
        Route::get('/shifts', [\App\Http\Controllers\Api\DailyJournalController::class, 'index'])->name('shifts.index')->middleware('can:daily_journal.view');
        Route::post('/daily-journal/open-shift', [\App\Http\Controllers\DailyJournalController::class, 'openShift'])->name('daily.journal.open_shift')->middleware('can:daily_journal.view');
        Route::post('/daily-journal/close-shift/{id}', [\App\Http\Controllers\DailyJournalController::class, 'closeShift'])->name('daily.journal.close_shift')->middleware('can:daily_journal.view');
        Route::post('/daily-journal/expense', [\App\Http\Controllers\DailyJournalController::class, 'storeExpense'])->name('daily.journal.expense')->middleware('can:daily_journal.view');

        // Auth, Profile, Settings, Trash, Activity Logs & User Management
        Route::get('/activity-logs', [\App\Http\Controllers\Api\ActivityLogController::class, 'index'])->name('activity-logs.index')->middleware('can:logs.view');
        Route::get('/activity-logs/export-csv', [\App\Http\Controllers\Api\ActivityLogController::class, 'exportCsv'])->name('tenant.activity-logs.export.csv')->middleware('can:logs.view');
        
        Route::get('/trash', [\App\Http\Controllers\Api\TrashController::class, 'index'])->name('trash.index')->middleware('can:trash.access');
        Route::post('/trash/{type}/{id}/restore', [\App\Http\Controllers\Api\TrashController::class, 'restore'])->name('trash.restore')->middleware('can:trash.access');
        Route::delete('/trash/{type}/{id}/force-delete', [\App\Http\Controllers\Api\TrashController::class, 'forceDelete'])->name('trash.force-delete')->middleware('can:trash.access');

        Route::get('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'show'])->name('profile');
        Route::put('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'update'])->name('profile.update');

        Route::get('/settings', [\App\Http\Controllers\Api\SettingController::class, 'index'])->name('settings.index')->middleware('can:roles.manage');
        Route::post('/settings', [\App\Http\Controllers\Api\SettingController::class, 'update'])->name('settings.update')->middleware('can:roles.manage');
        Route::post('/settings/telegram/test', [\App\Http\Controllers\Api\SettingController::class, 'sendTestTelegram'])->name('settings.telegram.test')->middleware('can:roles.manage');
        Route::post('/settings/telegram/daily-summary', [\App\Http\Controllers\Api\SettingController::class, 'sendDailySummaryTelegram'])->name('settings.telegram.daily_summary')->middleware('can:roles.manage');
        Route::post('/settings/telegram/low-stock', [\App\Http\Controllers\Api\SettingController::class, 'sendLowStockTelegram'])->name('settings.telegram.low_stock')->middleware('can:roles.manage');
        Route::post('/settings/telegram/overdue-shifts', [\App\Http\Controllers\Api\SettingController::class, 'sendOverdueShiftTelegram'])->name('settings.telegram.overdue_shifts')->middleware('can:roles.manage');
        Route::post('/settings/telegram/backup', [\App\Http\Controllers\Api\SettingController::class, 'sendBackupTelegram'])->name('settings.telegram.backup')->middleware('can:roles.manage');
        Route::get('/settings/backup/download', [\App\Http\Controllers\Api\SettingController::class, 'downloadBackup'])->name('settings.backup.download')->middleware('can:roles.manage');
        Route::post('/settings/clear-cache', [\App\Http\Controllers\Api\SettingController::class, 'clearCache'])->name('settings.clear_cache')->middleware('can:roles.manage');

        Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index')->middleware('can:roles.manage');
        Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store')->middleware('can:roles.manage');
        Route::put('/users/{id}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update')->middleware('can:roles.manage');
        Route::delete('/users/{id}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy')->middleware('can:roles.manage');
        Route::post('/users/{id}/toggle-active', [\App\Http\Controllers\UserController::class, 'toggleActive'])->name('users.toggle')->middleware('can:roles.manage');

        Route::get('/roles', [\App\Http\Controllers\Api\RoleController::class, 'index'])->name('roles.index')->middleware('can:roles.manage');
        Route::put('/roles/{id}', [\App\Http\Controllers\Api\RoleController::class, 'updatePermissions'])->name('roles.update')->middleware('can:roles.manage');

        // Theme Toggle (Dark / Light Mode)
        Route::post('/theme-toggle', function (\Illuminate\Http\Request $request) {
            $theme = $request->input('theme', 'dark');
            if (in_array($theme, ['dark', 'light']) && Auth::check()) {
                Auth::user()->update(['theme_preference' => $theme]);
            }
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['status' => 'success', 'theme' => $theme]);
            }
            return back();
        })->name('theme.toggle');

        // Store Switcher (Fast active branch/van switch for authorized users)
        Route::post('/tenant/store/switch', function (\Illuminate\Http\Request $request) {
            $storeId = (int)$request->input('store_id');
            $store = \App\Models\Store::where('id', $storeId)->where('is_active', true)->first();

            if ($store) {
                $user = Auth::user();
                if ($user->hasRole('admin') || $user->stores()->where('stores.id', $storeId)->exists() || (int)$user->default_store_id === $storeId) {
                    session(['current_store_id' => $storeId]);
                    if ($request->wantsJson() && !$request->header('X-Inertia')) {
                        return response()->json(['status' => 'success', 'store' => $store]);
                    }
                    return back()->with('success', "تم التبديل إلى ({$store->name}) بنجاح");
                }
            }

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['status' => 'error', 'message' => 'غير مصرح'], 403);
            }
            return back()->with('error', 'غير مصرح بالوصول إلى هذا الفرع');
        })->name('tenant.store.switch');
    });
});

