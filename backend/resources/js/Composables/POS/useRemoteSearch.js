import { ref, onBeforeUnmount } from 'vue';
import api from '../../Services/api';

const DEBOUNCE_MS = 250;

const isCancel = (err) => err.name === 'CanceledError' || err.code === 'ERR_CANCELED' || err.message === 'canceled';

/**
 * Debounced, abortable server search against a list endpoint.
 * `pick(res)` extracts the rows from the axios response.
 */
export function useRemoteSearch(endpoint, pick) {
    const results = ref([]);
    const isSearching = ref(false);
    let timer = null;
    let controller = null;

    const cancel = () => {
        if (timer) clearTimeout(timer);
        timer = null;
        if (controller) controller.abort();
        controller = null;
    };

    const search = (query) => {
        cancel();

        const q = query.trim();
        if (!q) {
            results.value = [];
            isSearching.value = false;
            return;
        }

        timer = setTimeout(async () => {
            controller = new AbortController();
            isSearching.value = true;

            try {
                const res = await api.get(endpoint, { params: { search: q, per_page: 30 }, signal: controller.signal });
                results.value = pick(res);
                isSearching.value = false;
            } catch (err) {
                if (isCancel(err)) return;
                console.error(`Remote search error (${endpoint}):`, err);
                isSearching.value = false;
            } finally {
                if (controller && !controller.signal.aborted) controller = null;
            }
        }, DEBOUNCE_MS);
    };

    onBeforeUnmount(cancel);

    return { results, isSearching, search };
}
