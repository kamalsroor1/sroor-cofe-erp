import { ref } from 'vue';
import { useTrans } from './useTrans';

/**
 * Shared load-failure state ({ error, errorMessage }) for composables that feed
 * ErrorState (no data on screen) / InlineErrorBar (stale data on screen).
 */
export function useLoadError() {
    const { t } = useTrans();

    const error = ref(false);
    const errorMessage = ref('');

    const clearError = () => {
        error.value = false;
        errorMessage.value = '';
    };

    const setError = (err) => {
        error.value = true;
        errorMessage.value = err?.userMessage || err?.message || t('common.error_occurred');
    };

    return { error, errorMessage, clearError, setError };
}
