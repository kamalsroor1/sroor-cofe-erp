import { Boxes, ChartColumn, Layers, PackagePlus, ScanBarcode, ShieldCheck, Users, Wallet } from 'lucide-vue-next';

/**
 * The 8 feature groups of /features, in chip order (build_pages.py GROUPS). Copy lives in
 * landing.home.features.groups.<key>.{title,body,items.<item>}; `layout` picks the zig-zag row (with a
 * FeatureStage mockup), the 2-column card grid, or the coffee band.
 */
export const FEATURE_GROUPS = Object.freeze([
    {
        key: 'pos',
        icon: ScanBarcode,
        layout: 'row',
        items: ['barcode', 'quick_keys', 'shortcuts', 'multi_orders', 'payments', 'shifts', 'receipt', 'pin'],
    },
    {
        key: 'inventory',
        icon: Boxes,
        layout: 'row',
        items: ['stores', 'transfers', 'movements', 'adjustments', 'units', 'imports'],
    },
    { key: 'purchases', icon: PackagePlus, layout: 'card', items: ['purchases', 'returns', 'reorder', 'cancel'] },
    { key: 'parties', icon: Users, layout: 'card', items: ['statements', 'payments', 'tiers', 'last_price'] },
    { key: 'treasury', icon: Wallet, layout: 'card', items: ['treasury', 'expenses', 'journal'] },
    {
        key: 'reports',
        icon: ChartColumn,
        layout: 'row',
        items: ['profit_loss', 'items_profit', 'valuation', 'stores', 'customers', 'expenses'],
    },
    {
        key: 'team',
        icon: ShieldCheck,
        layout: 'card',
        items: ['roles', 'stores_access', 'activity', 'trash', 'telegram'],
    },
    { key: 'blender', icon: Layers, layout: 'band', items: ['blend', 'roast_grind', 'cost'] },
]);

/** DOM id of a group's section (chip targets and the scroll-spy). */
export const featureGroupId = (key) => `group-${key}`;

export const featureGroupKey = (key) => `landing.home.features.groups.${key}`;
