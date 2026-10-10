import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_STORAGE_KEYS, isCentralAppContext, setCentralSessionHandlers } from '../Services/centralApi';
import { safeCentralRedirect } from '../Composables/useSuperAdminLogin';
import { trans } from '../helpers/trans';

/**
 * Two disjoint route tables (IDEN-1.9). The server decides the mode:
 * <meta name="app-context" content="central"> is rendered on the admin host only.
 *  - tenant mode: the ERP / POS routes; no /super-admin route exists (=> redirect to /);
 *  - central mode: only the platform console and its own sign-in; no tenant route exists.
 */
const CENTRAL_MODE = isCentralAppContext();

const tenantRoutes = [
    {
        path: '/connect',
        alias: '/workspace',
        name: 'workspace.connect',
        component: () => import('../views/Auth/WorkspaceConnectView.vue'),
        meta: {
            title: 'الاتصال ببيئة العمل',
            guestOnly: true,
        },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/Auth/LoginView.vue'),
        meta: {
            title: 'تسجيل الدخول',
            guestOnly: true,
        },
    },
    {
        path: '/',
        alias: '/dashboard',
        name: 'dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: {
            title: 'لوحة التحكم الرئيسية',
            requiresAuth: true,
        },
    },
    {
        path: '/stores',
        name: 'stores.index',
        component: () => import('../views/Stores/StoresView.vue'),
        meta: {
            title: 'إدارة الفروع والمخازن',
            requiresAuth: true,
            permission: 'stores.manage',
        },
    },
    {
        path: '/stores/stocks',
        name: 'stores.stocks',
        component: () => import('../views/Stores/StoreStocksView.vue'),
        meta: {
            title: 'أرصدة الفروع والمخازن',
            requiresAuth: true,
        },
    },
    {
        path: '/customers',
        name: 'customers.index',
        component: () => import('../views/Customers/CustomersView.vue'),
        meta: {
            title: 'العملاء وكشوف الحساب',
            requiresAuth: true,
            permission: 'customers.manage',
        },
    },
    {
        path: '/customers/:id/statement',
        name: 'customers.statement',
        component: () => import('../views/Customers/CustomerStatementView.vue'),
        meta: {
            title: 'كشف حساب عميل',
            requiresAuth: true,
            permission: 'customers.manage',
        },
    },
    {
        path: '/suppliers',
        name: 'suppliers.index',
        component: () => import('../views/Suppliers/SuppliersView.vue'),
        meta: {
            title: 'الموردين وكشوف الحساب',
            requiresAuth: true,
            permission: 'suppliers.manage',
        },
    },
    {
        path: '/suppliers/:id/statement',
        name: 'suppliers.statement',
        component: () => import('../views/Suppliers/SupplierStatementView.vue'),
        meta: {
            title: 'كشف حساب مورد',
            requiresAuth: true,
            permission: 'suppliers.manage',
        },
    },
    {
        path: '/expenses',
        name: 'expenses.index',
        component: () => import('../views/Expenses/ExpensesView.vue'),
        meta: {
            title: 'المصروفات والعهد',
            requiresAuth: true,
            permission: 'expenses.manage',
        },
    },
    {
        path: '/items',
        name: 'items.index',
        component: () => import('../views/Items/ItemsView.vue'),
        meta: {
            title: 'الأصناف والمخزون',
            requiresAuth: true,
            permission: 'items.view',
        },
    },
    {
        path: '/categories',
        name: 'categories.index',
        component: () => import('../views/Items/CategoriesView.vue'),
        meta: {
            title: 'فئات الأصناف',
            requiresAuth: true,
            permission: 'items.view',
        },
    },
    {
        path: '/items/:id/movements',
        name: 'items.movements',
        component: () => import('../views/Items/ItemMovementsView.vue'),
        meta: {
            title: 'حركات مخزون الصنف',
            requiresAuth: true,
            permission: 'items.view',
        },
    },
    {
        path: '/daily-journal',
        name: 'daily_journal.index',
        component: () => import('../views/DailyJournal/DailyJournalView.vue'),
        meta: {
            title: 'دفتر اليومية والخزينة',
            requiresAuth: true,
            permission: 'daily_journal.view',
        },
    },
    {
        path: '/purchases',
        name: 'purchases.index',
        component: () => import('../views/Purchases/PurchasesView.vue'),
        meta: {
            title: 'المشتريات والتوريد',
            requiresAuth: true,
            permission: 'purchases.view',
        },
    },
    {
        path: '/purchases/create',
        name: 'purchases.create',
        component: () => import('../views/Purchases/CreatePurchaseView.vue'),
        meta: {
            title: 'فاتورة مشتريات جديدة',
            requiresAuth: true,
            permission: 'purchases.create',
        },
    },
    {
        path: '/purchases/smart-reorder',
        alias: '/smart-reorder',
        name: 'purchases.smart_reorder',
        component: () => import('../views/Purchases/SmartReorderView.vue'),
        meta: {
            title: 'رادار إعادة الطلب الذكي',
            requiresAuth: true,
            permission: 'purchases.view',
        },
    },
    {
        path: '/invoices',
        name: 'invoices.index',
        component: () => import('../views/Invoices/InvoicesView.vue'),
        meta: {
            title: 'فواتير المبيعات',
            requiresAuth: true,
            permission: 'invoices.view',
        },
    },
    {
        path: '/invoices/:id',
        name: 'invoices.show',
        component: () => import('../views/Invoices/InvoiceShowView.vue'),
        meta: {
            title: 'معاينة الفاتورة والطباعة',
            requiresAuth: true,
            permission: 'invoices.view',
        },
    },
    {
        path: '/invoices/:id/print',
        name: 'invoices.print',
        component: () => import('../views/Invoices/InvoicePrintView.vue'),
        meta: {
            title: 'طباعة الفاتورة',
            requiresAuth: true,
            layout: 'blank',
            isPrintView: true,
            permission: 'invoices.view',
        },
    },
    {
        path: '/pos',
        name: 'pos.index',
        component: () => import('../views/POS/PosView.vue'),
        meta: {
            title: 'نقطة البيع السريعة (POS)',
            requiresAuth: true,
            permission: 'pos.access',
        },
    },
    {
        path: '/returns',
        name: 'returns.index',
        component: () => import('../views/Returns/ReturnsView.vue'),
        meta: {
            title: 'مرتجعات المبيعات والمشتريات',
            requiresAuth: true,
            permission: 'returns.manage',
        },
    },
    {
        path: '/returns/create',
        name: 'returns.create',
        component: () => import('../views/Returns/CreateReturnView.vue'),
        meta: {
            title: 'تسجيل مرتجع جديد',
            requiresAuth: true,
            permission: 'returns.manage',
        },
    },
    {
        path: '/stock-transfers',
        name: 'stock_transfers.index',
        component: () => import('../views/StockTransfers/StockTransfersView.vue'),
        meta: {
            title: 'التحويلات المخزنية',
            requiresAuth: true,
            permission: 'transfers.view',
        },
    },
    {
        path: '/stock-transfers/create',
        name: 'stock_transfers.create',
        component: () => import('../views/StockTransfers/CreateStockTransferView.vue'),
        meta: {
            title: 'إذن تحويل مخزني جديد',
            requiresAuth: true,
            permission: 'stores.manage',
        },
    },
    {
        path: '/coffee-blender',
        name: 'coffee_blender.index',
        component: () => import('../views/CoffeeBlender/CoffeeBlenderView.vue'),
        meta: {
            title: 'معمل تركيب وتجميع المنتجات',
            requiresAuth: true,
            permission: 'items.create',
        },
    },
    {
        path: '/reports',
        name: 'reports.index',
        component: () => import('../views/Reports/ReportsView.vue'),
        meta: {
            title: 'التقارير المالية والأرباح',
            requiresAuth: true,
            permission: 'reports.view',
        },
    },
    {
        path: '/users',
        name: 'users.index',
        component: () => import('../views/Users/UsersView.vue'),
        meta: {
            title: 'إدارة المستخدمين والموظفين',
            requiresAuth: true,
            permission: 'roles.manage',
        },
    },
    {
        path: '/roles',
        name: 'roles.index',
        component: () => import('../views/Roles/RolesView.vue'),
        meta: {
            title: 'مصفوفة الصلاحيات والأدوار',
            requiresAuth: true,
            permission: 'roles.manage',
        },
    },
    {
        path: '/activity-logs',
        name: 'activity-logs.index',
        component: () => import('../views/ActivityLogs/ActivityLogsView.vue'),
        meta: {
            title: 'سجل التدقيق الأمني والنشاطات',
            requiresAuth: true,
            permission: 'logs.view',
        },
    },
    {
        path: '/settings',
        name: 'settings.index',
        component: () => import('../views/Settings/SettingsView.vue'),
        meta: {
            title: 'إعدادات النظام والمؤسسة',
            requiresAuth: true,
            permission: 'roles.manage',
        },
    },
    {
        path: '/profile',
        name: 'profile.show',
        component: () => import('../views/Profile/ProfileView.vue'),
        meta: {
            title: 'الملف الشخصي والحساب',
            requiresAuth: true,
        },
    },
    {
        path: '/trash',
        name: 'trash.index',
        component: () => import('../views/Trash/TrashView.vue'),
        meta: {
            title: 'سلة المحذوفات',
            requiresAuth: true,
            permission: 'trash.access',
        },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        redirect: '/',
    },
];

const centralGuest = (path, name, titleKey, loader) => ({
    path,
    name,
    component: loader,
    meta: { titleKey, guestOnly: true, centralGuest: true },
});

const centralPage = (path, name, titleKey, loader) => ({
    path,
    name,
    component: loader,
    meta: { titleKey, requiresCentralAuth: true },
});

const centralRoutes = [
    centralGuest(
        '/super-admin/login',
        'super_admin.login',
        'super.page_titles.login',
        () => import('../views/SuperAdmin/SuperAdminLoginView.vue')
    ),
    centralGuest(
        '/super-admin/forgot-password',
        'super_admin.forgot_password',
        'super.page_titles.forgot_password',
        () => import('../views/SuperAdmin/SuperAdminForgotPasswordView.vue')
    ),
    centralGuest(
        '/super-admin/reset-password',
        'super_admin.reset_password',
        'super.page_titles.reset_password',
        () => import('../views/SuperAdmin/SuperAdminResetPasswordView.vue')
    ),
    centralPage(
        '/super-admin/dashboard',
        'super_admin.dashboard',
        'super.page_titles.dashboard',
        () => import('../views/SuperAdmin/SuperAdminDashboardView.vue')
    ),
    centralPage(
        '/super-admin/tenants',
        'super_admin.tenants',
        'super.page_titles.tenants',
        () => import('../views/SuperAdmin/SuperAdminTenantsView.vue')
    ),
    centralPage(
        '/super-admin/tenants/:id',
        'super_admin.tenants.show',
        'super.page_titles.tenant_show',
        () => import('../views/SuperAdmin/SuperAdminTenantShowView.vue')
    ),
    centralPage(
        '/super-admin/plans',
        'super_admin.plans',
        'super.page_titles.plans',
        () => import('../views/SuperAdmin/SuperAdminPlansView.vue')
    ),
    centralPage(
        '/super-admin/app-versions',
        'super_admin.app_versions',
        'super.page_titles.app_versions',
        () => import('../views/SuperAdmin/SuperAdminAppVersionsView.vue')
    ),
    centralPage(
        '/super-admin/units',
        'super_admin.units',
        'super.page_titles.units',
        () => import('../views/SuperAdmin/SuperAdminUnitsView.vue')
    ),
    { path: '/:pathMatch(.*)*', name: 'super_admin.not_found', redirect: '/super-admin/dashboard' },
];

const router = createRouter({
    history: createWebHistory(),
    routes: CENTRAL_MODE ? centralRoutes : tenantRoutes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        } else {
            return { top: 0, behavior: 'smooth' };
        }
    },
});

// Tenant navigation guard
async function tenantGuard(to, from, next) {
    const authStore = useAuthStore();
    const appConfigStore = useAppConfigStore();

    // 1. If user is marked authenticated, ensure profile and system context load cleanly
    if (authStore.isAuthenticated && !appConfigStore.isLoaded) {
        try {
            await appConfigStore.fetchBootstrapContext();
            window.spaTranslations = appConfigStore.translations;
        } catch (e) {
            console.warn('Session expired or bootstrap context failed, clearing auth:', e);
            authStore.clearSession();
            appConfigStore.isLoaded = false;
            return next({ name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : undefined });
        }
    }

    // 2. Set document title
    const appName = appConfigStore.platformName || 'منظومة ERP';
    document.title = to.meta.title ? `${to.meta.title} - ${appName}` : appName;

    // 3. Guest-only check (e.g. Login page)
    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return next({ name: 'dashboard' });
    }

    // 4. Requires Auth check
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } });
    }

    // 5. Permission / Role Check
    if (to.meta.permission && !authStore.hasPermission(to.meta.permission)) {
        return next({ name: 'dashboard' });
    }

    if (to.meta.role && !authStore.hasRole(to.meta.role)) {
        return next({ name: 'dashboard' });
    }

    next();
}

// Platform console navigation guard (central mode)
async function centralGuard(to) {
    const centralAuth = useCentralAuthStore();

    document.title = to.meta.titleKey
        ? `${trans(to.meta.titleKey)} - ${trans('super.platform_title')}`
        : trans('super.platform_title');

    if (centralAuth.token && centralAuth.isSessionExpired()) {
        await centralAuth.logout('expired');
    }

    let authenticated = false;
    if (centralAuth.isAuthenticated) {
        try {
            authenticated = await centralAuth.ensureSession();
        } catch {
            // Network / server error: keep the stored session; the page shows its own error state.
            authenticated = centralAuth.isAuthenticated;
        }
    }

    if (to.meta.centralGuest) {
        return authenticated ? { name: 'super_admin.dashboard' } : true;
    }

    if (to.meta.requiresCentralAuth && !authenticated) {
        return { name: 'super_admin.login', query: { redirect: safeCentralRedirect(to.fullPath) } };
    }

    return true;
}

function installCentralSessionHandlers() {
    const sendToLogin = () => {
        const current = router.currentRoute.value;
        if (current.meta?.requiresCentralAuth) {
            router.replace({ name: 'super_admin.login', query: { redirect: safeCentralRedirect(current.fullPath) } });
        }
    };

    setCentralSessionHandlers({
        onUnauthenticated: () => {
            const centralAuth = useCentralAuthStore();
            const hadSession = centralAuth.isAuthenticated;
            centralAuth.clearSession();
            if (hadSession) centralAuth.notice = 'expired';
            sendToLogin();
        },
        onStepUpRequired: () => useCentralAuthStore().requestStepUp(),
    });

    // Signing out in one tab signs out every tab of the console.
    window.addEventListener('storage', (event) => {
        if (event.key !== CENTRAL_STORAGE_KEYS.token || event.newValue) return;
        const centralAuth = useCentralAuthStore();
        if (!centralAuth.token) return;
        centralAuth.clearSession();
        sendToLogin();
    });
}

if (CENTRAL_MODE) {
    installCentralSessionHandlers();
    router.beforeEach(centralGuard);
} else {
    router.beforeEach(tenantGuard);
}

export default router;
