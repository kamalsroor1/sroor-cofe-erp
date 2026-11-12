import { computed } from 'vue';
import { useAppConfigStore } from '../stores/appConfig';
import { useAuthStore } from '../stores/auth';
import { addDays, businessToday } from './useBusinessDate';

/**
 * DATE-02: the date-picker window for every write document (purchase, expense, return, payments).
 * max = the tenant's business date (never in the future); min = business date minus the back-dating
 * window when the user holds `documents.backdate`, otherwise the business date itself.
 * Informational only: the server (App\Rules\DocumentDate) is the authority.
 */
export function useDocumentDateLimits() {
    const appConfigStore = useAppConfigStore();
    const authStore = useAuthStore();

    const maxDate = computed(() => appConfigStore.system?.business_date || businessToday());
    const minDate = computed(() => {
        if (!authStore.can('documents.backdate')) return maxDate.value;
        const days = appConfigStore.system?.backdate_max_days;
        return typeof days === 'number' ? addDays(maxDate.value, -days) : null;
    });

    return { minDate, maxDate };
}
