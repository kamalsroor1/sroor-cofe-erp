<?php

use App\Enums\ImportType;
use App\Enums\TenantLogoVariant;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AppUpdateController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\CentralTenantResolverController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DailyJournalController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\Landing\PublicLegalDocumentController;
use App\Http\Controllers\Api\Landing\PublicPlanController;
use App\Http\Controllers\Api\Landing\PublicSignupRequestController;
use App\Http\Controllers\Api\Landing\SelfSignupController;
use App\Http\Controllers\Api\MixController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PermissionApiController;
use App\Http\Controllers\Api\PlanChangeRequestController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\PosQuickKeyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportBreakdownController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\ReturnSourceController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ScreenLockController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\StockCountController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StorePosSettingsController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SystemContextApiController;
use App\Http\Controllers\Api\TenantBrandAssetController;
use App\Http\Controllers\Api\TenantLogoController;
use App\Http\Controllers\Api\TrashController;
use App\Http\Controllers\Api\TreasuryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\DenyQuickLoginToken;
use App\Http\Middleware\EnsureQuickLoginAllowed;
use App\Http\Middleware\EnsureSelfSignupEnabled;
use App\Http\Middleware\ResolveApiTenancy;
use App\Http\Middleware\ThrottleTenantMisses;
use Illuminate\Support\Facades\Route;

// PRD-05 / PRD-07 / PRD-08: the public marketing site. No authentication and NO tenancy (outside the
// ResolveApiTenancy group on purpose: central DB only, any host, X-Tenant ignored). Throttled.
Route::prefix('v1/public')->name('api.public.')->group(function () {
    Route::get('/plans', [PublicPlanController::class, 'index'])->middleware('throttle:public-api')->name('plans');
    Route::get('/legal/{document}', [PublicLegalDocumentController::class, 'show'])
        ->middleware('throttle:public-api')
        ->whereIn('document', ['terms', 'privacy', 'dpa'])
        ->name('legal.show');
    Route::post('/signup-requests', [PublicSignupRequestController::class, 'store'])->middleware('throttle:signup-requests')->name('signup_requests.store');
});

// SIGN-11: public self-signup with an emailed verification code. Central DB only, NO tenancy
// (outside the ResolveApiTenancy group on purpose, X-Tenant ignored), every route throttled.
// EnsureSelfSignupEnabled answers 503 while the platform switch is off.
Route::prefix('v1/signup')->name('api.signup.')->group(function () {
    Route::middleware(EnsureSelfSignupEnabled::class)->group(function () {
        Route::post('/', [SelfSignupController::class, 'store'])->middleware('throttle:self-signup')->name('store');
        Route::post('/verify', [SelfSignupController::class, 'verify'])->middleware('throttle:self-signup-verify')->name('verify');
        Route::post('/resend', [SelfSignupController::class, 'resend'])->middleware('throttle:self-signup-resend')->name('resend');
    });
    // SIGN-12: progress polling ("preparing your shop"); open even while signup is switched off.
    Route::get('/status', [SelfSignupController::class, 'status'])->middleware('throttle:self-signup-status')->name('status');
});

// IDEN-4.6: ThrottleTenantMisses caps unknown-tenant 404s per IP before tenancy resolves.
Route::prefix('v1')->middleware([ThrottleTenantMisses::class, ResolveApiTenancy::class])->group(function () {
    // 1. App Updates & Guest Endpoints
    // IDEN-4.6: every public route carries a named limiter (AppServiceProvider::registerRateLimiters).
    Route::get('/ping', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->timestamp]))->middleware('throttle:public-api')->name('api.ping');
    Route::get('/central/tenants/resolve', [CentralTenantResolverController::class, 'resolve'])->middleware('throttle:tenant-resolve')->name('api.central.tenants.resolve');
    Route::get('/app/version', [AppUpdateController::class, 'checkVersion'])->middleware('throttle:public-api')->name('api.app.version');
    Route::get('/app/check-update', [AppUpdateController::class, 'checkVersion'])->middleware('throttle:public-api')->name('api.app.check_update');
    Route::get('/app/download-apk', [AppUpdateController::class, 'downloadApk'])->middleware(['throttle:public-api', 'throttle:app-download'])->name('api.app.download_apk');
    Route::get('/app/download-latest-apk', [AppUpdateController::class, 'downloadApk'])->middleware(['throttle:public-api', 'throttle:app-download'])->name('api.app.download_latest_apk');
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

    // BRND-3: public branding (before login): platform brand + the resolved shop's public brand. Allowlist only.
    Route::get('/branding', [BrandingController::class, 'show'])->middleware('throttle:public-api')->name('api.branding');

    // 2. Protected Endpoints (Requires valid Bearer Token)
    Route::middleware(ApiTokenAuth::class)->group(function () {
        // Auth Profile & Logout
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        // SELL-01: own password change; the only write open to a user flagged must_change_password
        // (EnsurePasswordChanged, chained from ApiTokenAuth). Throttled like login.
        Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:tenant-password-change')->name('api.auth.change_password');

        // SET-02: dismiss the idle lock screen with the user's own password or POS PIN (throttled verifiers;
        // SEC-21 review: own named limiter, per tenant + user).
        Route::post('/auth/unlock-screen', [ScreenLockController::class, 'unlock'])->middleware('throttle:tenant-unlock-screen')->name('api.auth.unlock_screen');

        // System Context (Bootstrap payload for the SPA)
        Route::get('/system/context', [SystemContextApiController::class, 'context'])->name('api.system.context');
        Route::get('/system/setup-progress', [SystemContextApiController::class, 'setupProgress'])->name('api.system.setup-progress');

        // Permissions & Roles Tree
        Route::get('/permissions', [PermissionApiController::class, 'index'])->name('api.permissions.index');

        // STOR-2: `store.active` resolves + access-checks the branch (X-Store-Id) of every branch-scoped
        // route below; `store.active:concrete` (single-branch writes) also refuses `all` with 403.
        // High-Performance Consolidated Dashboard Summary
        Route::get('/dashboard', [DashboardApiController::class, 'index'])->name('api.dashboard.index')->middleware('store.active');
        Route::get('/dashboard/summary', [DashboardApiController::class, 'index'])->name('api.dashboard.summary')->middleware('store.active');

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
            // POS-10: the shift rule alone; settings.manage OR stores.manage + store access.
            Route::get('/stores/{store}/pos-settings/shift-rule', [StorePosSettingsController::class, 'showShiftRule'])->whereNumber('store')->name('api.stores.pos_settings.shift_rule.show');
            Route::put('/stores/{store}/pos-settings/shift-rule', [StorePosSettingsController::class, 'updateShiftRule'])->whereNumber('store')->name('api.stores.pos_settings.shift_rule.update');
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
        Route::post('/customers/{id}/collect-payment', [CustomerController::class, 'collectPayment'])->name('api.customers.collect_payment')->middleware('store.active:concrete')->middleware('feature:payments.manage');
        Route::get('/customers/{id}/statement', [CustomerController::class, 'statement'])->name('api.customers.statement');

        // Suppliers & Statements
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('api.suppliers.index');
        Route::get('/suppliers/{id}', [SupplierController::class, 'show'])->name('api.suppliers.show');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('api.suppliers.store');
        Route::put('/suppliers/{id}', [SupplierController::class, 'update'])->name('api.suppliers.update');
        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->name('api.suppliers.destroy');
        Route::patch('/suppliers/{id}/toggle-active', [SupplierController::class, 'toggleActive'])->name('api.suppliers.toggle_active');
        Route::post('/suppliers/{id}/pay', [SupplierController::class, 'pay'])->name('api.suppliers.pay')->middleware('store.active:concrete')->middleware('feature:payments.manage');
        Route::get('/suppliers/{id}/statement', [SupplierController::class, 'statement'])->name('api.suppliers.statement');
        // Purchases & Coffee Bean Inbound & Smart Reorder
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('api.purchases.index')->middleware('store.active')->middleware('feature:purchases.manage');
        Route::get('/purchases/smart-reorder', [PurchaseController::class, 'smartReorder'])->name('api.purchases.smart_reorder')->middleware('store.active')->middleware('feature:purchases.reorder');
        Route::get('/purchases/{id}', [PurchaseController::class, 'show'])->name('api.purchases.show')->middleware('store.active')->middleware('feature:purchases.manage');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('api.purchases.store')->middleware('store.active:concrete')->middleware('feature:purchases.manage');
        Route::post('/purchases/{id}/cancel', [PurchaseController::class, 'cancel'])->name('api.purchases.cancel')->middleware('store.active')->middleware('feature:purchases.manage');

        // Items & Stock by Branch & Low Stock Radar & Movements
        Route::get('/items', [ItemController::class, 'index'])->name('api.items.index');
        Route::get('/items/low-stock', [ItemController::class, 'lowStock'])->name('api.items.low_stock');
        Route::get('/items/{id}', [ItemController::class, 'show'])->name('api.items.show')->middleware('store.active');
        Route::post('/items', [ItemController::class, 'store'])->name('api.items.store')->middleware('feature:items.manage');
        Route::put('/items/{id}', [ItemController::class, 'update'])->name('api.items.update')->middleware('feature:items.manage');
        Route::delete('/items/{id}', [ItemController::class, 'destroy'])->name('api.items.destroy')->middleware('feature:items.manage');
        Route::patch('/items/{id}/toggle-active', [ItemController::class, 'toggleActive'])->name('api.items.toggle_active')->middleware('feature:items.manage');
        Route::post('/items/{id}/adjust-stock', [ItemController::class, 'adjustStock'])->name('api.items.adjust_stock')->middleware('feature:items.manage');
        Route::get('/items/{id}/movements', [ItemController::class, 'movements'])->name('api.items.movements')->middleware('store.active')->middleware('feature:items.movements');

        // Categories Management
        Route::get('/categories', [CategoryApiController::class, 'index'])->name('api.categories.index');
        Route::post('/categories', [CategoryApiController::class, 'store'])->name('api.categories.store');
        Route::put('/categories/{id}', [CategoryApiController::class, 'update'])->name('api.categories.update');
        Route::delete('/categories/{id}', [CategoryApiController::class, 'destroy'])->name('api.categories.destroy');

        // PRD-06: Excel/CSV import wizard. Permission per {type} is enforced in the Form Requests;
        // preview/commit need one concrete branch (opening stock lands in the X-Store-Id store).
        Route::prefix('imports/{type}')->where(['type' => ImportType::routePattern()])->middleware('throttle:imports')->group(function () {
            Route::get('/template', [ImportController::class, 'template'])->name('api.imports.template');
            Route::post('/errors', [ImportController::class, 'errors'])->name('api.imports.errors');
            Route::post('/preview', [ImportController::class, 'preview'])->name('api.imports.preview')->middleware('store.active:concrete');
            Route::post('/commit', [ImportController::class, 'commit'])->name('api.imports.commit')->middleware('store.active:concrete');
        });

        // POS & Sales Invoices & WhatsApp
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('api.invoices.index')->middleware('store.active');
        Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('api.invoices.export')->middleware(['store.active', 'feature:reports.export', 'throttle:exports']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->name('api.invoices.show')->middleware('store.active');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('api.invoices.store')->middleware('store.active:concrete')->middleware('feature:invoices.create');
        Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->name('api.invoices.cancel')->middleware('store.active');

        // POS Fast Operations
        Route::get('/pos/bootstrap', [PosController::class, 'bootstrap'])->name('api.pos.bootstrap')->middleware('store.active')->middleware('feature:pos.access');
        Route::get('/pos/invoice-limit', [PosController::class, 'invoiceLimit'])->name('api.pos.invoice_limit')->middleware('feature:pos.access');
        Route::get('/pos/items', [PosController::class, 'items'])->name('api.pos.items')->middleware('store.active')->middleware('feature:pos.access');
        Route::get('/pos/lookup', [PosController::class, 'lookup'])->name('api.pos.lookup')->middleware('store.active')->middleware('feature:pos.access');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('api.pos.checkout')->middleware('store.active:concrete')->middleware('feature:pos.access');
        Route::post('/pos/quick-customer', [PosController::class, 'quickCustomer'])->name('api.pos.quick_customer')->middleware('feature:pos.access');
        Route::get('/pos/last-price', [PosController::class, 'lastPrice'])->name('api.pos.last_price')->middleware('store.active')->middleware('feature:pos.access');
        // POSB-6: quick keys of the active store (pos.access + store access).
        Route::get('/pos/quick-keys', [PosQuickKeyController::class, 'index'])->name('api.pos.quick_keys.index')->middleware('store.active')->middleware('feature:pos.access');

        // Payments & Vouchers (Customer Receipts / Supplier Disbursements)
        Route::get('/payments', [PaymentController::class, 'index'])->name('api.payments.index')->middleware('store.active')->middleware('feature:payments.manage');
        Route::post('/payments/customer-receipt', [PaymentController::class, 'customerReceipt'])->name('api.payments.customer_receipt')->middleware('store.active:concrete')->middleware('feature:payments.manage');
        Route::post('/payments/supplier-voucher', [PaymentController::class, 'supplierVoucher'])->name('api.payments.supplier_voucher')->middleware('store.active:concrete')->middleware('feature:payments.manage');

        // Cashier Shifts & Z-Report & Daily Journal
        Route::get('/shifts', [ShiftController::class, 'index'])->name('api.shifts.index')->middleware('store.active')->middleware('feature:shifts.manage');
        Route::get('/shifts/current', [ShiftController::class, 'current'])->name('api.shifts.current')->middleware('store.active')->middleware('feature:shifts.manage');
        // QA-2 / Q10 (CTO): opening and closing a shift requires daily_journal.close_shift.
        Route::post('/shifts/open', [ShiftController::class, 'open'])->name('api.shifts.open')->middleware('store.active:concrete')->middleware('can:daily_journal.close_shift')->middleware('feature:shifts.manage');
        Route::post('/shifts/close', [ShiftController::class, 'close'])->name('api.shifts.close')->middleware('store.active:concrete')->middleware('can:daily_journal.close_shift')->middleware('feature:shifts.manage');
        Route::get('/shifts/{id}/z-report', [ShiftController::class, 'zReport'])->name('api.shifts.z_report')->middleware('store.active')->middleware('feature:shifts.manage');
        Route::get('/daily-journal', [DailyJournalController::class, 'index'])->name('api.daily_journal.index')->middleware('store.active');

        // Expenses & Petty Cash
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('api.expenses.index')->middleware('store.active')->middleware('feature:expenses.manage');
        Route::get('/expenses/{id}', [ExpenseController::class, 'show'])->name('api.expenses.show')->middleware('store.active')->middleware('feature:expenses.manage');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('api.expenses.store')->middleware('store.active:concrete')->middleware('feature:expenses.manage');
        Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->name('api.expenses.update')->middleware('store.active')->middleware('feature:expenses.manage');
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->name('api.expenses.destroy')->middleware('store.active')->middleware('feature:expenses.manage');

        // Treasury & Quick Financial Stats
        Route::get('/treasury/summary', [TreasuryController::class, 'summary'])->name('api.treasury.summary')->middleware('store.active')->middleware('feature:treasury.view');

        // Profit & Loss Reports & Business Analytics
        Route::get('/reports/summary', [ReportController::class, 'summary'])->name('api.reports.summary')->middleware('store.active')->middleware('feature:reports.basic');
        Route::get('/reports/comprehensive', [ReportController::class, 'comprehensive'])->name('api.reports.comprehensive')->middleware('store.active')->middleware('feature:reports.basic');
        // Owner decision 2026-11-15: item profitability and the branch comparison are Pro reports
        // (reports.advanced: Pro / Enterprise plans or the control bundle add-on).
        Route::get('/reports/items', [ReportController::class, 'items'])->name('api.reports.items')->middleware('store.active')->middleware('feature:reports.basic')->middleware('feature:reports.advanced');
        Route::get('/reports/stores', [ReportController::class, 'stores'])->name('api.reports.stores')->middleware('store.active')->middleware('feature:reports.basic')->middleware('feature:reports.advanced');
        Route::get('/reports/customers', [ReportController::class, 'customers'])->name('api.reports.customers')->middleware('store.active')->middleware('feature:reports.basic');
        Route::get('/reports/expenses', [ReportController::class, 'expenses'])->name('api.reports.expenses')->middleware('store.active')->middleware('feature:reports.basic');
        Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('api.reports.inventory')->middleware('store.active')->middleware('feature:reports.basic');
        Route::get('/reports/treasury', [ReportController::class, 'treasury'])->name('api.reports.treasury')->middleware('store.active')->middleware('feature:reports.basic');
        Route::get('/reports/top-items', [ReportController::class, 'topItems'])->name('api.reports.top_items')->middleware('store.active')->middleware('feature:reports.basic');
        Route::get('/reports/stock-drift', [ReportController::class, 'stockDrift'])->name('api.reports.stock_drift')->middleware('store.active')->middleware('feature:reports.basic');
        Route::get('/reports/items/{id}/card', [ReportController::class, 'itemCard'])->name('api.reports.item_card')->middleware('store.active')->middleware('feature:reports.basic');
        // SHOP-05: sales by cashier / category, customer debt aging, supplier payables (+ CSV), and
        // the CSV of the P&L summary, top items and item profitability.
        Route::middleware(['store.active', 'feature:reports.basic'])->group(function () {
            Route::get('/reports/sales-by-cashier', [ReportBreakdownController::class, 'salesByCashier'])->name('api.reports.sales_by_cashier');
            Route::get('/reports/sales-by-category', [ReportBreakdownController::class, 'salesByCategory'])->name('api.reports.sales_by_category');
            Route::get('/reports/customer-aging', [ReportBreakdownController::class, 'customerAging'])->name('api.reports.customer_aging');
            Route::get('/reports/supplier-payables', [ReportBreakdownController::class, 'supplierPayables'])->name('api.reports.supplier_payables');
            // reports.export: the plan feature of every CSV export (the free plan has none), as /invoices/export.
            Route::middleware(['feature:reports.export', 'throttle:exports'])->group(function () {
                Route::get('/reports/summary/export', [ReportExportController::class, 'summary'])->name('api.reports.summary.export');
                Route::get('/reports/top-items/export', [ReportExportController::class, 'topItems'])->name('api.reports.top_items.export');
                Route::get('/reports/items/export', [ReportExportController::class, 'items'])->name('api.reports.items.export')->middleware('feature:reports.advanced');
                Route::get('/reports/sales-by-cashier/export', [ReportBreakdownController::class, 'exportSalesByCashier'])->name('api.reports.sales_by_cashier.export');
                Route::get('/reports/sales-by-category/export', [ReportBreakdownController::class, 'exportSalesByCategory'])->name('api.reports.sales_by_category.export');
                Route::get('/reports/customer-aging/export', [ReportBreakdownController::class, 'exportCustomerAging'])->name('api.reports.customer_aging.export');
                Route::get('/reports/supplier-payables/export', [ReportBreakdownController::class, 'exportSupplierPayables'])->name('api.reports.supplier_payables.export');
            });
        });

        // Returns (Sales & Purchase Returns)
        Route::get('/returns', [ReturnController::class, 'index'])->name('api.returns.index')->middleware('store.active')->middleware('feature:returns.manage');
        Route::get('/returns/{id}', [ReturnController::class, 'show'])->name('api.returns.show')->middleware('store.active')->middleware('feature:returns.manage');
        Route::post('/returns', [ReturnController::class, 'store'])->name('api.returns.store')->middleware('store.active:concrete')->middleware('feature:returns.manage');
        Route::delete('/returns/{id}', [ReturnController::class, 'destroy'])->name('api.returns.destroy')->middleware('store.active')->middleware('feature:returns.manage');
        // BE-01: source invoices/purchases (+ returnable lines) of the active store to book a return against.
        Route::get('/return-sources/{type}', [ReturnSourceController::class, 'index'])->name('api.return_sources.index')->middleware('store.active:concrete')->middleware('feature:returns.manage');
        Route::get('/return-sources/{type}/{id}', [ReturnSourceController::class, 'show'])->whereNumber('id')->name('api.return_sources.show')->middleware('store.active:concrete')->middleware('feature:returns.manage');

        // Stock Transfers between stores/branches
        Route::get('/transfers', [StockTransferController::class, 'index'])->name('api.transfers.index')->middleware('store.active')->middleware('feature:transfers.manage');
        Route::get('/transfers/{id}', [StockTransferController::class, 'show'])->name('api.transfers.show')->middleware('store.active')->middleware('feature:transfers.manage');
        Route::post('/transfers', [StockTransferController::class, 'store'])->name('api.transfers.store')->middleware('store.active:concrete')->middleware('feature:transfers.manage');
        Route::post('/transfers/{id}/cancel', [StockTransferController::class, 'cancel'])->name('api.transfers.cancel')->middleware('store.active')->middleware('feature:transfers.manage');

        // SHOP-02: stocktake sessions. Every plan (no plan feature); permission `stock.count` in the Form Requests.
        Route::get('/stock-counts', [StockCountController::class, 'index'])->name('api.stock_counts.index')->middleware('store.active');
        Route::post('/stock-counts', [StockCountController::class, 'store'])->name('api.stock_counts.store')->middleware('store.active:concrete');
        Route::get('/stock-counts/{id}', [StockCountController::class, 'show'])->whereNumber('id')->name('api.stock_counts.show')->middleware('store.active');
        Route::get('/stock-counts/{id}/variance', [StockCountController::class, 'variance'])->whereNumber('id')->name('api.stock_counts.variance')->middleware('store.active');
        Route::put('/stock-counts/{id}/lines', [StockCountController::class, 'saveLines'])->whereNumber('id')->name('api.stock_counts.lines')->middleware('store.active');
        Route::post('/stock-counts/{id}/post', [StockCountController::class, 'post'])->whereNumber('id')->name('api.stock_counts.post')->middleware('store.active');
        Route::post('/stock-counts/{id}/cancel', [StockCountController::class, 'cancel'])->whereNumber('id')->name('api.stock_counts.cancel')->middleware('store.active');

        // Recipes / product assembly (MIX-01). `/mixes/*` is canonical; `/coffee-blender/*` is a
        // deprecated alias kept for old Android/desktop builds (same controller, same behaviour).
        Route::post('/mixes/calculate', [MixController::class, 'calculate'])->name('api.mixes.calculate')->middleware('feature:mixes.manage');
        Route::post('/mixes/invoice', [MixController::class, 'createInvoice'])->name('api.mixes.invoice')->middleware('store.active:concrete')->middleware('feature:mixes.manage');
        // @deprecated alias of /mixes/* - remove once old app builds are retired
        Route::post('/coffee-blender/calculate', [MixController::class, 'calculate'])->name('api.coffee_blender.calculate')->middleware('feature:mixes.manage');
        Route::post('/coffee-blender/invoice', [MixController::class, 'createInvoice'])->name('api.coffee_blender.invoice')->middleware('store.active:concrete')->middleware('feature:mixes.manage');

        // Administrative areas: never reachable with a testing-only quick-login token.
        Route::middleware(DenyQuickLoginToken::class)->group(function () {
            // Users & Employees Management
            Route::get('/users', [UserController::class, 'index'])->name('api.users.index');
            Route::get('/users/{id}', [UserController::class, 'show'])->name('api.users.show');
            Route::post('/users', [UserController::class, 'store'])->name('api.users.store');
            Route::put('/users/{id}', [UserController::class, 'update'])->name('api.users.update');
            Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('api.users.destroy');
            Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('api.users.toggle_active');
            // SEC-05: lift a sign-in lockout.
            Route::post('/users/{id}/unlock-login', [UserController::class, 'unlockLogin'])->whereNumber('id')->name('api.users.unlock_login')->middleware('throttle:20,1');
            // BE-08: manager approval PIN for below-minimum-price sales.
            Route::put('/users/{id}/pos-pin', [UserController::class, 'updatePosPin'])->whereNumber('id')->name('api.users.pos_pin')->middleware('throttle:20,1');
            // SELL-01: temporary password shown once; the user must change it at the next sign-in.
            Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->whereNumber('id')->name('api.users.reset_password')->middleware('throttle:20,1');

            // Roles & Permissions Matrix
            Route::get('/roles', [RoleController::class, 'index'])->name('api.roles.index');
            Route::put('/roles/{id}/permissions', [RoleController::class, 'updatePermissions'])->name('api.roles.update_permissions');
        });

        // PRD-04 / ENTI-3.3: the shop's own subscription & billing. billing.manage on every route; the two
        // POSTs stay open to a read-only / past-due shop (EnsureTenantActive::WRITE_ALLOWLIST) so it can pay.
        Route::middleware([DenyQuickLoginToken::class, 'can:billing.manage'])->prefix('billing')->group(function () {
            Route::get('/subscription', [BillingController::class, 'subscription'])->name('api.billing.subscription');
            Route::get('/invoices', [BillingController::class, 'invoices'])->name('api.billing.invoices.index');
            Route::get('/invoices/{id}', [BillingController::class, 'showInvoice'])->whereNumber('id')->name('api.billing.invoices.show');
            Route::post('/invoices/{id}/payments', [BillingController::class, 'submitPayment'])
                ->whereNumber('id')
                ->middleware('throttle:billing-receipt-upload')
                ->name('api.billing.invoices.payments.store');
            Route::post('/payments/{id}/cancel', [BillingController::class, 'cancelPayment'])->whereNumber('id')
                ->name('api.billing.payments.cancel');
            Route::post('/renewal-invoice', [BillingController::class, 'renewalInvoice'])->middleware('throttle:billing-renewal-invoice')
                ->name('api.billing.renewal_invoice');
            // Plan change / add-on requests go through support (owner decision); both POSTs stay open to a read-only shop.
            Route::get('/plan-change-requests', [PlanChangeRequestController::class, 'index'])->name('api.billing.plan_change_requests.index');
            Route::post('/plan-change-requests', [PlanChangeRequestController::class, 'store'])->middleware('throttle:billing-plan-change-request')
                ->name('api.billing.plan_change_requests.store');
            Route::post('/plan-change-requests/{id}/cancel', [PlanChangeRequestController::class, 'cancel'])->whereNumber('id')
                ->name('api.billing.plan_change_requests.cancel');
        });

        // Activity & Audit Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('api.activity_logs.index')->middleware('can:logs.view')->middleware('feature:audit.logs');
        Route::get('/activity-logs/export-csv', [ActivityLogController::class, 'exportCsv'])->name('api.activity_logs.export_csv')->middleware('can:logs.view')->middleware('feature:audit.logs');

        // User Profile & Preferences
        Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->name('api.profile.update');
        Route::put('/profile/pos-pin', [ProfileController::class, 'updatePosPin'])->name('api.profile.pos_pin')->middleware(DenyQuickLoginToken::class)->middleware('throttle:10,1');
        Route::put('/profile/locale', [ProfileController::class, 'updateLocale'])->name('api.profile.locale');

        Route::middleware(DenyQuickLoginToken::class)->group(function () {
            // Admin Settings & Integrations
            Route::get('/settings', [SettingController::class, 'index'])->name('api.settings.index');
            Route::post('/settings', [SettingController::class, 'update'])->name('api.settings.update');
            Route::post('/settings/telegram/test', [SettingController::class, 'sendTestTelegram'])->name('api.settings.telegram_test')->middleware('feature:telegram.notifications');

            // BRND-5: shop logo upload/removal (sanitized image, tenant disk). settings.manage.
            Route::post('/settings/branding/logo/{variant}', [TenantBrandAssetController::class, 'store'])
                ->whereIn('variant', TenantLogoVariant::values())
                ->middleware('can:settings.manage')
                ->name('api.settings.branding.logo.store');
            Route::delete('/settings/branding/logo/{variant}', [TenantBrandAssetController::class, 'destroy'])
                ->whereIn('variant', TenantLogoVariant::values())
                ->middleware('can:settings.manage')
                ->name('api.settings.branding.logo.destroy');

            // Trash Bin (Soft-deleted records recovery)
            Route::get('/trash', [TrashController::class, 'index'])->name('api.trash.index');
            Route::post('/trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('api.trash.restore');
            Route::delete('/trash/{type}/{id}/force', [TrashController::class, 'forceDelete'])->name('api.trash.force_delete');
        });

    });
});

// BRND-5: the shop logo, public (login screen, manifest) and bound to the tenant of the request
// HOST only (TenantHostResolver ignores X-Tenant / ?tenant=; central or admin host = 404). Its own
// group OUTSIDE ResolveApiTenancy so no header can select the shop; ThrottleTenantMisses still
// caps unknown-host probes and public-api caps each client.
Route::prefix('v1')->middleware([ThrottleTenantMisses::class, 'throttle:public-api'])->group(function () {
    Route::get('/branding/logo/{variant}', [TenantLogoController::class, 'show'])
        ->whereIn('variant', TenantLogoVariant::values())
        ->name('api.branding.logo');
});

// IDEN-1.4: the platform console (control plane) lives in routes/central.php only.

// Direct Central Workspace Resolver alias without v1 prefix. Security audit (W2 lane 3I): same
// stack as the v1 resolver: ThrottleTenantMisses + ResolveApiTenancy (admin host => 404, never
// initialises tenancy: listed in ResolveApiTenancy::PUBLIC_CENTRAL_ROUTES) + tenant-resolve.
Route::get('/central/tenants/resolve', [CentralTenantResolverController::class, 'resolve'])
    ->middleware([ThrottleTenantMisses::class, ResolveApiTenancy::class, 'throttle:tenant-resolve'])
    ->name('api.central.tenants.resolve.alias');
