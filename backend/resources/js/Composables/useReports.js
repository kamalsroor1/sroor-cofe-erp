import { ref, reactive, computed, onMounted } from 'vue';
import api from '../services/api';
import { useTrans } from './useTrans';

export function useReports() {
    const { t } = useTrans();

    const activeTab = ref('sales');
    const isLoading = ref(false);
    const stores = ref([]);

    const filters = reactive({
        period: 'this_month',
        from: '',
        to: '',
        store_id: 'all',
        stock_filter: 'all',
    });

    const tabs = computed(() => [
        { key: 'sales', label: t('reports.tab_sales'), icon: '📈' },
        { key: 'items', label: t('reports.tab_items'), icon: '☕' },
        { key: 'stores', label: t('reports.tab_stores'), icon: '🏢' },
        { key: 'customers', label: t('reports.tab_customers'), icon: '👥' },
        { key: 'expenses', label: t('reports.tab_expenses'), icon: '💸' },
        { key: 'inventory', label: t('reports.tab_inventory'), icon: '📦' },
        { key: 'treasury', label: t('reports.tab_treasury'), icon: '🏦' },
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

    const itemProfits = ref([]);
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
        total_inflow: 0,
        total_outflow: 0,
        net_cash_flow: 0,
    });

    const setPeriod = (period) => {
        filters.period = period;
        const now = new Date();
        if (period === 'today') {
            const todayStr = now.toISOString().split('T')[0];
            filters.from = todayStr;
            filters.to = todayStr;
        } else if (period === 'yesterday') {
            const d = new Date();
            d.setDate(d.getDate() - 1);
            const yStr = d.toISOString().split('T')[0];
            filters.from = yStr;
            filters.to = yStr;
        } else if (period === 'this_week') {
            const d = new Date();
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1);
            filters.from = new Date(d.setDate(diff)).toISOString().split('T')[0];
            filters.to = new Date().toISOString().split('T')[0];
        } else if (period === 'this_month') {
            const d = new Date();
            filters.from = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
            filters.to = new Date().toISOString().split('T')[0];
        } else if (period === 'this_year') {
            const d = new Date();
            filters.from = new Date(d.getFullYear(), 0, 1).toISOString().split('T')[0];
            filters.to = new Date().toISOString().split('T')[0];
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
            console.error('Failed to load stores:', e);
        }
    };

    const fetchReportsData = async () => {
        isLoading.value = true;
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
            itemProfits.value = d.item_profits || [];
            storeBreakdown.value = d.store_breakdown || [];
            customerSales.value = d.customer_sales || [];
            expensesBreakdown.value = d.expenses_breakdown || [];
            inventoryData.value = d.inventory_data || {};
            treasuryData.value = d.treasury_data || {};
        } catch (e) {
            console.error('Failed to load comprehensive reports:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const printReport = () => {
        window.print();
    };

    onMounted(() => {
        const d = new Date();
        filters.from = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
        filters.to = new Date().toISOString().split('T')[0];

        loadStores();
        fetchReportsData();
    });

    return {
        activeTab,
        isLoading,
        stores,
        filters,
        tabs,
        presets,
        summary,
        itemProfits,
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
