import { reactive, ref, nextTick, onBeforeUnmount } from 'vue';
import { notifyError, notifyWarning } from '../helpers/alert';
import { collectAllPages } from '../helpers/customerStatement';
import {
    REPORT_PRINT_MAX_ROWS,
    REPORT_PRINT_PAGE_SIZE,
    reportPrintColumns,
    reportPrintSummary,
} from '../helpers/reportPrint';
import { useTrans } from './useTrans';

/**
 * PERF-01: "print A4" on a paged report tab. Every page of the active tab is fetched with the
 * current filters (sequentially, 200 rows each, capped at REPORT_PRINT_MAX_ROWS, cancellable) into
 * a print-only sheet that is emptied after printing, so the screen keeps its 25-row page. Tabs
 * that are not paged print the screen as before.
 *
 * `pagedByTab`: { tab: usePagedReport() }. `breakdownDefinitions`: computed column definitions of
 * the breakdown tabs. `tabTitle(tab)`: the tab's label.
 */
export function useReportPrint({ pagedByTab, breakdownDefinitions, tabTitle }) {
    const { t } = useTrans();
    const printing = reactive({ active: false, loaded: 0, total: 0 });
    const sheet = ref(null);
    let printAbort = null;

    const clearSheet = () => {
        sheet.value = null;
    };

    const cancelPrint = () => {
        printAbort?.abort();
    };

    const columnsFor = (tab, rows) => breakdownDefinitions.value[tab]?.columns ?? reportPrintColumns(tab, t, rows);

    const printPagedTab = async (tab, report) => {
        printAbort = new AbortController();
        printing.active = true;
        printing.loaded = 0;
        printing.total = Number(report.meta.value?.total) || 0;
        try {
            const result = await collectAllPages({
                fetchPage: (page, perPage) => report.fetchPage(page, perPage, printAbort.signal),
                perPage: REPORT_PRINT_PAGE_SIZE,
                maxRows: REPORT_PRINT_MAX_ROWS,
                rowsOf: (page) => page.rows,
                onProgress: (loaded, total) => {
                    printing.loaded = loaded;
                    printing.total = total;
                },
                isCancelled: () => printAbort.signal.aborted,
            });
            if (result.status === 'too_large') {
                notifyWarning(t('reports.print_too_large', { total: result.total, max: REPORT_PRINT_MAX_ROWS }));
                return;
            }
            if (result.status === 'cancelled') {
                notifyWarning(t('reports.print_cancelled'));
                return;
            }
            sheet.value = {
                tab,
                title: tabTitle(tab),
                columns: columnsFor(tab, result.rows),
                rows: result.rows,
                totals: result.first.totals,
                summary: reportPrintSummary(tab, t, result.first.extra),
            };
            window.addEventListener('afterprint', clearSheet, { once: true });
            await nextTick();
            window.print();
        } catch (err) {
            if (err?.code === 'ERR_CANCELED') {
                notifyWarning(t('reports.print_cancelled'));
            } else {
                notifyError(t('reports.print_failed'));
            }
        } finally {
            printing.active = false;
            printAbort = null;
        }
    };

    const printTab = async (tab, { locked = false } = {}) => {
        if (printing.active) return;
        const report = pagedByTab[tab];
        if (!report || locked) {
            window.print();
            return;
        }
        await printPagedTab(tab, report);
    };

    onBeforeUnmount(() => {
        printAbort?.abort();
        window.removeEventListener('afterprint', clearSheet);
    });

    return { printing, sheet, printTab, cancelPrint };
}
