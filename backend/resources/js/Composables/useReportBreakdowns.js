import { ref, computed } from 'vue';
import api from '../Services/api';
import { notifyError } from '../helpers/alert';
import { useTrans } from './useTrans';
import { useAppConfigStore } from '../stores/appConfig';
import { usePagedReport } from './usePagedReport';
import { buildReportParams, normalizeItemsResponse } from '../helpers/reportPaging';
import { businessToday } from './useBusinessDate';

/**
 * SHOP-05: the four tabular reports (sales by cashier / category, customer debt aging, supplier
 * payables). Each is a paged /reports/* endpoint answering { data, totals, meta } and has a CSV
 * export at `<endpoint>/export` with the same filters. Column definitions drive one shared table.
 */
const ENDPOINTS = {
    sales_by_cashier: '/reports/sales-by-cashier',
    sales_by_category: '/reports/sales-by-category',
    customer_aging: '/reports/customer-aging',
    supplier_payables: '/reports/supplier-payables',
};

/**
 * Existing reports with a CSV export at `<endpoint>/export` (same filters): the sales tab is the
 * P&L summary, the items tab the item profitability report.
 */
const EXPORT_ONLY_ENDPOINTS = {
    sales: '/reports/summary',
    items: '/reports/items',
};

export const EXPORTABLE_TABS = Object.keys(EXPORT_ONLY_ENDPOINTS);

/** The aging reports are a snapshot as of today: the period filter does not apply. */
export const SNAPSHOT_REPORTS = ['customer_aging', 'supplier_payables'];

export const BREAKDOWN_TABS = Object.keys(ENDPOINTS);

export function useReportBreakdowns(filters, periodError) {
    const { t } = useTrans();
    const appConfig = useAppConfigStore();
    const exporting = ref('');
    // PLAN-AUDIT: every CSV export needs the reports.export plan feature (not on the free plan).
    const canExport = computed(() => appConfig.hasFeature('reports.export'));

    const reports = Object.fromEntries(
        Object.entries(ENDPOINTS).map(([key, endpoint]) => [
            key,
            usePagedReport({
                endpoint,
                getParams: (page, perPage) => buildReportParams(filters, { page, perPage }),
                normalize: (body) => ({ ...normalizeItemsResponse(body), extra: { as_of: body?.as_of ?? null } }),
                periodError,
            }),
        ])
    );

    const agingColumns = (partyLabel) => [
        { key: 'name', label: partyLabel, type: 'text', primary: true },
        { key: 'phone', label: t('reports.col_phone'), type: 'mono' },
        { key: 'bucket_0_30', label: t('reports.aging_0_30'), type: 'money' },
        { key: 'bucket_31_60', label: t('reports.aging_31_60'), type: 'money' },
        { key: 'bucket_61_90', label: t('reports.aging_61_90'), type: 'money', tone: 'warning' },
        { key: 'bucket_90_plus', label: t('reports.aging_90_plus'), type: 'money', tone: 'danger' },
        { key: 'unallocated', label: t('reports.aging_unallocated'), type: 'money' },
        { key: 'total', label: t('reports.aging_total'), type: 'money', strong: true },
    ];

    const definitions = computed(() => ({
        sales_by_cashier: {
            title: t('reports.sales_by_cashier_title'),
            hint: t('reports.sales_by_cashier_hint'),
            columns: [
                { key: 'name', label: t('reports.col_cashier'), type: 'text', primary: true },
                { key: 'invoices_count', label: t('reports.col_invoices_count'), type: 'int' },
                { key: 'sales', label: t('reports.col_sales'), type: 'money' },
                { key: 'discounts', label: t('reports.col_discounts'), type: 'money' },
                { key: 'returns_value', label: t('reports.col_returns_value'), type: 'money', tone: 'danger' },
                { key: 'net_sales', label: t('reports.col_net_sales'), type: 'money', strong: true },
                { key: 'collected', label: t('reports.col_collected'), type: 'money', tone: 'success' },
                { key: 'credit', label: t('reports.col_credit'), type: 'money', tone: 'warning' },
                { key: 'avg_invoice', label: t('reports.col_avg_invoice'), type: 'money' },
            ],
        },
        sales_by_category: {
            title: t('reports.sales_by_category_title'),
            hint: t('reports.sales_by_category_hint'),
            columns: [
                { key: 'name', label: t('reports.col_category'), type: 'text', primary: true, colorKey: 'color' },
                { key: 'quantity', label: t('reports.col_quantity'), type: 'qty' },
                { key: 'gross_sales', label: t('reports.col_gross_sales'), type: 'money' },
                { key: 'returns_value', label: t('reports.col_returns_value'), type: 'money', tone: 'danger' },
                { key: 'net_sales', label: t('reports.col_net_sales'), type: 'money', strong: true },
                { key: 'share_percent', label: t('reports.col_share_percent'), type: 'percent' },
            ],
        },
        customer_aging: {
            title: t('reports.customer_aging_title'),
            hint: (asOf) => t('reports.customer_aging_hint', { date: asOf || businessToday() }),
            columns: agingColumns(t('reports.col_customer')),
        },
        supplier_payables: {
            title: t('reports.supplier_payables_title'),
            hint: (asOf) => t('reports.supplier_payables_hint', { date: asOf || businessToday() }),
            columns: agingColumns(t('reports.col_supplier')),
        },
    }));

    const downloadBlob = (blob, filename) => {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    const exportReport = async (key) => {
        const endpoint = ENDPOINTS[key] || EXPORT_ONLY_ENDPOINTS[key];
        if (!endpoint || exporting.value) return;
        exporting.value = key;
        try {
            const params = buildReportParams(filters);
            delete params.page;
            delete params.per_page;
            const res = await api.get(`${endpoint}/export`, { params, responseType: 'blob' });
            downloadBlob(res.data, `${key}_${businessToday()}.csv`);
        } catch (e) {
            // api.js already shows the dialog for 403.
            if (e?.response?.status !== 403) notifyError(t('common.error'), t('reports.export_failed'));
        } finally {
            exporting.value = '';
        }
    };

    const invalidateAll = () => Object.values(reports).forEach((report) => report.invalidate());

    return { reports, definitions, exporting, canExport, exportReport, invalidateAll };
}
