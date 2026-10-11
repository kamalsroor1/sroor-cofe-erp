import { ref, onMounted } from 'vue';
import api from '../Services/centralApi';

/**
 * Platform dashboard data (GET /super-admin/dashboard). The platform branding form moved to
 * its own page (/super-admin/settings, usePlatformSettings).
 */
export function useSuperAdminDashboard() {
    const metrics = ref({});
    const planStats = ref([]);
    const recentTenants = ref([]);
    const systemInfo = ref({});
    const isLoading = ref(false);

    const fetchDashboard = async () => {
        isLoading.value = true;
        try {
            const res = await api.get('/super-admin/dashboard');
            metrics.value = res.data?.metrics || {};
            planStats.value = res.data?.plan_stats || [];
            recentTenants.value = res.data?.recent_tenants || [];
            systemInfo.value = res.data?.system_info || {};
        } catch (e) {
            console.error('Failed to load super admin dashboard:', e);
        } finally {
            isLoading.value = false;
        }
    };

    onMounted(fetchDashboard);

    return {
        metrics,
        planStats,
        recentTenants,
        systemInfo,
        isLoading,
        fetchDashboard,
    };
}
