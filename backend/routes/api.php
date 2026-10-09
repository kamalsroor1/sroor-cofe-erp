<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AppUpdateController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\CentralTenantResolverController;
use App\Http\Controllers\Api\CoffeeBlenderController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DailyJournalController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PermissionApiController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\PosQuickKeyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StorePosSettingsController;
use App\Http\Controllers\Api\SuperAdminApiController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SystemContextApiController;
use App\Http\Controllers\Api\TrashController;
use App\Http\Controllers\Api\TreasuryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminAppVersionController;
use App\Http\Controllers\Api\V1\SuperAdmin\TelescopeLinkController;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\DenyQuickLoginToken;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\EnsureQuickLoginAllowed;
use App\Http\Middleware\ResolveApiTenancy;
use App\Http\Middleware\ThrottleTenantMisses;
use Illuminate\Support\Facades\Route;

// IDEN-4.6: ThrottleTenantMisses caps unknown-tenant 404s per IP before tenancy resolves.
Route::prefix('v1')->middleware([ThrottleTenantMisses::class, ResolveApiTenancy::class])->group(function () {
    // 1. App Updates & Guest Endpoints
    // IDEN-4.6: every public route carries a named limiter (AppServiceProvider::registerRateLimiters).
    Route::get('/ping', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->timestamp]))->middleware('throttle:public-api')->name('api.ping');
    Route::get('/central/tenants/resolve', [CentralTenantResolverController::class, 'resolve'])->middleware('throttle:tenant-resolve')->name('api.central.tenants.resolve');
    Route::get('/app/version', [AppUpdateController::class, 'checkVersion'])->middleware('throttle:public-api')->name('api.app.version');
    Route::get('/app/check-update', [AppUpdateController::class, 'checkVersion'])->middleware('throttle:public-api')->name('api.app.check_update');
    Route::get('/app/download-apk', [AppUpdateController::class, 'downloadApk'])->middleware('throttle:public-api')->name('api.app.download_apk');
    Route::get('/app/download-latest-apk', [AppUpdateController::class, 'downloadApk'])->middleware('throttle:public-api')->name('api.app.download_latest_apk');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:tenant-login')->name('api.auth.login');
    Route::get('/auth/options', [AuthController::class, 'authOptions'])->middleware('throttle:public-api')->name('api.auth.options');

    // Quick login: TESTING ONLY. Registered only when the flag is on outside production;
    // EnsureQuickLoginAllowed re-checks at runtime (stale route cache, central host, prod).
    if (config('auth.quick_login.enabled') === true && ! app()->isProduction()) {
        Route::middleware([EnsureQuickLoginAllowed::class, 'throttle:quick-login'])->group(function () {
            Route::post('/auth/quick-login', [AuthController::class, 'quickLogin'])->name('api.auth.quick_login');
            Route::get('/auth/quick-login/users', [AuthController::class, 'quickLoginUsers'])->name('api.auth.quick_login.users');
        });
    }

    Route::get('/system/translations', [SystemContextApiController::class, 'translations'])->middleware('throttle:public-api')->name('api.system.translations');

    // 2. Protected Endpoints (Requires valid Bearer Token)
    Route::middleware(ApiTokenAuth::class)->group(function () {
        // Auth Profile & Logout
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        // System Context (Bootstrap payload for the SPA)
        Route::get('/system/context', [SystemContextApiController::class, 'context'])->name('api.system.context');

        // Permissions & Roles Tree
        Route::get('/permissions', [PermissionApiController::class, 'index'])->name('api.permissions.index');

        // High-Performance Consolidated Dashboard Summary
        Route::get('/dashboard', [DashboardApiController::class, 'index'])->name('api.dashboard.index');
        Route::get('/dashboard/summary', [DashboardApiController::class, 'index'])->name('api.dashboard.summary');

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

        // POSB-2: per-store POS settings (scale-label parser + max discount). settings.manage + store access.
        Route::middleware(DenyQuickLoginToken::class)->group(function () {
            Route::get('/stores/{store}/pos-settings', [StorePosSettingsController::class, 'show'])->whereNumber('store')->name('api.stores.pos_settings.show');
            Route::put('/stores/{store}/pos-settings', [StorePosSettingsController::class, 'update'])->whereNumber('store')->name('api.stores.pos_settings.update');
            // POSB-6: full replace of a store's POS quick keys. settings.manage + store access.
            Route::put('/stores/{store}/pos/quick-keys', [PosQuickKeyController::class, 'replace'])->whereNumber('store')->name('api.stores.pos_quick_keys.replace');
        });

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
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('api.purchases.index');
        Route::get('/purchases/smart-reorder', [PurchaseController::class, 'smartReorder'])->name('api.purchases.smart_reorder');
        Route::get('/purchases/{id}', [PurchaseController::class, 'show'])->name('api.purchases.show');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('api.purchases.store');
        Route::post('/purchases/{id}/cancel', [PurchaseController::class, 'cancel'])->name('api.purchases.cancel');

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

        // Categories Management
        Route::get('/categories', [CategoryApiController::class, 'index'])->name('api.categories.index');
        Route::post('/categories', [CategoryApiController::class, 'store'])->name('api.categories.store');
        Route::put('/categories/{id}', [CategoryApiController::class, 'update'])->name('api.categories.update');
        Route::delete('/categories/{id}', [CategoryApiController::class, 'destroy'])->name('api.categories.destroy');

        // POS & Sales Invoices & WhatsApp
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('api.invoices.index');
        Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->name('api.invoices.show');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('api.invoices.store');
        Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->name('api.invoices.cancel');

        // POS Fast Operations
        Route::get('/pos/bootstrap', [PosController::class, 'bootstrap'])->name('api.pos.bootstrap');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('api.pos.checkout');
        Route::post('/pos/quick-customer', [PosController::class, 'quickCustomer'])->name('api.pos.quick_customer');
        Route::get('/pos/last-price', [PosController::class, 'lastPrice'])->name('api.pos.last_price');
        // POSB-6: quick keys of the active store (pos.access + store access).
        Route::get('/pos/quick-keys', [PosQuickKeyController::class, 'index'])->name('api.pos.quick_keys.index');

        // Payments & Vouchers (Customer Receipts / Supplier Disbursements)
        Route::get('/payments', [PaymentController::class, 'index'])->name('api.payments.index');
        Route::post('/payments/customer-receipt', [PaymentController::class, 'customerReceipt'])->name('api.payments.customer_receipt');
        Route::post('/payments/supplier-voucher', [PaymentController::class, 'supplierVoucher'])->name('api.payments.supplier_voucher');

        // Cashier Shifts & Z-Report & Daily Journal
        Route::get('/shifts', [ShiftController::class, 'index'])->name('api.shifts.index');
        Route::get('/shifts/current', [ShiftController::class, 'current'])->name('api.shifts.current');
        // QA-2 / Q10 (CTO): opening and closing a shift requires daily_journal.close_shift.
        Route::post('/shifts/open', [ShiftController::class, 'open'])->name('api.shifts.open')->middleware('can:daily_journal.close_shift');
        Route::post('/shifts/close', [ShiftController::class, 'close'])->name('api.shifts.close')->middleware('can:daily_journal.close_shift');
        Route::get('/shifts/{id}/z-report', [ShiftController::class, 'zReport'])->name('api.shifts.z_report');
        Route::get('/daily-journal', [DailyJournalController::class, 'index'])->name('api.daily_journal.index');

        // Expenses & Petty Cash
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('api.expenses.index');
        Route::get('/expenses/{id}', [ExpenseController::class, 'show'])->name('api.expenses.show');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('api.expenses.store');
        Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->name('api.expenses.update');
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->name('api.expenses.destroy');

        // Treasury & Quick Financial Stats
        Route::get('/treasury/summary', [TreasuryController::class, 'summary'])->name('api.treasury.summary');

        // Profit & Loss Reports & Business Analytics
        Route::get('/reports/summary', [ReportController::class, 'summary'])->name('api.reports.summary');
        Route::get('/reports/comprehensive', [ReportController::class, 'comprehensive'])->name('api.reports.comprehensive');
        Route::get('/reports/items', [ReportController::class, 'items'])->name('api.reports.items');
        Route::get('/reports/stores', [ReportController::class, 'stores'])->name('api.reports.stores');
        Route::get('/reports/customers', [ReportController::class, 'customers'])->name('api.reports.customers');
        Route::get('/reports/expenses', [ReportController::class, 'expenses'])->name('api.reports.expenses');
        Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('api.reports.inventory');
        Route::get('/reports/treasury', [ReportController::class, 'treasury'])->name('api.reports.treasury');
        Route::get('/reports/top-items', [ReportController::class, 'topItems'])->name('api.reports.top_items');
        Route::get('/reports/items/{id}/card', [ReportController::class, 'itemCard'])->name('api.reports.item_card');

        // Returns (Sales & Purchase Returns)
        Route::get('/returns', [ReturnController::class, 'index'])->name('api.returns.index');
        Route::get('/returns/{id}', [ReturnController::class, 'show'])->name('api.returns.show');
        Route::post('/returns', [ReturnController::class, 'store'])->name('api.returns.store');
        Route::delete('/returns/{id}', [ReturnController::class, 'destroy'])->name('api.returns.destroy');

        // Stock Transfers between stores/branches
        Route::get('/transfers', [StockTransferController::class, 'index'])->name('api.transfers.index');
        Route::get('/transfers/{id}', [StockTransferController::class, 'show'])->name('api.transfers.show');
        Route::post('/transfers', [StockTransferController::class, 'store'])->name('api.transfers.store');
        Route::post('/transfers/{id}/cancel', [StockTransferController::class, 'cancel'])->name('api.transfers.cancel');

        // Coffee Blender Engine & Custom Roasting Studio
        Route::post('/coffee-blender/calculate', [CoffeeBlenderController::class, 'calculate'])->name('api.coffee_blender.calculate');
        Route::post('/coffee-blender/invoice', [CoffeeBlenderController::class, 'createInvoice'])->name('api.coffee_blender.invoice');

        // Administrative areas: never reachable with a testing-only quick-login token.
        Route::middleware(DenyQuickLoginToken::class)->group(function () {
            // Users & Employees Management
            Route::get('/users', [UserController::class, 'index'])->name('api.users.index');
            Route::get('/users/{id}', [UserController::class, 'show'])->name('api.users.show');
            Route::post('/users', [UserController::class, 'store'])->name('api.users.store');
            Route::put('/users/{id}', [UserController::class, 'update'])->name('api.users.update');
            Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('api.users.destroy');
            Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('api.users.toggle_active');

            // Roles & Permissions Matrix
            Route::get('/roles', [RoleController::class, 'index'])->name('api.roles.index');
            Route::put('/roles/{id}/permissions', [RoleController::class, 'updatePermissions'])->name('api.roles.update_permissions');
        });

        // Activity & Audit Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('api.activity_logs.index')->middleware('can:logs.view');
        Route::get('/activity-logs/export-csv', [ActivityLogController::class, 'exportCsv'])->name('api.activity_logs.export_csv')->middleware('can:logs.view');

        // User Profile & Preferences
        Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->name('api.profile.update');

        Route::middleware(DenyQuickLoginToken::class)->group(function () {
            // Admin Settings & Integrations
            Route::get('/settings', [SettingController::class, 'index'])->name('api.settings.index');
            Route::post('/settings', [SettingController::class, 'update'])->name('api.settings.update');
            Route::post('/settings/telegram/test', [SettingController::class, 'sendTestTelegram'])->name('api.settings.telegram_test');

            // Trash Bin (Soft-deleted records recovery)
            Route::get('/trash', [TrashController::class, 'index'])->name('api.trash.index');
            Route::post('/trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('api.trash.restore');
            Route::delete('/trash/{type}/{id}/force', [TrashController::class, 'forceDelete'])->name('api.trash.force_delete');
        });

    });
});

// Super Admin & Multi-Tenant Management (central context only, never tenant-initialised).
// EnsureCentralContext runs first: tenant host / tenancy => 404 before auth (guest => 404, not 401).
Route::prefix('v1/super-admin')->middleware([EnsureCentralContext::class, ApiTokenAuth::class, 'can:super_admin.access'])->group(function () {
    Route::get('/dashboard', [SuperAdminApiController::class, 'dashboard'])->name('api.super_admin.dashboard');
    Route::get('/tenants', [SuperAdminApiController::class, 'tenants'])->name('api.super_admin.tenants');
    Route::post('/tenants', [SuperAdminApiController::class, 'storeTenant'])->name('api.super_admin.tenants.store');
    Route::get('/tenants/{id}', [SuperAdminApiController::class, 'showTenant'])->name('api.super_admin.tenants.show');
    Route::delete('/tenants/{id}', [SuperAdminApiController::class, 'destroyTenant'])->name('api.super_admin.tenants.destroy');
    Route::post('/tenants/{id}/update-db-config', [SuperAdminApiController::class, 'updateDatabaseConfig'])->name('api.super_admin.tenants.update_db_config');
    Route::post('/tenants/{id}/toggle-status', [SuperAdminApiController::class, 'toggleStatus'])->name('api.super_admin.tenants.toggle_status');
    Route::post('/tenants/{id}/override-feature', [SuperAdminApiController::class, 'overrideFeature'])->name('api.super_admin.tenants.override_feature');
    Route::post('/tenants/{id}/update-units', [SuperAdminApiController::class, 'updateTenantUnits'])->name('api.super_admin.tenants.update_units');
    Route::post('/tenants/{id}/run-migrations', [SuperAdminApiController::class, 'runTenantMigrations'])->name('api.super_admin.tenants.run_migrations');
    Route::get('/plans', [SuperAdminApiController::class, 'plans'])->name('api.super_admin.plans');
    Route::put('/plans/{id}', [SuperAdminApiController::class, 'updatePlan'])->name('api.super_admin.plans.update');

    // Telescope: short-lived single-use signed link (replaces /telescope-access?token=)
    Route::post('/telescope-link', TelescopeLinkController::class)->middleware('throttle:10,1')->name('api.super_admin.telescope_link');

    // Central Platform Settings & Whitelabel & Units
    Route::get('/settings', [SuperAdminApiController::class, 'getPlatformSettings'])->name('api.super_admin.settings.get');
    Route::post('/settings', [SuperAdminApiController::class, 'updatePlatformSettings'])->name('api.super_admin.settings.update');
    Route::get('/units', [SuperAdminApiController::class, 'getUnits'])->name('api.super_admin.units.get');
    Route::post('/units', [SuperAdminApiController::class, 'updateUnits'])->name('api.super_admin.units.update');

    // App Versions & APK Releases Management
    Route::get('/app-versions', [SuperAdminAppVersionController::class, 'index'])->name('api.super_admin.app_versions.index');
    Route::post('/app-versions', [SuperAdminAppVersionController::class, 'store'])->name('api.super_admin.app_versions.store');
    Route::patch('/app-versions/{appVersion}/toggle-active', [SuperAdminAppVersionController::class, 'toggleActive'])->name('api.super_admin.app_versions.toggle_active');
    Route::delete('/app-versions/{appVersion}', [SuperAdminAppVersionController::class, 'destroy'])->name('api.super_admin.app_versions.destroy');
});

// Direct Central Workspace Resolver alias without v1 prefix
Route::get('/central/tenants/resolve', [CentralTenantResolverController::class, 'resolve'])->middleware('throttle:tenant-resolve')->name('api.central.tenants.resolve.alias');
