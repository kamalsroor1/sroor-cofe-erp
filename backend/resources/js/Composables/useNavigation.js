import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useRoutePermission } from './useRoutePermission';
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
    const route = useRoute();
    const { canAccessPath } = useRoutePermission();

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
            items: [
                {
                    key: 'invoices',
                    title: 'فواتير المبيعات',
                    titleKey: 'nav.invoices_log',
                    path: '/invoices',
                    icon: FileText,
                },
                {
                    key: 'pos',
                    title: 'نقطة البيع السريعة (POS)',
                    titleKey: 'nav.pos_fast',
                    path: '/pos',
                    icon: Zap,
                },
                {
                    key: 'daily-journal',
                    title: 'اليومية وحركة الدرج والورديات',
                    titleKey: 'nav.daily_journal',
                    path: '/daily-journal',
                    icon: Wallet,
                },
                {
                    key: 'customers',
                    title: 'العملاء والشركات',
                    titleKey: 'nav.customers',
                    path: '/customers',
                    icon: Users,
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
            items: [
                {
                    key: 'items',
                    title: 'الأصناف والأسعار',
                    titleKey: 'nav.items_catalog',
                    path: '/items',
                    icon: Package,
                },
                {
                    key: 'categories',
                    title: 'فئات وتصنيفات الأصناف',
                    titleKey: 'nav.categories',
                    path: '/categories',
                    icon: Tags,
                },
                {
                    key: 'stores',
                    title: 'المخازن والفروع',
                    titleKey: 'nav.stores',
                    path: '/stores',
                    icon: StoreIcon,
                },
                {
                    key: 'stores-stocks',
                    title: 'أرصدة المخازن الحية',
                    titleKey: 'nav.store_stocks',
                    path: '/stores/stocks',
                    icon: StoreIcon,
                },
                {
                    key: 'stock-transfers',
                    title: 'التحويلات المخزنية',
                    titleKey: 'nav.stock_transfers',
                    path: '/stock-transfers',
                    icon: ArrowLeftRight,
                },
                {
                    key: 'smart-reorder',
                    title: 'مساعد المشتريات وإعادة الطلب',
                    titleKey: 'nav.smart_reorder',
                    path: '/purchases/smart-reorder',
                    icon: Sparkles,
                },
                {
                    key: 'coffee-blender',
                    title: 'صانع الخلطات وتوليفات البن',
                    titleKey: 'nav.coffee_blender',
                    path: '/coffee-blender',
                    icon: Layers,
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
            items: [
                {
                    key: 'purchases',
                    title: 'فواتير المشتريات والتوريد',
                    titleKey: 'nav.purchases',
                    path: '/purchases',
                    icon: ShoppingCart,
                },
                {
                    key: 'suppliers',
                    title: 'الموردون والشركات',
                    titleKey: 'nav.suppliers',
                    path: '/suppliers',
                    icon: Building2,
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
            items: [
                {
                    key: 'expenses',
                    title: 'المصروفات والعهد',
                    titleKey: 'nav.expenses',
                    path: '/expenses',
                    icon: Receipt,
                },
                {
                    key: 'returns',
                    title: 'سجل المرتجعات',
                    titleKey: 'nav.returns_adjustments',
                    path: '/returns',
                    icon: RotateCcw,
                },
                {
                    key: 'reports',
                    title: 'التقارير المالية والأرباح',
                    titleKey: 'nav.reports',
                    path: '/reports',
                    icon: BarChart3,
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
            items: [
                {
                    key: 'users',
                    title: 'المستخدمون والكاشير',
                    titleKey: 'nav.users',
                    path: '/users',
                    icon: Users,
                },
                {
                    key: 'roles',
                    title: 'الأدوار والصلاحيات',
                    titleKey: 'nav.roles',
                    path: '/roles',
                    icon: ShieldCheck,
                },
                {
                    key: 'activity-logs',
                    title: 'سجل العمليات والرقابة',
                    titleKey: 'nav.audit_logs',
                    path: '/activity-logs',
                    icon: Activity,
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
                },
            ],
        },
    ];

    const navigationSections = computed(() => {
        return sectionsRaw
            .map((section) => {
                const visibleItems = section.items.filter((item) => canAccessPath(item.path));

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
