import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useRoutePermission } from './useRoutePermission';
import { trans } from '../helpers/trans';
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
    FileUp,
    CreditCard,
    LifeBuoy,
    LayoutGrid,
} from 'lucide-vue-next';

export function useNavigation() {
    const route = useRoute();
    const { canAccessPath } = useRoutePermission();

    const sectionsRaw = [
        {
            key: 'main',
            icon: LayoutDashboard,
            iconBg: 'bg-amber-500/10 text-amber-500',
            isDirect: true,
            directPath: '/',
            items: [
                {
                    key: 'dashboard',
                    path: '/',
                    icon: LayoutDashboard,
                    exact: true,
                },
            ],
        },
        {
            key: 'sales',
            icon: Zap,
            iconBg: 'bg-emerald-500/10 text-emerald-500',
            items: [
                {
                    key: 'invoices',
                    path: '/invoices',
                    icon: FileText,
                },
                {
                    key: 'pos',
                    path: '/pos',
                    icon: Zap,
                },
                {
                    key: 'daily-journal',
                    path: '/daily-journal',
                    icon: Wallet,
                },
                {
                    key: 'customers',
                    path: '/customers',
                    icon: Users,
                },
            ],
        },
        {
            key: 'inventory',
            icon: Package,
            iconBg: 'bg-sky-500/10 text-sky-500',
            items: [
                {
                    key: 'items',
                    path: '/items',
                    icon: Package,
                },
                {
                    key: 'categories',
                    path: '/categories',
                    icon: Tags,
                },
                {
                    key: 'stores',
                    path: '/stores',
                    icon: StoreIcon,
                },
                {
                    key: 'stores-stocks',
                    path: '/stores/stocks',
                    icon: StoreIcon,
                },
                {
                    key: 'stock-transfers',
                    path: '/stock-transfers',
                    icon: ArrowLeftRight,
                },
                {
                    key: 'smart-reorder',
                    path: '/purchases/smart-reorder',
                    icon: Sparkles,
                },
                {
                    key: 'coffee-blender',
                    path: '/coffee-blender',
                    icon: Layers,
                },
            ],
        },
        {
            key: 'purchases',
            icon: Truck,
            iconBg: 'bg-indigo-500/10 text-indigo-500',
            items: [
                {
                    key: 'purchases',
                    path: '/purchases',
                    icon: ShoppingCart,
                },
                {
                    key: 'suppliers',
                    path: '/suppliers',
                    icon: Building2,
                },
            ],
        },
        {
            key: 'finance',
            icon: BarChart3,
            iconBg: 'bg-purple-500/10 text-purple-500',
            items: [
                {
                    key: 'expenses',
                    path: '/expenses',
                    icon: Receipt,
                },
                {
                    key: 'returns',
                    path: '/returns',
                    icon: RotateCcw,
                },
                {
                    key: 'reports',
                    path: '/reports',
                    icon: BarChart3,
                },
            ],
        },
        {
            key: 'system',
            icon: ShieldCheck,
            iconBg: 'bg-slate-500/10 text-slate-500',
            items: [
                {
                    key: 'users',
                    path: '/users',
                    icon: Users,
                },
                {
                    key: 'roles',
                    path: '/roles',
                    icon: ShieldCheck,
                },
                {
                    key: 'activity-logs',
                    path: '/activity-logs',
                    icon: Activity,
                },
                {
                    key: 'imports',
                    path: '/imports',
                    icon: FileUp,
                },
                {
                    key: 'trash',
                    path: '/trash',
                    icon: Trash2,
                },
                {
                    key: 'settings',
                    path: '/settings',
                    icon: Sliders,
                },
                {
                    key: 'billing',
                    path: '/billing',
                    icon: CreditCard,
                },
                {
                    key: 'all-screens',
                    path: '/all-screens',
                    icon: LayoutGrid,
                },
                {
                    key: 'help',
                    path: '/help',
                    icon: LifeBuoy,
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

                // trans() is reactive, so titles re-render when the language changes.
                return {
                    ...section,
                    title: trans(`nav.menu.sections.${section.key}.title`),
                    subtitle: trans(`nav.menu.sections.${section.key}.subtitle`),
                    items: visibleItems.map((item) => ({
                        ...item,
                        title: trans(`nav.menu.items.${item.key}`),
                    })),
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
