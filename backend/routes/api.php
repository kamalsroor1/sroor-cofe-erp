<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TreasuryController;
use App\Http\Controllers\Api\AppUpdateController;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\ResolveApiTenancy;

use App\Http\Controllers\Api\PermissionApiController;
use App\Http\Controllers\Api\SystemContextApiController;

Route::prefix('v1')->middleware([ResolveApiTenancy::class])->group(function () {
    // 1. App Updates & Guest Endpoints
    Route::get('/app/version', [AppUpdateController::class, 'checkVersion'])->name('api.app.version');
    Route::get('/app/download-apk', [AppUpdateController::class, 'downloadApk'])->name('api.app.download_apk');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::get('/system/translations', [SystemContextApiController::class, 'translations'])->name('api.system.translations');

    // 2. Protected Endpoints (Requires valid Bearer Token)
    Route::middleware(ApiTokenAuth::class)->group(function () {
        // Auth Profile & Logout
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        // System Context (Bootstrap payload replacing Inertia::share)
        Route::get('/system/context', [SystemContextApiController::class, 'context'])->name('api.system.context');

        // Permissions & Roles Tree
        Route::get('/permissions', [PermissionApiController::class, 'index'])->name('api.permissions.index');

        // High-Performance Consolidated Dashboard Summary
        Route::get('/dashboard/summary', [\App\Http\Controllers\Api\DashboardApiController::class, 'index'])->name('api.dashboard.summary');

        // Stores & Branches (CRUD, Stocks & Switching)
        Route::get('/stores', [StoreController::class, 'index'])->name('api.stores.index');
        Route::post('/stores', [StoreController::class, 'store'])->name('api.stores.store');
        Route::get('/stores/stocks', [StoreController::class, 'stocks'])->name('api.stores.stocks');
        Route::post('/stores/switch', [StoreController::class, 'switchStore'])->name('api.stores.switch');
        Route::get('/stores/{id}', [StoreController::class, 'show'])->name('api.stores.show');
        Route::put('/stores/{id}', [StoreController::class, 'update'])->name('api.stores.update');
        Route::delete('/stores/{id}', [StoreController::class, 'destroy'])->name('api.stores.destroy');
        Route::patch('/stores/{id}/toggle-active', [StoreController::class, 'toggleActive'])->name('api.stores.toggle_active');
        Route::post('/stores/{id}/assign-users', [StoreController::class, 'assignUsers'])->name('api.stores.assign_users');

        // Customers & Statements
        Route::get('/customers', [CustomerController::class, 'index'])->name('api.customers.index');
        Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('api.customers.show');
        Route::post('/customers', [CustomerController::class, 'store'])->name('api.customers.store');
        Route::put('/customers/{id}', [CustomerController::class, 'update'])->name('api.customers.update');
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->name('api.customers.destroy');
        Route::patch('/customers/{id}/toggle-active', [CustomerController::class, 'toggleActive'])->name('api.customers.toggle_active');
        Route::post('/customers/{id}/collect-payment', [CustomerController::class, 'collectPayment'])->name('api.customers.collect_payment');
        Route::get('/customers/{id}/statement', [CustomerController::class, 'statement'])->name('api.customers.statement');

        // Suppliers & Statements
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('api.suppliers.index');
        Route::get('/suppliers/{id}', [SupplierController::class, 'show'])->name('api.suppliers.show');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('api.suppliers.store');
        Route::put('/suppliers/{id}', [SupplierController::class, 'update'])->name('api.suppliers.update');
        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->name('api.suppliers.destroy');
        Route::patch('/suppliers/{id}/toggle-active', [SupplierController::class, 'toggleActive'])->name('api.suppliers.toggle_active');
        Route::post('/suppliers/{id}/pay', [SupplierController::class, 'pay'])->name('api.suppliers.pay');
        Route::get('/suppliers/{id}/statement', [SupplierController::class, 'statement'])->name('api.suppliers.statement');
        // Purchases & Coffee Bean Inbound & Smart Reorder
        Route::get('/purchases', [\App\Http\Controllers\Api\PurchaseController::class, 'index'])->name('api.purchases.index');
        Route::get('/purchases/smart-reorder', [\App\Http\Controllers\Api\PurchaseController::class, 'smartReorder'])->name('api.purchases.smart_reorder');
        Route::get('/purchases/{id}', [\App\Http\Controllers\Api\PurchaseController::class, 'show'])->name('api.purchases.show');
        Route::post('/purchases', [\App\Http\Controllers\Api\PurchaseController::class, 'store'])->name('api.purchases.store');
        Route::post('/purchases/{id}/cancel', [\App\Http\Controllers\Api\PurchaseController::class, 'cancel'])->name('api.purchases.cancel');

        // Items & Stock by Branch & Low Stock Radar & Movements
        Route::get('/items', [ItemController::class, 'index'])->name('api.items.index');
        Route::get('/items/low-stock', [ItemController::class, 'lowStock'])->name('api.items.low_stock');
        Route::get('/items/{id}', [ItemController::class, 'show'])->name('api.items.show');
        Route::post('/items', [ItemController::class, 'store'])->name('api.items.store');
        Route::put('/items/{id}', [ItemController::class, 'update'])->name('api.items.update');
        Route::delete('/items/{id}', [ItemController::class, 'destroy'])->name('api.items.destroy');
        Route::patch('/items/{id}/toggle-active', [ItemController::class, 'toggleActive'])->name('api.items.toggle_active');
        Route::post('/items/{id}/adjust-stock', [ItemController::class, 'adjustStock'])->name('api.items.adjust_stock');
        Route::get('/items/{id}/movements', [ItemController::class, 'movements'])->name('api.items.movements');

        // Audit Trail & Activity Logs
        Route::get('/activity-logs', [\App\Http\Controllers\Api\ActivityLogController::class, 'index'])->name('api.activity_logs.index');

        // POS & Sales Invoices & WhatsApp
        Route::get('/invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'index'])->name('api.invoices.index');
        Route::get('/invoices/{id}', [\App\Http\Controllers\Api\InvoiceController::class, 'show'])->name('api.invoices.show');
        Route::post('/invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'store'])->name('api.invoices.store');
        Route::post('/invoices/{id}/cancel', [\App\Http\Controllers\Api\InvoiceController::class, 'cancel'])->name('api.invoices.cancel');

        // POS Fast Operations
        Route::get('/pos/bootstrap', [\App\Http\Controllers\Api\PosController::class, 'bootstrap'])->name('api.pos.bootstrap');
        Route::post('/pos/checkout', [\App\Http\Controllers\Api\PosController::class, 'checkout'])->name('api.pos.checkout');
        Route::post('/pos/quick-customer', [\App\Http\Controllers\Api\PosController::class, 'quickCustomer'])->name('api.pos.quick_customer');
        Route::get('/pos/last-price', [\App\Http\Controllers\Api\PosController::class, 'lastPrice'])->name('api.pos.last_price');

        // Payments & Vouchers (Customer Receipts / Supplier Disbursements)
        Route::get('/payments', [PaymentController::class, 'index'])->name('api.payments.index');
        Route::post('/payments/customer-receipt', [PaymentController::class, 'customerReceipt'])->name('api.payments.customer_receipt');
        Route::post('/payments/supplier-voucher', [PaymentController::class, 'supplierVoucher'])->name('api.payments.supplier_voucher');

        // Cashier Shifts & Z-Report & Daily Journal
        Route::get('/shifts', [\App\Http\Controllers\Api\ShiftController::class, 'index'])->name('api.shifts.index');
        Route::get('/shifts/current', [\App\Http\Controllers\Api\ShiftController::class, 'current'])->name('api.shifts.current');
        Route::post('/shifts/open', [\App\Http\Controllers\Api\ShiftController::class, 'open'])->name('api.shifts.open');
        Route::post('/shifts/close', [\App\Http\Controllers\Api\ShiftController::class, 'close'])->name('api.shifts.close');
        Route::get('/shifts/{id}/z-report', [\App\Http\Controllers\Api\ShiftController::class, 'zReport'])->name('api.shifts.z_report');
        Route::get('/daily-journal', [\App\Http\Controllers\Api\DailyJournalController::class, 'index'])->name('api.daily_journal.index');

        // Expenses & Petty Cash
        Route::get('/expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'index'])->name('api.expenses.index');
        Route::get('/expenses/{id}', [\App\Http\Controllers\Api\ExpenseController::class, 'show'])->name('api.expenses.show');
        Route::post('/expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'store'])->name('api.expenses.store');
        Route::put('/expenses/{id}', [\App\Http\Controllers\Api\ExpenseController::class, 'update'])->name('api.expenses.update');
        Route::delete('/expenses/{id}', [\App\Http\Controllers\Api\ExpenseController::class, 'destroy'])->name('api.expenses.destroy');

        // Treasury & Quick Financial Stats
        Route::get('/treasury/summary', [TreasuryController::class, 'summary'])->name('api.treasury.summary');

        // Profit & Loss Reports & Business Analytics
        Route::get('/reports/summary', [\App\Http\Controllers\Api\ReportController::class, 'summary'])->name('api.reports.summary');
        Route::get('/reports/comprehensive', [\App\Http\Controllers\Api\ReportController::class, 'comprehensive'])->name('api.reports.comprehensive');
        Route::get('/reports/items', [\App\Http\Controllers\Api\ReportController::class, 'items'])->name('api.reports.items');
        Route::get('/reports/stores', [\App\Http\Controllers\Api\ReportController::class, 'stores'])->name('api.reports.stores');
        Route::get('/reports/customers', [\App\Http\Controllers\Api\ReportController::class, 'customers'])->name('api.reports.customers');
        Route::get('/reports/expenses', [\App\Http\Controllers\Api\ReportController::class, 'expenses'])->name('api.reports.expenses');
        Route::get('/reports/inventory', [\App\Http\Controllers\Api\ReportController::class, 'inventory'])->name('api.reports.inventory');
        Route::get('/reports/treasury', [\App\Http\Controllers\Api\ReportController::class, 'treasury'])->name('api.reports.treasury');
        Route::get('/reports/top-items', [\App\Http\Controllers\Api\ReportController::class, 'topItems'])->name('api.reports.top_items');
        Route::get('/reports/items/{id}/card', [\App\Http\Controllers\Api\ReportController::class, 'itemCard'])->name('api.reports.item_card');

        // Returns (Sales & Purchase Returns)
        Route::get('/returns', [\App\Http\Controllers\Api\ReturnController::class, 'index'])->name('api.returns.index');
        Route::get('/returns/{id}', [\App\Http\Controllers\Api\ReturnController::class, 'show'])->name('api.returns.show');
        Route::post('/returns', [\App\Http\Controllers\Api\ReturnController::class, 'store'])->name('api.returns.store');
        Route::delete('/returns/{id}', [\App\Http\Controllers\Api\ReturnController::class, 'destroy'])->name('api.returns.destroy');

        // Stock Transfers between stores/branches
        Route::get('/transfers', [\App\Http\Controllers\Api\StockTransferController::class, 'index'])->name('api.transfers.index');
        Route::get('/transfers/{id}', [\App\Http\Controllers\Api\StockTransferController::class, 'show'])->name('api.transfers.show');
        Route::post('/transfers', [\App\Http\Controllers\Api\StockTransferController::class, 'store'])->name('api.transfers.store');
        Route::post('/transfers/{id}/cancel', [\App\Http\Controllers\Api\StockTransferController::class, 'cancel'])->name('api.transfers.cancel');

        // Coffee Blender Engine & Custom Roasting Studio
        Route::post('/coffee-blender/calculate', [\App\Http\Controllers\Api\CoffeeBlenderController::class, 'calculate'])->name('api.coffee_blender.calculate');
        Route::post('/coffee-blender/invoice', [\App\Http\Controllers\Api\CoffeeBlenderController::class, 'createInvoice'])->name('api.coffee_blender.invoice');

        // Users & Employees Management
        Route::get('/users', [\App\Http\Controllers\Api\UserController::class, 'index'])->name('api.users.index');
        Route::get('/users/{id}', [\App\Http\Controllers\Api\UserController::class, 'show'])->name('api.users.show');
        Route::post('/users', [\App\Http\Controllers\Api\UserController::class, 'store'])->name('api.users.store');
        Route::put('/users/{id}', [\App\Http\Controllers\Api\UserController::class, 'update'])->name('api.users.update');
        Route::delete('/users/{id}', [\App\Http\Controllers\Api\UserController::class, 'destroy'])->name('api.users.destroy');
        Route::patch('/users/{id}/toggle-active', [\App\Http\Controllers\Api\UserController::class, 'toggleActive'])->name('api.users.toggle_active');

        // Roles & Permissions Matrix
        Route::get('/roles', [\App\Http\Controllers\Api\RoleController::class, 'index'])->name('api.roles.index');
        Route::put('/roles/{id}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'updatePermissions'])->name('api.roles.update_permissions');

        // Activity & Audit Logs
        Route::get('/activity-logs', [\App\Http\Controllers\Api\ActivityLogController::class, 'index'])->name('api.activity_logs.index');

        // Admin Settings
        Route::get('/settings', [\App\Http\Controllers\Api\SettingController::class, 'index'])->name('api.settings.index');
        Route::post('/settings', [\App\Http\Controllers\Api\SettingController::class, 'update'])->name('api.settings.update');
    });
});
