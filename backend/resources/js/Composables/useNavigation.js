import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useModules } from './useModules';
import {
    LayoutDashboard,
    FileText,
    Zap,
    Wallet,
    Users,
    Package,
    Tags,
    Store as StoreIcon,
    ArrowLeftRight,
    Sparkles,
    Layers,
    ShoppingCart,
    Building2,
    Receipt,
    RotateCcw,
    BarChart3,
    ShieldCheck,
    Activity,
    Trash2,
    Sliders,
    Truck,
} from 'lucide-vue-next';

export function useNavigation() {
    const authStore = useAuthStore();
    const route = useRoute();
    const { isModuleEnabled } = useModules();

    const sectionsRaw = [
        {
            key: 'main',
            title: 'الرئيسية',
            subtitle: 'لوحة المؤشرات والتحليلات الحية',
            titleKey: 'nav.dashboard',
            icon: LayoutDashboard,
            iconBg: 'bg-amber-500/10 text-amber-500',
            isDirect: true,
            directPath: '/',
            items: [
                {
                    key: 'dashboard',
                    title: 'لوحة التحكم (Dashboard)',
                    titleKey: 'nav.dashboard',
                    path: '/',
                    icon: LayoutDashboard,
                    exact: true,
                },
            ],
        },
        {
            key: 'sales',
            title: 'المبيعات ونقاط البيع',
            subtitle: 'الفواتير، نقطة البيع، اليومية، والعملاء',
            titleKey: 'nav.group_sales',
            icon: Zap,
            iconBg: 'bg-emerald-500/10 text-emerald-500',
            module: 'pos_and_sales',
            items: [
                {
                    key: 'invoices',
                    title: 'فواتير المبيعات',
                    titleKey: 'nav.invoices_log',
                    path: '/invoices',
                    icon: FileText,
                    module: 'pos_and_sales',
                },
                {
                    key: 'pos',
                    title: 'نقطة البيع السريعة (POS)',
                    titleKey: 'nav.pos_fast',
                    path: '/pos',
                    icon: Zap,
                    module: 'pos_and_sales',
                },
                {
                    key: 'daily-journal',
                    title: 'اليومية وحركة الدرج والورديات',
                    titleKey: 'nav.daily_journal',
                    path: '/daily-journal',
                    icon: Wallet,
                    module: 'treasury_and_shifts',
                },
                {
                    key: 'customers',
                    title: 'العملاء والشركات',
                    titleKey: 'nav.customers',
                    path: '/customers',
                    icon: Users,
                    module: 'customers',
                },
            ],
        },
        {
            key: 'inventory',
            title: 'المخزون والأصناف والفروع',
            subtitle: 'الأصناف، الفئات، المخازن، والتحويلات',
            titleKey: 'nav.group_inventory',
            icon: Package,
            iconBg: 'bg-sky-500/10 text-sky-500',
            module: 'inventory_and_stores',
            comingSoon: true,
            items: [
                {
                    key: 'items',
                    title: 'الأصناف والأسعار',
                    titleKey: 'nav.items_catalog',
                    path: '/items',
                    icon: Package,
                    module: 'inventory_and_stores',
                },
                {
                    key: 'categories',
                    title: 'فئات وتصنيفات الأصناف',
                    titleKey: 'nav.categories',
                    path: '/categories',
                    icon: Tags,
                    module: 'inventory_and_stores',
                },
                {
                    key: 'stores',
                    title: 'المخازن والفروع',
                    titleKey: 'nav.stores',
                    path: '/stores',
                    icon: StoreIcon,
                    module: 'inventory_and_stores',
                },
                {
                    key: 'stores-stocks',
                    title: 'أرصدة المخازن الحية',
                    titleKey: 'nav.store_stocks',
                    path: '/stores/stocks',
                    icon: StoreIcon,
                    module: 'inventory_and_stores',
                },
                {
                    key: 'stock-transfers',
                    title: 'التحويلات المخزنية',
                    titleKey: 'nav.stock_transfers',
                    path: '/stock-transfers',
                    icon: ArrowLeftRight,
                    module: 'inventory_and_stores',
                },
                {
                    key: 'smart-reorder',
                    title: 'مساعد المشتريات وإعادة الطلب',
                    titleKey: 'nav.smart_reorder',
                    path: '/purchases/smart-reorder',
                    icon: Sparkles,
                    module: 'inventory_and_stores',
                },
                {
                    key: 'coffee-blender',
                    title: 'صانع الخلطات وتوليفات البن',
                    titleKey: 'nav.coffee_blender',
                    path: '/coffee-blender',
                    icon: Layers,
                    module: 'coffee_blends',
                },
            ],
        },
        {
            key: 'purchases',
            title: 'المشتريات والموردين',
            subtitle: 'فواتير التوريد وحسابات الموردين',
            titleKey: 'nav.purchases',
            icon: Truck,
            iconBg: 'bg-indigo-500/10 text-indigo-500',
            module: 'purchases_and_suppliers',
            comingSoon: true,
            items: [
                {
                    key: 'purchases',
                    title: 'فواتير المشتريات والتوريد',
                    titleKey: 'nav.purchases',
                    path: '/purchases',
                    icon: ShoppingCart,
                    module: 'purchases_and_suppliers',
                },
                {
                    key: 'suppliers',
                    title: 'الموردون والشركات',
                    titleKey: 'nav.suppliers',
                    path: '/suppliers',
                    icon: Building2,
                    module: 'purchases_and_suppliers',
                },
            ],
        },
        {
            key: 'finance',
            title: 'المالية والمرتجعات والتقارير',
            subtitle: 'المصروفات، المرتجعات، والأرباح',
            titleKey: 'nav.group_financials',
            icon: BarChart3,
            iconBg: 'bg-purple-500/10 text-purple-500',
            comingSoon: true,
            items: [
                {
                    key: 'expenses',
                    title: 'المصروفات والعهد',
                    titleKey: 'nav.expenses',
                    path: '/expenses',
                    icon: Receipt,
                    module: 'expenses',
                },
                {
                    key: 'returns',
                    title: 'سجل المرتجعات',
                    titleKey: 'nav.returns_adjustments',
                    path: '/returns',
                    icon: RotateCcw,
                    module: 'returns',
                },
                {
                    key: 'reports',
                    title: 'التقارير المالية والأرباح',
                    titleKey: 'nav.reports',
                    path: '/reports',
                    icon: BarChart3,
                    module: 'reports',
                },
            ],
        },
        {
            key: 'system',
            title: 'إدارة النظام والأمان',
            subtitle: 'المستخدمين، الصلاحيات، وسجل الرقابة',
            titleKey: 'nav.group_management',
            icon: ShieldCheck,
            iconBg: 'bg-slate-500/10 text-slate-500',
            comingSoon: true,
            items: [
                {
                    key: 'users',
                    title: 'المستخدمون والكاشير',
                    titleKey: 'nav.users',
                    path: '/users',
                    icon: Users,
                    module: 'users_and_auth',
                },
                {
                    key: 'roles',
                    title: 'الأدوار والصلاحيات',
                    titleKey: 'nav.roles',
                    path: '/roles',
                    icon: ShieldCheck,
                    module: 'users_and_auth',
                },
                {
                    key: 'activity-logs',
                    title: 'سجل العمليات والرقابة',
                    titleKey: 'nav.audit_logs',
                    path: '/activity-logs',
                    icon: Activity,
                    module: 'users_and_auth',
                },
                {
                    key: 'trash',
                    title: 'سلة المحذوفات',
                    titleKey: 'nav.trash',
                    path: '/trash',
                    icon: Trash2,
                },
                {
                    key: 'settings',
                    title: 'إعدادات المؤسسة',
                    titleKey: 'nav.settings',
                    path: '/settings',
                    icon: Sliders,
                    module: 'settings',
                },
            ],
        },
    ];

    const navigationSections = computed(() => {
        return sectionsRaw
            .map((section) => {
                if (section.module && !isModuleEnabled(section.module)) {
                    return null;
                }

                const visibleItems = section.items.filter((item) => {
                    if (item.module && !isModuleEnabled(item.module)) {
                        return false;
                    }
                    return true;
                });

                if (visibleItems.length === 0) {
                    return null;
                }

                return {
                    ...section,
                    items: visibleItems,
                };
            })
            .filter(Boolean);
    });

    const isItemActive = (item) => {
        if (item.exact) {
            return route.path === item.path;
        }
        if (item.path === '/') {
            return route.path === '/';
        }
        return route.path.startsWith(item.path);
    };

    return {
        navigationSections,
        isItemActive,
    };
}
