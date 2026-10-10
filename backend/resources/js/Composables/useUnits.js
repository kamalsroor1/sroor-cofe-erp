import { computed } from 'vue';
import { useAppConfigStore } from '../stores/appConfig';
import { trans } from '../helpers/trans';

/**
 * SETG-10: the tenant's unit list is `system.inventory_units` from /system/context
 * (the saved `inventory_units` setting, picked from the platform catalog; the server
 * already falls back to its default list). The SPA never ships its own unit list.
 */
export function cleanUnits(raw) {
    const list = Array.isArray(raw) ? raw : typeof raw === 'string' ? raw.split(',') : [];
    const seen = new Set();
    const units = [];
    for (const entry of list) {
        const unit = typeof entry === 'string' ? entry.trim() : '';
        if (unit !== '' && !seen.has(unit)) {
            seen.add(unit);
            units.push(unit);
        }
    }
    return units;
}

export function useUnits() {
    const appConfigStore = useAppConfigStore();

    const units = computed(() => cleanUnits(appConfigStore.system?.inventory_units));

    /** First unit of the tenant list: the default for a new item and for lines without a unit. */
    const defaultUnit = computed(() => units.value[0] || '');

    /** Display text for a line's unit: its own unit, else the tenant default. */
    const unitLabel = (unit) => (typeof unit === 'string' && unit.trim() !== '' ? unit : defaultUnit.value);

    /**
     * Select options for an item form. An item saved with a unit that is no longer in
     * the tenant list (legacy data) keeps that unit as an extra, labelled option so
     * editing never silently changes it.
     *
     * @param {string|null|undefined} currentUnit
     * @returns {{ value: string, label: string }[]}
     */
    const unitOptionsFor = (currentUnit) => {
        const options = units.value.map((unit) => ({ value: unit, label: unit }));
        const legacy = typeof currentUnit === 'string' ? currentUnit.trim() : '';
        if (legacy !== '' && !units.value.includes(legacy)) {
            options.push({ value: legacy, label: trans('items.unit_not_in_list', { unit: legacy }) });
        }
        return options;
    };

    return { units, defaultUnit, unitLabel, unitOptionsFor };
}
