/**
 * E2E Visual Testing & Crawling Pages Registry
 * Contains all registered routes organized by functional module.
 */
export const pagesConfig = [
    // 1. Auth Module
    {
        route: '/login',
        name: 'login',
        title: 'تسجيل الدخول',
        module: 'auth',
        requiresAuth: false,
    },

    // 2. Dashboard Module
    {
        route: '/',
        name: 'dashboard',
        title: 'لوحة التحكم والتحليلات',
        module: 'dashboard',
        requiresAuth: true,
    },

    // 3. POS & Fast Cashier Module
    {
        route: '/pos',
        name: 'pos',
        title: 'نقطة البيع السريعة (POS)',
        module: 'pos',
        requiresAuth: true,
    },

    // 4. Sales Invoices Module
    {
        route: '/invoices',
        name: 'invoices',
        title: 'فواتير المبيعات',
        module: 'invoices',
        requiresAuth: true,
    },

    // 5. Items & Inventory Module
    {
        route: '/items',
        name: 'items',
        title: 'الأصناف والمخزون',
        module: 'items',
        requiresAuth: true,
    },
    {
        route: '/items/1/movements',
        name: 'item-movements',
        title: 'حركات مخزون الصنف',
        module: 'items',
        requiresAuth: true,
    },

    // 6. Stores & Branches Module
    {
        route: '/stores',
        name: 'stores',
        title: 'إدارة الفروع والمخازن',
        module: 'stores',
        requiresAuth: true,
    },
    {
        route: '/stores/stocks',
        name: 'store-stocks',
        title: 'أرصدة الفروع والمخازن',
        module: 'stores',
        requiresAuth: true,
    },

    // 7. Customers Module
    {
        route: '/customers',
        name: 'customers',
        title: 'العملاء وكشوف الحساب',
        module: 'customers',
        requiresAuth: true,
    },
    {
        route: '/customers/1/statement',
        name: 'customer-statement',
        title: 'كشف حساب عميل',
        module: 'customers',
        requiresAuth: true,
    },

    // 8. Suppliers Module
    {
        route: '/suppliers',
        name: 'suppliers',
        title: 'الموردين وكشوف الحساب',
        module: 'suppliers',
        requiresAuth: true,
    },
    {
        route: '/suppliers/1/statement',
        name: 'supplier-statement',
        title: 'كشف حساب مورد',
        module: 'suppliers',
        requiresAuth: true,
    },

    // 9. Purchases & Smart Reorder Module
    {
        route: '/purchases',
        name: 'purchases',
        title: 'المشتريات والتوريد',
        module: 'purchases',
        requiresAuth: true,
    },
    {
        route: '/purchases/create',
        name: 'purchases-create',
        title: 'فاتورة مشتريات جديدة',
        module: 'purchases',
        requiresAuth: true,
    },
    {
        route: '/purchases/smart-reorder',
        name: 'smart-reorder',
        title: 'رادار إعادة الطلب الذكي',
        module: 'purchases',
        requiresAuth: true,
    },

    // 10. Expenses & Petty Cash Module
    {
        route: '/expenses',
        name: 'expenses',
        title: 'المصروفات والعهد',
        module: 'expenses',
        requiresAuth: true,
    },

    // 11. Returns & Reversals Module
    {
        route: '/returns',
        name: 'returns',
        title: 'مرتجعات المبيعات والمشتريات',
        module: 'returns',
        requiresAuth: true,
    },
    {
        route: '/returns/create',
        name: 'returns-create',
        title: 'تسجيل مرتجع جديد',
        module: 'returns',
        requiresAuth: true,
    },

    // 12. Stock Transfers Module
    {
        route: '/stock-transfers',
        name: 'stock-transfers',
        title: 'التحويلات المخزنية',
        module: 'transfers',
        requiresAuth: true,
    },
    {
        route: '/stock-transfers/create',
        name: 'stock-transfers-create',
        title: 'إذن تحويل مخزني جديد',
        module: 'transfers',
        requiresAuth: true,
    },

    // 13. Coffee Blender Engine Module
    {
        route: '/coffee-blender',
        name: 'coffee-blender',
        title: 'محرك وخلاط توليفات البن',
        module: 'blender',
        requiresAuth: true,
    },

    // 14. Shifts & Daily Journal Module
    {
        route: '/daily-journal',
        name: 'daily-journal',
        title: 'دفتر اليومية والخزينة',
        module: 'shifts',
        requiresAuth: true,
    },

    // 15. Reports & Profit Analytics Module
    {
        route: '/reports',
        name: 'reports',
        title: 'التقارير المالية والأرباح',
        module: 'reports',
        requiresAuth: true,
    },

    // 16. Users & Roles & Logs Module
    {
        route: '/users',
        name: 'users',
        title: 'إدارة المستخدمين والموظفين',
        module: 'users',
        requiresAuth: true,
    },
    {
        route: '/roles',
        name: 'roles',
        title: 'مصفوفة الصلاحيات والأدوار',
        module: 'users',
        requiresAuth: true,
    },
    {
        route: '/activity-logs',
        name: 'activity-logs',
        title: 'سجل التدقيق الأمني والنشاطات',
        module: 'users',
        requiresAuth: true,
    },

    // 17. Settings & Profile & Trash Module
    {
        route: '/settings',
        name: 'settings',
        title: 'إعدادات النظام والمؤسسة',
        module: 'settings',
        requiresAuth: true,
    },
    {
        route: '/profile',
        name: 'profile',
        title: 'الملف الشخصي والحساب',
        module: 'settings',
        requiresAuth: true,
    },
    {
        route: '/trash',
        name: 'trash',
        title: 'سلة المحذوفات',
        module: 'settings',
        requiresAuth: true,
    },

    // 18. SuperAdmin Management Module
    {
        route: '/super-admin/dashboard',
        name: 'super-admin-dashboard',
        title: 'لوحة تحكم السوبر أدمن',
        module: 'super-admin',
        requiresAuth: true,
    },
    {
        route: '/super-admin/tenants',
        name: 'super-admin-tenants',
        title: 'إدارة المستأجرين',
        module: 'super-admin',
        requiresAuth: true,
    },
    {
        route: '/super-admin/plans',
        name: 'super-admin-plans',
        title: 'إدارة الباقات والأسعار',
        module: 'super-admin',
        requiresAuth: true,
    },

    // 19. Public Marketing Presentation
    {
        route: '/brochure',
        name: 'marketing-brochure',
        title: 'بروشور المنصة والأسعار',
        module: 'marketing',
        requiresAuth: false,
    },
];

export default pagesConfig;
