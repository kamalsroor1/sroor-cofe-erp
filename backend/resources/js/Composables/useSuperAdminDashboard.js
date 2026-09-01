import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useAppConfigStore } from '../stores/appConfig';
import { useTrans } from './useTrans';

export function useSuperAdminDashboard() {
    const { t } = useTrans();
    const appConfigStore = useAppConfigStore();

    const metrics = ref({});
    const planStats = ref([]);
    const recentTenants = ref([]);
    const systemInfo = ref({});
    const isLoading = ref(false);

    const platformSettings = ref({
        platform_name: '',
        platform_subtitle: '',
        support_email: '',
        support_phone: ''
    });
    const isSavingSettings = ref(false);
    const saveSuccessMessage = ref('');

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

    const fetchPlatformSettings = async () => {
        try {
            const res = await api.get('/super-admin/settings');
            if (res.data?.data) {
                platformSettings.value = {
                    platform_name: res.data.data.platform_name || '',
                    platform_subtitle: res.data.data.platform_subtitle || '',
                    support_email: res.data.data.support_email || '',
                    support_phone: res.data.data.support_phone || ''
                };
            }
        } catch (e) {
            console.error('Failed to load platform settings:', e);
        }
    };

    const updatePlatformField = (field, val) => {
        platformSettings.value[field] = val;
    };

    const savePlatformSettings = async () => {
        isSavingSettings.value = true;
        saveSuccessMessage.value = '';
        try {
            const res = await api.post('/super-admin/settings', platformSettings.value);
            if (res.data?.success) {
                saveSuccessMessage.value = res.data.message || t('super.platform_settings_saved_success');
                appConfigStore.system.platform_name = platformSettings.value.platform_name;
                appConfigStore.system.company_subtitle = platformSettings.value.platform_subtitle;
                const appName = platformSettings.value.platform_name;
                document.title = `${t('super.super_admin_title')} - ${appName}`;
                setTimeout(() => {
                    saveSuccessMessage.value = '';
                }, 4000);
            }
        } catch (e) {
            console.error('Failed to save platform settings:', e);
        } finally {
            isSavingSettings.value = false;
        }
    };

    onMounted(() => {
        fetchDashboard();
        fetchPlatformSettings();
    });

    return {
        metrics,
        planStats,
        recentTenants,
        systemInfo,
        isLoading,
        platformSettings,
        isSavingSettings,
        saveSuccessMessage,
        fetchDashboard,
        updatePlatformField,
        savePlatformSettings,
    };
}
