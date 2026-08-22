import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';

const routes = [
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
            permission: 'items.manage',
        },
    },
    {
        path: '/items/:id/movements',
        name: 'items.movements',
        component: () => import('../views/Items/ItemMovementsView.vue'),
        meta: {
            title: 'حركات مخزون الصنف',
            requiresAuth: true,
            permission: 'items.manage',
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
            permission: 'returns.view',
        },
    },
    {
        path: '/returns/create',
        name: 'returns.create',
        component: () => import('../views/Returns/CreateReturnView.vue'),
        meta: {
            title: 'تسجيل مرتجع جديد',
            requiresAuth: true,
            permission: 'returns.create',
        },
    },
    {
        path: '/stock-transfers',
        name: 'stock_transfers.index',
        component: () => import('../views/StockTransfers/StockTransfersView.vue'),
        meta: {
            title: 'التحويلات المخزنية',
            requiresAuth: true,
            permission: 'stores.view',
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
            permission: 'users.manage',
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
        path: '/super-admin/dashboard',
        name: 'super_admin.dashboard',
        component: () => import('../views/SuperAdmin/SuperAdminDashboardView.vue'),
        meta: {
            title: 'لوحة تحكم السوبر أدمن',
            requiresAuth: true,
            superAdminOnly: true,
        },
    },
    {
        path: '/super-admin/tenants',
        name: 'super_admin.tenants',
        component: () => import('../views/SuperAdmin/SuperAdminTenantsView.vue'),
        meta: {
            title: 'إدارة المستأجرين',
            requiresAuth: true,
            superAdminOnly: true,
        },
    },
    {
        path: '/super-admin/tenants/:id',
        name: 'super_admin.tenants.show',
        component: () => import('../views/SuperAdmin/SuperAdminTenantShowView.vue'),
        meta: {
            title: 'تفاصيل المستأجر والتحكم',
            requiresAuth: true,
            superAdminOnly: true,
        },
    },
    {
        path: '/super-admin/plans',
        name: 'super_admin.plans',
        component: () => import('../views/SuperAdmin/SuperAdminPlansView.vue'),
        meta: {
            title: 'إدارة الباقات والأسعار',
            requiresAuth: true,
            superAdminOnly: true,
        },
    },
    {
        path: '/super-admin/app-versions',
        name: 'super_admin.app_versions',
        component: () => import('../views/SuperAdmin/SuperAdminAppVersionsView.vue'),
        meta: {
            title: 'إدارة إصدارات التطبيق وحزم APK',
            requiresAuth: true,
            superAdminOnly: true,
        },
    },
    {
        path: '/super-admin/units',
        name: 'super_admin.units',
        component: () => import('../views/SuperAdmin/SuperAdminUnitsView.vue'),
        meta: {
            title: 'إدارة وحدات القياس للنظام',
            requiresAuth: true,
            superAdminOnly: true,
        },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        } else {
            return { top: 0, behavior: 'smooth' };
        }
    },
});

// Navigation Guards
router.beforeEach(async (to, from, next) => {
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
        if (authStore.isSuperAdmin && !appConfigStore.tenant) {
            return next({ name: 'super_admin.dashboard' });
        }
        return next({ name: 'dashboard' });
    }

    // 4. Requires Auth check
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } });
    }

    // 5. Smart Root Landing: Super Admin vs Tenant Store
    if (to.path === '/' && authStore.isSuperAdmin && !appConfigStore.tenant) {
        return next({ name: 'super_admin.dashboard' });
    }

    // 6. Super Admin Only check
    if (to.meta.superAdminOnly && !authStore.isSuperAdmin) {
        return next({ name: 'dashboard' });
    }

    // 6. Permission / Role Check
    if (to.meta.permission && !authStore.hasPermission(to.meta.permission)) {
        return next({ name: 'dashboard' });
    }

    if (to.meta.role && !authStore.hasRole(to.meta.role)) {
        return next({ name: 'dashboard' });
    }

    next();
});

export default router;
