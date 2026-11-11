import { ref } from 'vue';
import { useTrans } from './useTrans.js';

/**
 * Shared load-failure state ({ error, errorMessage, statusCode, canRetry }) for composables that feed
 * ErrorState (no data on screen) / InlineErrorBar (stale data on screen).
 */
export function useLoadError() {
    const { t } = useTrans();

    const error = ref(false);
    const errorMessage = ref('');
    const statusCode = ref(null);
    const canRetry = ref(true);

    const clearError = () => {
        error.value = false;
        errorMessage.value = '';
        statusCode.value = null;
        canRetry.value = true;
    };

    const setError = (err) => {
        error.value = true;
        const status = err?.response?.status ?? err?.status ?? null;
        statusCode.value = status ? Number(status) : null;
        canRetry.value = statusCode.value !== 403;
        errorMessage.value =
            err?.userMessage || err?.response?.data?.message || err?.message || t('common.error_occurred');
    };

    return { error, errorMessage, statusCode, canRetry, clearError, setError };
}
