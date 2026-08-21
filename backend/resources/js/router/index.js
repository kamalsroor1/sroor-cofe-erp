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

    // 1. If user is authenticated, ensure profile and system context are loaded
    if (authStore.isAuthenticated && !appConfigStore.isLoaded) {
        try {
            await appConfigStore.fetchBootstrapContext();
            window.spaTranslations = appConfigStore.translations;
        } catch (e) {
            console.error('Bootstrap context load error:', e);
        }
    }

    // 2. Set document title
    const appName = appConfigStore.system?.company_name || 'مخزني ERP';
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
});

export default router;
