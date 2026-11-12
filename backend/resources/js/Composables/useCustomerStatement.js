import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/api';
import { useTrans } from './useTrans';
import { notifyError, notifyWarning } from '../helpers/alert';
import { businessToday, startOfMonth, endOfMonth, startOfYear, endOfYear } from './useBusinessDate';
import {
    PRINT_MAX_ROWS,
    PRINT_PAGE_SIZE,
    STATEMENT_PAGE_SIZE,
    buildStatementParams,
    buildStatementRows,
    collectAllPages,
    describePeriod,
    normalizeStatement,
    rangeIsReversed,
} from '../helpers/customerStatement';

export function useCustomerStatement() {
    const route = useRoute();
    const customerId = route.params.id;
    const { t } = useTrans();

    const customer = ref(null);
    const ledger = ref([]);
    const summary = ref({});
    const filters = ref({ from_date: null, to_date: null, all_history: true });
    const meta = ref({ current_page: 1, per_page: STATEMENT_PAGE_SIZE, total: 0, last_page: 1 });

    const dateFrom = ref('');
    const dateTo = ref('');
    const activePreset = ref('all');
    const isLoading = ref(false);
    const error = ref(false);
    const errorMessage = ref('');
    const rangeError = ref('');

    const printing = reactive({ active: false, loaded: 0, total: 0 });
    const printRows = ref([]);
    const printSummary = ref({});
    let printAbort = null;
    let requestSeq = 0;

    const page = computed(() => Number(meta.value.current_page) || 1);
    const openingLabel = computed(() => t('contacts.statement_opening_row'));
    const rows = computed(() =>
        buildStatementRows({
            ledger: ledger.value,
            summary: summary.value,
            filters: filters.value,
            page: page.value,
            openingLabel: openingLabel.value,
        })
    );
    const hasOpening = computed(() => Boolean(rows.value[0]?._opening));
    const pageOffset = computed(() => (page.value - 1) * (Number(meta.value.per_page) || STATEMENT_PAGE_SIZE));
    const periodText = computed(() => {
        const { key, params } = describePeriod(filters.value);
        return t(key, params);
    });

    const requestPage = async (targetPage, perPage, signal) => {
        const response = await api.get(`/customers/${customerId}/statement`, {
            params: buildStatementParams({ from: dateFrom.value, to: dateTo.value, page: targetPage, perPage }),
            signal,
        });
        return normalizeStatement(response.data?.data);
    };

    const load = async (targetPage = 1) => {
        if (rangeIsReversed(dateFrom.value, dateTo.value)) {
            rangeError.value = t('reports.period_reversed');
            return;
        }
        const seq = ++requestSeq;
        isLoading.value = true;
        error.value = false;
        errorMessage.value = '';
        rangeError.value = '';
        try {
            const data = await requestPage(targetPage, STATEMENT_PAGE_SIZE);
            if (seq !== requestSeq) return;
            customer.value = data.customer;
            ledger.value = data.ledger;
            summary.value = data.summary;
            filters.value = data.filters;
            meta.value = data.meta;
        } catch (err) {
            if (seq !== requestSeq) return;
            const message = err.userMessage || err.message || t('common.error_occurred');
            if (err?.response?.status === 422) {
                rangeError.value = message;
            } else {
                error.value = true;
                errorMessage.value = message;
            }
        } finally {
            if (seq === requestSeq) isLoading.value = false;
        }
    };

    const applyPreset = (preset) => {
        activePreset.value = preset;
        const today = businessToday();

        if (preset === 'today') {
            dateFrom.value = today;
            dateTo.value = today;
        } else if (preset === 'this_month') {
            dateFrom.value = startOfMonth(today);
            dateTo.value = endOfMonth(today);
        } else if (preset === 'this_year') {
            dateFrom.value = startOfYear(today);
            dateTo.value = endOfYear(today);
        } else if (preset === 'all') {
            dateFrom.value = '';
            dateTo.value = '';
        }
        load(1);
    };

    const applyFilter = () => {
        activePreset.value = dateFrom.value || dateTo.value ? 'custom' : 'all';
        load(1);
    };

    const goToPage = (targetPage) => load(Number(targetPage) || 1);
    const reload = () => load(page.value);

    const clearPrintRows = () => {
        printRows.value = [];
    };

    const cancelPrint = () => {
        printAbort?.abort();
    };

    // The printout must hold the whole selected period, not the visible page: every page is fetched
    // (sequentially, 200 rows each, capped at PRINT_MAX_ROWS) into a print-only sheet. No server
    // print route exists for the statement, so this keeps the API's exact running balances.
    const printStatement = async () => {
        if (printing.active) return;
        printAbort = new AbortController();
        printing.active = true;
        printing.loaded = 0;
        printing.total = Number(meta.value.total) || 0;
        try {
            const result = await collectAllPages({
                fetchPage: (targetPage, perPage) => requestPage(targetPage, perPage, printAbort.signal),
                perPage: PRINT_PAGE_SIZE,
                maxRows: PRINT_MAX_ROWS,
                onProgress: (loaded, total) => {
                    printing.loaded = loaded;
                    printing.total = total;
                },
                isCancelled: () => printAbort.signal.aborted,
            });
            if (result.status === 'too_large') {
                notifyWarning(t('contacts.statement_print_too_large', { total: result.total, max: PRINT_MAX_ROWS }));
                return;
            }
            if (result.status === 'cancelled') {
                notifyWarning(t('contacts.statement_print_cancelled'));
                return;
            }
            printSummary.value = result.first.summary;
            printRows.value = buildStatementRows({
                ledger: result.rows,
                summary: result.first.summary,
                filters: result.first.filters,
                page: 1,
                openingLabel: openingLabel.value,
            });
            window.addEventListener('afterprint', clearPrintRows, { once: true });
            await nextTick();
            window.print();
        } catch (err) {
            if (err?.code === 'ERR_CANCELED') {
                notifyWarning(t('contacts.statement_print_cancelled'));
            } else {
                notifyError(t('contacts.statement_print_failed'));
            }
        } finally {
            printing.active = false;
            printAbort = null;
        }
    };

    onMounted(() => load(1));
    onBeforeUnmount(() => {
        printAbort?.abort();
        window.removeEventListener('afterprint', clearPrintRows);
    });

    return {
        error,
        errorMessage,
        rangeError,
        customer,
        ledger,
        rows,
        hasOpening,
        pageOffset,
        summary,
        filters,
        meta,
        periodText,
        dateFrom,
        dateTo,
        activePreset,
        isLoading,
        printing,
        printRows,
        printSummary,
        applyPreset,
        applyFilter,
        goToPage,
        reload,
        printStatement,
        cancelPrint,
    };
}
