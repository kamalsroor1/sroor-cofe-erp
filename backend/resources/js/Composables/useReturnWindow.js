import { computed } from 'vue';
import { useAppConfigStore } from '../stores/appConfig';
import { useAuthStore } from '../stores/auth';
import { businessToday } from './useBusinessDate';
import { returnWindowStatus } from '../helpers/returnWindow';

/**
 * SET-02: what the return window means for the invoice picked as the source of a return, computed from
 * the tenant's `returns_max_days` and business date (both from /system/context). Informational only:
 * the server decides, and also covers the clock being a few minutes off.
 *
 * @param {import('vue').Ref<object|null>} source the selected return source ({ date: 'YYYY-MM-DD', ... })
 * @param {import('vue').Ref<boolean>} isSalesReturn the window only applies to sales returns
 */
export function useReturnWindow(source, isSalesReturn) {
    const appConfigStore = useAppConfigStore();
    const authStore = useAuthStore();

    const status = computed(() => {
        if (!isSalesReturn.value || !source.value?.date) return null;
        const result = returnWindowStatus({
            invoiceDate: source.value.date,
            today: appConfigStore.system?.business_date || businessToday(),
            maxDays: appConfigStore.system?.returns_max_days,
        });
        return result.limited ? result : null;
    });

    return {
        status,
        maxDays: computed(() => appConfigStore.system?.returns_max_days ?? null),
        canOverride: computed(() => authStore.can('returns.override_window')),
    };
}
