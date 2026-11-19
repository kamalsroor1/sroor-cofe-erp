import { computed, ref } from 'vue';
import api from '../Services/api';
import { trans } from '../helpers/trans';
import { normalize } from '../helpers/decimal';
import { filterLines, findLineByCode, isCounted, lineDifference, nextScanQuantity } from '../helpers/stockCountSheet';

/**
 * SHOP-02: the counting screen of one stocktake session. Counted quantities are edited locally
 * (`drafts`), saved in batches (PUT /lines) and only the server's posting changes stock.
 * Quantities stay decimal strings (helpers/decimal), never floats.
 */
export function useStockCountSheet() {
    const count = ref(null);
    const lines = ref([]);
    const drafts = ref({});
    const isLoading = ref(true);
    const isSaving = ref(false);
    const isPosting = ref(false);
    const error = ref('');
    const search = ref('');
    const filter = ref('all');
    const lastScannedId = ref(null);

    const isOpen = computed(() => Boolean(count.value?.is_open));
    const isBlind = computed(() => Boolean(count.value?.is_blind) && isOpen.value);

    const dirtyIds = computed(() =>
        Object.keys(drafts.value)
            .map(Number)
            .filter((id) => {
                const line = lines.value.find((l) => l.item_id === id);
                return line && (line.counted_qty ?? null) !== (drafts.value[id] ?? null);
            })
    );

    const countedOf = (line) => (line.item_id in drafts.value ? drafts.value[line.item_id] : line.counted_qty);

    /** Difference shown while counting: counted - snapshot (posting recomputes against the live balance). */
    const differenceOf = (line) => lineDifference(line, countedOf(line), isOpen.value);

    const countedTotal = computed(() => lines.value.filter((l) => isCounted(countedOf(l))).length);

    const visibleLines = computed(() =>
        filterLines(lines.value, { search: search.value, filter: filter.value, countedOf, differenceOf })
    );

    const applyCount = (data) => {
        count.value = data;
        lines.value = data?.lines || [];
        drafts.value = {};
    };

    const load = async (id) => {
        isLoading.value = true;
        error.value = '';
        try {
            const res = await api.get(`/stock-counts/${id}`);
            applyCount(res.data?.data || null);
        } catch (err) {
            error.value = err.userMessage || err.message || trans('stock_counts.load_failed');
        } finally {
            isLoading.value = false;
        }
    };

    const setCounted = (itemId, value) => {
        const raw = value === null || value === undefined ? '' : String(value).trim();
        drafts.value = { ...drafts.value, [itemId]: raw === '' ? null : normalize(raw) };
    };

    /** Barcode / code entry: +1 on the matching line. Returns {status, line?}. */
    const scan = (rawCode) => {
        const code = String(rawCode || '').trim();
        if (!code) return { status: 'empty' };
        const line = findLineByCode(lines.value, code);
        if (!line) return { status: 'not_found', code };
        setCounted(line.item_id, nextScanQuantity(countedOf(line)));
        lastScannedId.value = line.item_id;
        return { status: 'counted', line, qty: drafts.value[line.item_id] };
    };

    const save = async () => {
        if (!count.value || dirtyIds.value.length === 0) return true;
        isSaving.value = true;
        try {
            const payload = dirtyIds.value.map((id) => ({ item_id: id, counted_qty: drafts.value[id] }));
            const res = await api.put(`/stock-counts/${count.value.id}/lines`, { lines: payload });
            applyCount(res.data?.data || count.value);
            return true;
        } finally {
            isSaving.value = false;
        }
    };

    const post = async (zeroUncounted = false) => {
        await save();
        isPosting.value = true;
        try {
            const res = await api.post(`/stock-counts/${count.value.id}/post`, { zero_uncounted: zeroUncounted });
            applyCount(res.data?.data || count.value);
            return res.data?.message || '';
        } finally {
            isPosting.value = false;
        }
    };

    const cancel = async () => {
        const res = await api.post(`/stock-counts/${count.value.id}/cancel`);
        await load(count.value.id);
        return res.data?.message || '';
    };

    return {
        count,
        lines,
        drafts,
        isLoading,
        isSaving,
        isPosting,
        error,
        search,
        filter,
        lastScannedId,
        isOpen,
        isBlind,
        dirtyIds,
        countedTotal,
        visibleLines,
        countedOf,
        differenceOf,
        load,
        setCounted,
        scan,
        save,
        post,
        cancel,
    };
}
