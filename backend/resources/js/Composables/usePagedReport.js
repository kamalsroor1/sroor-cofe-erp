import { ref, computed } from 'vue';
import api from '../Services/api';
import { useLoadError } from './useLoadError';
import { REPORT_PAGE_SIZE, normalizeReportMeta } from '../helpers/reportPaging';

/**
 * One paged /reports/* endpoint (items, inventory, treasury). Loaded lazily when its tab opens and
 * again after the filters change. A 422 (period too long / reversed) is written to the shared
 * `periodError` ref instead of replacing the rows, so the previous page stays on screen.
 */
export function usePagedReport({ endpoint, getParams, normalize, periodError, perPage = REPORT_PAGE_SIZE }) {
    const rows = ref([]);
    const totals = ref(null);
    const extra = ref(null);
    const meta = ref(normalizeReportMeta(null, 0));
    const isLoading = ref(false);
    const loaded = ref(false);
    const { error, errorMessage, clearError, setError } = useLoadError();
    let seq = 0;

    const load = async (page = 1) => {
        const current = ++seq;
        isLoading.value = true;
        clearError();
        try {
            const res = await api.get(endpoint, { params: getParams(page, perPage) });
            if (current !== seq) return;
            const data = normalize(res.data);
            rows.value = data.rows;
            totals.value = data.totals;
            extra.value = data.extra;
            meta.value = data.meta;
            loaded.value = true;
        } catch (err) {
            if (current !== seq) return;
            if (err?.response?.status === 422) {
                periodError.value = err.userMessage || err.message;
                if (!loaded.value) setError(err);
            } else {
                setError(err);
            }
        } finally {
            if (current === seq) isLoading.value = false;
        }
    };

    const ensureLoaded = () => {
        if (!loaded.value && !isLoading.value) load(1);
    };

    // Filters changed: forget the data and drop any answer still on its way.
    const invalidate = () => {
        seq += 1;
        isLoading.value = false;
        loaded.value = false;
    };

    const goToPage = (page) => load(Number(page) || 1);
    const reload = () => load(Number(meta.value.current_page) || 1);

    // Skeleton until the first answer, and while paging; never an empty state before data exists.
    const showSkeleton = computed(() => isLoading.value || (!loaded.value && !error.value));

    return {
        rows,
        totals,
        extra,
        meta,
        isLoading,
        loaded,
        error,
        errorMessage,
        showSkeleton,
        load,
        ensureLoaded,
        invalidate,
        goToPage,
        reload,
    };
}
