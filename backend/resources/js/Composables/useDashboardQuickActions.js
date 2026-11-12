import { computed } from 'vue';
import { Zap, ShoppingCart, PackagePlus, RotateCcw, HandCoins } from 'lucide-vue-next';
import { useRoutePermission } from './useRoutePermission';
import { useTrans } from './useTrans';
import { filterAccessible } from '../helpers/accessFilter';

// Each action opens a screen the user may already open: permission and plan checks come from the route table.
const ACTIONS = [
    { key: 'sale', to: '/pos', icon: Zap, labelKey: 'dashboard.quick_actions.sale' },
    { key: 'purchase', to: '/purchases/create', icon: ShoppingCart, labelKey: 'dashboard.quick_actions.purchase' },
    { key: 'item', to: '/items', icon: PackagePlus, labelKey: 'dashboard.quick_actions.item' },
    { key: 'return', to: '/returns/create', icon: RotateCcw, labelKey: 'dashboard.quick_actions.return' },
    { key: 'collect', to: '/customers', icon: HandCoins, labelKey: 'dashboard.quick_actions.collect' },
];

/** Quick-action shortcuts for the dashboard, limited to the screens the current user is allowed to open. */
export function useDashboardQuickActions() {
    const { canAccessPath } = useRoutePermission();
    const { t } = useTrans();

    const actions = computed(() =>
        filterAccessible(ACTIONS, canAccessPath).map((action) => ({
            key: action.key,
            to: action.to,
            icon: action.icon,
            label: t(action.labelKey),
        }))
    );

    const canSell = computed(() => canAccessPath('/pos'));

    return { actions, canSell };
}
