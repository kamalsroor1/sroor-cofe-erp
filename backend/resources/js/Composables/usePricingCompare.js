import { computed, ref, watch } from 'vue';
import { trans } from '../helpers/trans';
import { LIMIT_KEYS, formatCatalogCount } from '../helpers/landingPricing';
import { defaultComparePair, groupCatalogFeatures, mergeCatalogFeatures } from '../helpers/landingCatalog';

const MB_PER_GB = 1024;

/** A limit cell: null = unlimited, 0 = not included, storage shown in GB when it is a whole number. */
function limitCell(key, value) {
    if (value === null || value === undefined) return { kind: 'text', text: trans('landing.unlimited') };
    if (Number(value) === 0) return { kind: 'no' };
    if (key === 'storage_mb') {
        const gb = Number(value) % MB_PER_GB === 0;
        return {
            kind: 'text',
            text: trans(gb ? 'landing.pricing.compare.units.gb' : 'landing.pricing.compare.units.mb', {
                value: formatCatalogCount(gb ? Number(value) / MB_PER_GB : value),
            }),
        };
    }
    return { kind: 'text', text: formatCatalogCount(value) };
}

function moduleLabel(module) {
    const key = `landing.pricing.compare.modules.${module}`;
    const label = trans(key);
    return label === key ? trans('landing.pricing.compare.modules.other') : label;
}

/**
 * Rows of the plan comparison, built only from GET /public/plans: the limits group, then one group per
 * feature module (registry order). Each row carries a cell per plan code. On narrow screens the table
 * compares two plans the visitor picks, and only the first groups until "show all" is pressed.
 */
export function usePricingCompare(plans) {
    const rows = computed(() => {
        const list = plans.value;
        const out = [{ type: 'group', id: 'limits', label: trans('landing.pricing.compare.limits_group') }];
        LIMIT_KEYS.forEach((key) => {
            out.push({
                type: 'row',
                id: `limit-${key}`,
                label: trans(key === 'storage_mb' ? 'landing.pricing.compare.storage' : `landing.limits.${key}`),
                description: '',
                cells: Object.fromEntries(list.map((plan) => [plan.code, limitCell(key, plan.limits?.[key])])),
            });
        });
        groupCatalogFeatures(mergeCatalogFeatures(list)).forEach((group) => {
            out.push({ type: 'group', id: `module-${group.module}`, label: moduleLabel(group.module) });
            group.features.forEach((feature) => {
                out.push({
                    type: 'row',
                    id: `feature-${feature.key}`,
                    label: feature.name,
                    description: feature.description || '',
                    cells: Object.fromEntries(
                        list.map((plan) => [
                            plan.code,
                            { kind: (plan.features || []).some((f) => f.key === feature.key) ? 'yes' : 'no' },
                        ])
                    ),
                });
            });
        });
        return out;
    });

    const pair = ref([]);
    watch(
        plans,
        (list) => {
            pair.value = defaultComparePair(list);
        },
        { immediate: true }
    );
    const pairPlans = computed(() =>
        pair.value.map((code) => plans.value.find((plan) => plan.code === code)).filter(Boolean)
    );
    const setPair = (slot, code) => {
        const next = [...pair.value];
        next[slot] = code;
        pair.value = next;
    };

    // Collapsed: the limits group and the first feature group.
    const expanded = ref(false);
    const collapsedRows = computed(() => {
        const groupStarts = rows.value.reduce((acc, row, i) => (row.type === 'group' ? [...acc, i] : acc), []);
        return groupStarts.length > 2 ? rows.value.slice(0, groupStarts[2]) : rows.value;
    });
    const canExpand = computed(() => collapsedRows.value.length < rows.value.length);
    const mobileRows = computed(() => (expanded.value ? rows.value : collapsedRows.value));

    return { rows, pair, pairPlans, setPair, expanded, canExpand, mobileRows };
}
