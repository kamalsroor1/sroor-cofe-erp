import { ref } from 'vue';
import api from '../Services/api';
import { useTrans } from './useTrans';

/**
 * Server figures of the shift being closed. The close-shift modal never derives the expected cash from the
 * day's journal: it asks GET /shifts/current, whose metrics belong to the open shift alone. A metric the server
 * withholds (cashier who did not open the shift) stays null so the modal shows no expected cash and no preview.
 */
export function useShiftCloseFigures() {
    const { t } = useTrans();

    const isLoading = ref(false);
    const failed = ref(false);
    const errorMessage = ref('');
    const shiftId = ref(null);
    const openingCash = ref(null);
    const expectedCash = ref(null);

    // Bumped by every load()/reset() so a response that lands after a newer request (dialog closed and reopened) is dropped.
    let requestId = 0;

    const reset = () => {
        requestId++;
        failed.value = false;
        errorMessage.value = '';
        shiftId.value = null;
        openingCash.value = null;
        expectedCash.value = null;
    };

    const load = async () => {
        reset();
        const current = requestId;
        isLoading.value = true;
        try {
            const response = await api.get('/shifts/current');
            if (current !== requestId) return;
            const shift = response.data?.active_shift;
            if (!shift) {
                failed.value = true;
                errorMessage.value = t('treasury.close_shift_figures_failed');
                return;
            }
            shiftId.value = shift.id;
            const metrics = response.data?.metrics || {};
            openingCash.value = metrics.opening_cash_balance ?? null;
            expectedCash.value = metrics.expected_cash_balance ?? null;
        } catch (error) {
            if (current !== requestId) return;
            failed.value = true;
            errorMessage.value = error.userMessage || t('treasury.close_shift_figures_failed');
        } finally {
            if (current === requestId) isLoading.value = false;
        }
    };

    return { isLoading, failed, errorMessage, shiftId, openingCash, expectedCash, load, reset };
}
