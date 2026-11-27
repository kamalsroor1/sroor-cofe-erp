import { ref, onMounted } from 'vue';
import api from '../Services/centralApi';
import { useSuperAdminLoader } from './useSuperAdminLoader';

/**
 * Platform dashboard data (GET /super-admin/dashboard). The platform branding form moved to
 * its own page (/super-admin/settings, usePlatformSettings).
 */
export function useSuperAdminDashboard() {
    const metrics = ref({});
    const planStats = ref([]);
    const recentTenants = ref([]);
    const systemInfo = ref({});

    const {
        isLoading,
        hasLoaded,
        error,
        errorMessage,
        statusCode,
        canRetry,
        load: fetchDashboard,
        retry,
    } = useSuperAdminLoader(async () => {
        const res = await api.get('/super-admin/dashboard');
        metrics.value = res.data?.metrics || {};
        planStats.value = res.data?.plan_stats || [];
        recentTenants.value = res.data?.recent_tenants || [];
        systemInfo.value = res.data?.system_info || {};
    });

    onMounted(fetchDashboard);

    return {
        metrics,
        planStats,
        recentTenants,
        systemInfo,
        isLoading,
        hasLoaded,
        error,
        errorMessage,
        statusCode,
        canRetry,
        fetchDashboard,
        retry,
    };
}
