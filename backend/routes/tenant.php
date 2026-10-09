<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\CoffeeBlenderController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DailyJournalController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TrashController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\POSController;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Store;
use App\Support\ReportStoreFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

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

    // 1. Guest Authentication Routes (Vue 3 SPA)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    });

    // 1.1 Tenant Impersonation by Super Admin
    Route::get('/impersonate/{token}', function (string $token) {
        session(['is_impersonating' => true, 'impersonated_by_super' => true]);

        return UserImpersonation::makeResponse($token);
    })->name('impersonate');

    Route::post('/impersonate/leave', function () {
        Auth::logout();
        session()->forget(['is_impersonating', 'impersonated_by_super']);
        session()->invalidate();
        session()->regenerateToken();

        $centralDomain = env('CENTRAL_DOMAIN', 'localhost');
        $port = request()->getPort() ? (':'.request()->getPort()) : '';
        $scheme = request()->getScheme();

        return redirect()->away("{$scheme}://{$centralDomain}{$port}/admin/super/tenants");
    })->name('impersonate.leave')->middleware('auth');

    // 2. Logout Route
    Route::post('/tenant/logout', [AuthenticatedSessionController::class, 'destroy'])->name('tenant.logout')->middleware('auth');

    // 🌐 Pure Vue 3 SPA Host Route (Dual-Engine Mode)
    Route::get('/spa/{any?}', function () {
        return view('spa');
    })->where('any', '.*')->name('tenant.spa');

    // NOTE: `/invoices/{id}/print` is intentionally NOT defined here. It falls
    // through to the SPA catch-all, whose InvoicePrintView loads the invoice via
    // the authenticated, permission-checked API before calling window.print().

    // 3. Protected POS, ERP & Inventory Routes
    Route::middleware('auth')->group(function () {
        // Dashboard (Vue 3 SPA)
        Route::get('/', fn () => view('app'))->name('dashboard');

        // Invoices & POS (Vue 3 Fast Cashier Engine)
        Route::get('/pos', [POSController::class, 'index'])->name('pos.index')->middleware('can:pos.access');
        Route::get('/invoices/create', [POSController::class, 'index'])->name('invoices.create')->middleware('can:pos.access');
        Route::post('/pos/invoices', [POSController::class, 'store'])->name('pos.invoices.store')->middleware('can:pos.access');
        Route::post('/pos/customers', [POSController::class, 'storeCustomer'])->name('pos.customers.store')->middleware('can:pos.access');
        Route::get('/pos/customer-last-price', [POSController::class, 'getCustomerLastPrice'])->name('pos.customer_last_price')->middleware('can:pos.access');

        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('can:invoices.view');
        Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show')->middleware('can:invoices.view');
        Route::get('/invoices/{id}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit')->middleware('can:invoices.edit');
        Route::put('/invoices/{id}', [InvoiceController::class, 'update'])->name('invoices.update')->middleware('can:invoices.edit');
        Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel')->middleware('can:invoices.cancel');
        Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy'])->name('invoices.destroy')->middleware('can:invoices.delete');
        Route::post('/invoices/{id}/restore', [InvoiceController::class, 'restore'])->name('invoices.restore')->middleware('can:trash.access');

        // Blade invoice print pages (thermal receipt / A4) — permission + policy + store access enforced
        Route::get('/invoices/{id}/print/thermal', [InvoicePrintController::class, 'thermal'])->whereNumber('id')->name('invoices.print.thermal')->middleware('can:invoices.view');
        Route::get('/invoices/{id}/print/a4', [InvoicePrintController::class, 'a4'])->whereNumber('id')->name('invoices.print.a4')->middleware('can:invoices.view');

        // Daily Journal A4 Print Route
        Route::get('/daily-journal/print', function (Request $request) {
            $date = $request->query('date', now()->toDateString());
            // Resolved + access-checked on the exact value used below (non-admins can never get "all").
            $storeFilter = ReportStoreFilter::resolve($request, $request->user());

            $storeName = 'كافة الفروع والعربيات';
            if ($storeFilter) {
                $st = Store::find($storeFilter);
                if ($st) {
                    $storeName = $st->name;
                }
            }

            // Invoices
            $invoices = Invoice::with(['customer', 'store'])
                ->whereDate('invoice_date', $date)
                ->where('status', 'confirmed')
                ->when($storeFilter, fn ($q) => $q->where('store_id', $storeFilter))
                ->latest('id')
                ->get();

            $invoicesCount = $invoices->count();
            $totalSales = (string) ($invoices->sum('total_amount') ?: '0.000');
            $cashSales = (string) ($invoices->where('payment_type', 'cash')->sum('total_amount') ?: '0.000');
            $creditSales = (string) ($invoices->where('payment_type', 'credit')->sum('total_amount') ?: '0.000');
            $partialSales = (string) ($invoices->where('payment_type', 'partial')->sum('total_amount') ?: '0.000');
            $partialPaid = (string) ($invoices->where('payment_type', 'partial')->sum('paid_amount') ?: '0.000');

            $customerPayments = (string) (Payment::whereDate('payment_date', $date)->whereNotNull('customer_id')->sum('amount') ?: '0.000');
            $totalCashCollected = bcadd((string) bcadd($cashSales, $partialPaid, 3), (string) $customerPayments, 3);

            $expenses = Expense::with('store')
                ->whereDate('expense_date', $date)
                ->when($storeFilter, fn ($q) => $q->where('store_id', $storeFilter))
                ->get();
            $totalExpenses = (string) ($expenses->sum('amount') ?: '0.000');

            $supplierPayments = Payment::with('supplier')
                ->whereDate('payment_date', $date)
                ->whereNotNull('supplier_id')
                ->get();
            $totalSupplierPaid = (string) ($supplierPayments->sum('amount') ?: '0.000');

            $totalOutflows = bcadd($totalExpenses, $totalSupplierPaid, 3);
            $netCashToday = bcsub((string) $totalCashCollected, $totalOutflows, 3);

            $shiftsOnDate = CashShift::with(['user', 'store'])
                ->whereDate('opened_at', $date)
                ->when($storeFilter, fn ($q) => $q->where('store_id', $storeFilter))
                ->latest('id')
                ->get();

            $openingCashBalance = $shiftsOnDate->count() > 0 ? (string) $shiftsOnDate->first()->opening_cash_balance : '0.000';
            $expectedCashInDrawer = bcadd($openingCashBalance, $netCashToday, 3);

            return view('layouts.print-daily-journal-a4', compact(
                'date', 'storeName', 'invoices', 'invoicesCount', 'totalSales',
                'cashSales', 'creditSales', 'partialSales', 'customerPayments',
                'totalCashCollected', 'expenses', 'totalExpenses', 'supplierPayments',
                'totalSupplierPaid', 'netCashToday', 'openingCashBalance',
                'expectedCashInDrawer', 'shiftsOnDate'
            ));
        })->name('daily.journal.print')->middleware(['can:daily_journal.view', 'store.access']);

        // Items & Inventory Movements
        Route::get('/items', [ItemController::class, 'index'])->name('items.index')->middleware('can:items.view');
        Route::post('/items', [ItemController::class, 'store'])->name('items.store')->middleware('can:items.create');
        Route::put('/items/{id}', [ItemController::class, 'update'])->name('items.update')->middleware('can:items.edit');
        Route::delete('/items/{id}', [ItemController::class, 'destroy'])->name('items.destroy')->middleware('can:items.delete');
        Route::get('/items/{id}/movements', [ItemController::class, 'movements'])->name('items.movements')->middleware('can:items.view');

        // Multi-Store, Vans & Warehouse Management
        Route::get('/stores', [StoreController::class, 'index'])->name('stores')->middleware('can:stores.manage');
        Route::post('/stores', [StoreController::class, 'store'])->name('stores.store')->middleware('can:stores.manage');
        Route::post('/stores/switch', [StoreController::class, 'switchStore']);
        Route::put('/stores/{id}', [StoreController::class, 'update'])->name('stores.update')->middleware('can:stores.manage');
        Route::post('/stores/{id}/toggle-active', [StoreController::class, 'toggleActive'])->name('stores.toggle_active')->middleware('can:stores.manage');
        Route::post('/stores/{id}/assign-users', [StoreController::class, 'assignUsers'])->name('stores.assign_users')->middleware('can:stores.manage');
        Route::delete('/stores/{id}', [StoreController::class, 'destroy'])->name('stores.destroy')->middleware('can:stores.manage');
        Route::get('/store-stocks', [StoreController::class, 'stocks'])->name('store-stocks')->middleware('can:items.view');

        // Customers & Statements
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index')->middleware('can:customers.manage');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store')->middleware('can:customers.manage');
        Route::put('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update')->middleware('can:customers.manage');
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->name('customers.destroy')->middleware('can:customers.manage');
        Route::post('/customers/{id}/toggle-active', [CustomerController::class, 'toggleActive'])->name('customers.toggle_active')->middleware('can:customers.manage');
        Route::post('/customers/{id}/payments', [CustomerController::class, 'collectPayment'])->name('customers.payments')->middleware('can:customers.manage');
        Route::get('/customers/{id}/statement', [CustomerController::class, 'statement'])->name('customers.statement')->middleware('can:customers.statement');

        // Suppliers & Purchases & Statements
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index')->middleware('can:suppliers.manage');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store')->middleware('can:suppliers.manage');
        Route::put('/suppliers/{id}', [SupplierController::class, 'update'])->name('suppliers.update')->middleware('can:suppliers.manage');
        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->middleware('can:suppliers.manage');
        Route::post('/suppliers/{id}/pay', [SupplierController::class, 'pay'])->name('suppliers.pay')->middleware('can:suppliers.manage');
        Route::post('/suppliers/{id}/toggle-active', [SupplierController::class, 'toggleActive'])->name('suppliers.toggle_active')->middleware('can:suppliers.manage');
        Route::get('/suppliers/{id}/statement', [SupplierController::class, 'statement'])->name('suppliers.statement')->middleware('can:suppliers.statement');
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index')->middleware('can:purchases.view');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create')->middleware('can:purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store')->middleware('can:purchases.create');
        Route::post('/purchases/{id}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel')->middleware('can:purchases.delete');
        Route::get('/purchases/smart-reorder', [PurchaseController::class, 'smartReorder'])->name('purchases.reorder')->middleware('can:purchases.view');

        // Returns & Reversals
        Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index')->middleware('can:returns.manage');
        Route::get('/returns/create', [ReturnController::class, 'create'])->name('returns.create')->middleware('can:returns.manage');
        Route::post('/returns', [ReturnController::class, 'store'])->name('returns.store')->middleware('can:returns.manage');
        Route::delete('/returns/{id}', [ReturnController::class, 'destroy'])->name('returns.destroy')->middleware('can:returns.manage');

        // Financial & Profit Reports (Admin & Accountant / reports.view)
        Route::get('/reports', [ReportController::class, 'comprehensive'])->name('reports.index')->middleware('can:reports.view');
        Route::get('/reports/export-abc', [ReportController::class, 'inventory'])->name('reports.export.abc')->middleware('can:reports.view');

        // Operational Expenses & Supplies
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index')->middleware('can:expenses.manage');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store')->middleware('can:expenses.manage');
        Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->name('expenses.update')->middleware('can:expenses.manage');
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->name('expenses.destroy')->middleware('can:expenses.manage');

        // Coffee Blending Master & Roastery Recipe
        Route::get('/coffee-blender', [CoffeeBlenderController::class, 'calculate'])->name('coffee.blender')->middleware('can:items.create');
        Route::post('/coffee-blender/invoice', [CoffeeBlenderController::class, 'createInvoice'])->name('coffee.blender.invoice')->middleware('can:items.create');

        // Daily Journal & Cashier Shifts (يوم بيوم)
        Route::get('/daily-journal', [DailyJournalController::class, 'index'])->name('daily.journal')->middleware('can:daily_journal.view');
        Route::get('/shifts', [DailyJournalController::class, 'index'])->name('shifts.index')->middleware('can:daily_journal.view');
        Route::post('/daily-journal/open-shift', [DailyJournalController::class, 'openShift'])->name('daily.journal.open_shift')->middleware('can:daily_journal.view');
        Route::post('/daily-journal/close-shift/{id}', [DailyJournalController::class, 'closeShift'])->name('daily.journal.close_shift')->middleware('can:daily_journal.view');
        Route::post('/daily-journal/expense', [DailyJournalController::class, 'storeExpense'])->name('daily.journal.expense')->middleware('can:daily_journal.view');

        // Auth, Profile, Settings, Trash, Activity Logs & User Management
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index')->middleware('can:logs.view');
        Route::get('/activity-logs/export-csv', [ActivityLogController::class, 'exportCsv'])->name('tenant.activity-logs.export.csv')->middleware('can:logs.view');

        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index')->middleware('can:trash.access');
        Route::post('/trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore')->middleware('can:trash.access');
        Route::delete('/trash/{type}/{id}/force-delete', [TrashController::class, 'forceDelete'])->name('trash.force-delete')->middleware('can:trash.access');

        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index')->middleware('can:roles.manage');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update')->middleware('can:roles.manage');
        Route::post('/settings/telegram/test', [SettingController::class, 'sendTestTelegram'])->name('settings.telegram.test')->middleware('can:roles.manage');

        Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('can:roles.manage');
        Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('can:roles.manage');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update')->middleware('can:roles.manage');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('can:roles.manage');
        Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle')->middleware('can:roles.manage');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('can:roles.manage');
        Route::put('/roles/{id}', [RoleController::class, 'updatePermissions'])->name('roles.update')->middleware('can:roles.manage');

        // Theme Toggle (Dark / Light Mode)
        Route::post('/theme-toggle', function (Request $request) {
            $theme = $request->input('theme', 'dark');
            if (in_array($theme, ['dark', 'light']) && Auth::check()) {
                Auth::user()->update(['theme_preference' => $theme]);
            }
            if ($request->wantsJson()) {
                return response()->json(['status' => 'success', 'theme' => $theme]);
            }

            return back();
        })->name('theme.toggle');

        // Store Switcher (Fast active branch/van switch for authorized users)
        Route::post('/tenant/store/switch', function (Request $request) {
            $storeId = (int) $request->input('store_id');
            $store = Store::where('id', $storeId)->where('is_active', true)->first();

            if ($store) {
                $user = Auth::user();
                if ($user->hasRole('admin') || $user->stores()->where('stores.id', $storeId)->exists() || (int) $user->default_store_id === $storeId) {
                    session(['current_store_id' => $storeId]);
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'success', 'store' => $store]);
                    }

                    return back()->with('success', "تم التبديل إلى ({$store->name}) بنجاح");
                }
            }

            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'غير مصرح'], 403);
            }

            return back()->with('error', 'غير مصرح بالوصول إلى هذا الفرع');
        })->name('tenant.store.switch');
    });
});
