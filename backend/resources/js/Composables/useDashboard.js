import { ref, computed, onMounted } from 'vue';
import api from '../Services/api';
import { useLoadError } from './useLoadError';
import { isOwnShiftScope } from '../helpers/dashboardScope';

export function useDashboard() {
    const { error, errorMessage, clearError, setError } = useLoadError();

    const dashboardData = ref(null);
    const isLoading = ref(true);

    const isOwnShift = computed(() => isOwnShiftScope(dashboardData.value));
    const metrics = computed(() => dashboardData.value?.metrics || {});
    const analytics = computed(() => dashboardData.value?.analytics || {});
    const dailyTrend = computed(() => analytics.value?.daily_trend || []);
    const hourlySales = computed(() => analytics.value?.hourly_sales || []);
    const peakHour = computed(() => analytics.value?.peak_hour || null);
    const paymentDistribution = computed(() => analytics.value?.payment_distribution || []);
    const periodAnalytics = computed(() => analytics.value?.period || {});
    const recentInvoices = computed(() => dashboardData.value?.recent_invoices || []);
    const lowStockItems = computed(() => dashboardData.value?.low_stock_items || []);
    const activeShift = computed(() => dashboardData.value?.active_shift || null);

    const fetchDashboard = async () => {
        isLoading.value = true;
        clearError();
        try {
            const res = await api.get('/dashboard');
            if (res.data?.data) {
                dashboardData.value = res.data.data;
            } else {
                setError(null);
            }
        } catch (e) {
            setError(e);
        } finally {
            isLoading.value = false;
        }
    };

    onMounted(fetchDashboard);

    return {
        dashboardData,
        isOwnShift,
        isLoading,
        error,
        errorMessage,
        retry: fetchDashboard,
        fetchDashboard,
        metrics,
        dailyTrend,
        hourlySales,
        peakHour,
        paymentDistribution,
        periodAnalytics,
        recentInvoices,
        lowStockItems,
        activeShift,
    };
}
