import { ref } from 'vue';
import { useLoadError } from './useLoadError';

/**
 * Shared loading / error / retry state for super-admin console data loaders.
 * Reuses useLoadError so ErrorState and InlineErrorBar receive consistent props.
 */
export function useSuperAdminLoader(fetcher, { initialLoading = true } = {}) {
    const isLoading = ref(initialLoading);
    const hasLoaded = ref(false);
    const { error, errorMessage, statusCode, canRetry, clearError, setError } = useLoadError();
    let seq = 0;
    let lastArgs = [];

    const load = async (...args) => {
        lastArgs = args;
        const ticket = ++seq;
        isLoading.value = true;
        clearError();
        try {
            const result = await fetcher(...args);
            if (ticket !== seq) return null;
            hasLoaded.value = true;
            return result;
        } catch (err) {
            if (ticket === seq) setError(err);
            return null;
        } finally {
            if (ticket === seq) isLoading.value = false;
        }
    };

    const retry = () => load(...lastArgs);

    return {
        isLoading,
        hasLoaded,
        error,
        errorMessage,
        statusCode,
        canRetry,
        clearError,
        setError,
        load,
        retry,
    };
}
