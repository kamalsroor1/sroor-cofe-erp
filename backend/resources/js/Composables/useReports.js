import { ref, reactive, computed, watch, onMounted } from 'vue';
import { TrendingUp, Coffee, Store, Users, Receipt, Package, Building2, Scale } from 'lucide-vue-next';
import api from '../Services/api';
import { useTrans } from './useTrans';
import { usePagedReport } from './usePagedReport';
import { rangeIsReversed } from '../helpers/customerStatement';
import {
    buildReportParams,
    normalizeInventoryResponse,
    normalizeItemsResponse,
    normalizeTreasuryResponse,
    reportTotal,
} from '../helpers/reportPaging';
import { addDays, businessToday, startOfMonth, startOfWeek, startOfYear } from './useBusinessDate';

export function useReports() {
    const { t } = useTrans();

    const activeTab = ref('sales');
    const isLoading = ref(false);
    const error = ref(false);
    const errorMessage = ref('');
    const stores = ref([]);
    const periodError = ref('');

    const filters = reactive({
        period: 'this_month',
        from: '',
        to: '',
        store_id: 'all',
        stock_filter: 'all',
    });

    const tabs = computed(() => [
        { key: 'sales', label: t('reports.tab_sales'), icon: TrendingUp },
        { key: 'items', label: t('reports.tab_items'), icon: Coffee },
        { key: 'stores', label: t('reports.tab_stores'), icon: Store },
        { key: 'customers', label: t('reports.tab_customers'), icon: Users },
        { key: 'expenses', label: t('reports.tab_expenses'), icon: Receipt },
        { key: 'inventory', label: t('reports.tab_inventory'), icon: Package },
        { key: 'stock_drift', label: t('reports.tab_stock_drift'), icon: Scale },
        { key: 'treasury', label: t('reports.tab_treasury'), icon: Building2 },
    ]);

    const presets = computed(() => [
        { key: 'today', label: t('common.today') },
        { key: 'yesterday', label: t('common.yesterday') },
        { key: 'this_week', label: t('common.this_week') },
        { key: 'this_month', label: t('common.this_month') },
        { key: 'this_year', label: t('common.this_year') },
    ]);

    const summary = ref({
        total_sales: 0,
        total_cogs: 0,
        gross_profit: 0,
        margin_percentage: 0,
        total_expenses: 0,
        expenses_count: 0,
        net_profit: 0,
        invoices_count: 0,
        avg_invoice: 0,
        total_paid: 0,
        total_remaining: 0,
        total_customers_debt: 0,
    });

    const storeBreakdown = ref([]);
    const customerSales = ref([]);
    const expensesBreakdown = ref([]);
    const inventoryData = ref({
        stock_cost_valuation: 0,
        stock_selling_valuation: 0,
        expected_stock_profit: 0,
        items: [],
        abc_data: null,
    });
    const treasuryData = ref({
        total_inflows: '0.000',
        total_outflows: '0.000',
        total_net_change: '0.000',
    });
    // /reports/comprehensive caps its embedded lists; `meta` says how many rows really exist.
    const listMeta = ref({});

    const reportParams = (withStock) => (page, perPage) => buildReportParams(filters, { withStock, page, perPage });

    const itemsReport = usePagedReport({
        endpoint: '/reports/items',
        getParams: reportParams(false),
        normalize: normalizeItemsResponse,
        periodError,
    });
    const inventoryReport = usePagedReport({
        endpoint: '/reports/inventory',
        getParams: reportParams(true),
        normalize: normalizeInventoryResponse,
        periodError,
    });
    const treasuryReport = usePagedReport({
        endpoint: '/reports/treasury',
        getParams: reportParams(false),
        normalize: normalizeTreasuryResponse,
        periodError,
    });

    const pagedByTab = { items: itemsReport, inventory: inventoryReport, treasury: treasuryReport };

    const itemsTotal = computed(() =>
        reportTotal(itemsReport.meta.value, itemsReport.loaded.value, listMeta.value.item_profits?.total)
    );
    const inventoryTotal = computed(() =>
        reportTotal(inventoryReport.meta.value, inventoryReport.loaded.value, listMeta.value.inventory_items?.total)
    );
    const treasuryLedgerTotal = computed(() =>
        reportTotal(treasuryReport.meta.value, treasuryReport.loaded.value, listMeta.value.treasury_ledger?.total)
    );

    watch(activeTab, (tab) => pagedByTab[tab]?.ensureLoaded());

    const setPeriod = (period) => {
        filters.period = period;
        const today = businessToday();
        filters.to = today;
        if (period === 'today') {
            filters.from = today;
        } else if (period === 'yesterday') {
            filters.from = addDays(today, -1);
            filters.to = filters.from;
        } else if (period === 'this_week') {
            filters.from = startOfWeek(today, 1);
        } else if (period === 'this_month') {
            filters.from = startOfMonth(today);
        } else if (period === 'this_year') {
            filters.from = startOfYear(today);
        }

        fetchReportsData();
    };

    const customDateChanged = () => {
        filters.period = 'custom';
        fetchReportsData();
    };

    const loadStores = async () => {
        try {
            const res = await api.get('/stores');
            stores.value = res.data?.data || [];
        } catch (e) {
            error.value = true;
            errorMessage.value = e.userMessage || e.message || t('common.error_occurred');
            console.error('Failed to load stores:', e);
        }
    };

    const fetchReportsData = async () => {
        if (rangeIsReversed(filters.from, filters.to)) {
            periodError.value = t('reports.period_reversed');
            return;
        }
        periodError.value = '';
        isLoading.value = true;
        error.value = false;
        errorMessage.value = '';
        try {
            const params = {
                period: filters.period,
                from: filters.from || undefined,
                to: filters.to || undefined,
                store_id: filters.store_id !== 'all' ? filters.store_id : undefined,
                stock_filter: filters.stock_filter !== 'all' ? filters.stock_filter : undefined,
            };

            const res = await api.get('/reports/comprehensive', { params });
            const d = res.data || {};

            summary.value = d.summary || {};
            storeBreakdown.value = d.store_breakdown || [];
            customerSales.value = d.customer_sales || [];
            expensesBreakdown.value = d.expenses_breakdown || [];
            inventoryData.value = d.inventory_data || {};
            treasuryData.value = d.treasury_data || {};
            listMeta.value = d.meta || {};

            Object.values(pagedByTab).forEach((report) => report.invalidate());
            pagedByTab[activeTab.value]?.ensureLoaded();
        } catch (e) {
            const message = e.userMessage || e.message || t('common.error_occurred');
            if (e?.response?.status === 422) {
                // Too long / reversed period: explain it by the date filter, keep the data on screen.
                periodError.value = message;
            } else {
                error.value = true;
                errorMessage.value = message;
            }
        } finally {
            isLoading.value = false;
        }
    };

    const printReport = () => {
        window.print();
    };

    onMounted(() => {
        const today = businessToday();
        filters.from = startOfMonth(today);
        filters.to = today;

        loadStores();
        fetchReportsData();
    });

    return {
        error,
        errorMessage,
        activeTab,
        isLoading,
        stores,
        filters,
        tabs,
        presets,
        summary,
        periodError,
        itemsReport,
        inventoryReport,
        treasuryReport,
        itemsTotal,
        inventoryTotal,
        treasuryLedgerTotal,
        storeBreakdown,
        customerSales,
        expensesBreakdown,
        inventoryData,
        treasuryData,
        setPeriod,
        customDateChanged,
        fetchReportsData,
        printReport,
    };
}
