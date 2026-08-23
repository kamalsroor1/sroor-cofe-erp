import { ref, reactive, computed, onMounted } from 'vue';
import api from '../services/api';
import { useTrans } from './useTrans';

export function useActivityLogs() {
    const { t } = useTrans();

    const logs = ref([]);
    const stats = ref({});
    const usersList = ref([]);
    const storesList = ref([]);
    const modulesList = ref({});
    const isLoading = ref(false);
    const selectedLog = ref(null);

    const filters = reactive({
        search: '',
        module: 'all',
        action: 'all',
        user_id: 'all',
        store_id: 'all',
        page: 1,
    });

    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 0,
    });

    const moduleOptions = computed(() => [
        { value: 'all', label: t('activity.all_modules') },
        ...Object.entries(modulesList.value).map(([k, v]) => ({ value: k, label: v }))
    ]);

    const userOptions = computed(() => [
        { value: 'all', label: t('activity.all_users') },
        ...usersList.value.map(u => ({ value: u.id, label: u.name }))
    ]);

    const storeOptions = computed(() => [
        { value: 'all', label: t('activity.all_stores') },
        ...storesList.value.map(s => ({ value: s.id, label: s.name }))
    ]);

    let debounceTimer = null;
    const debouncedFetch = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            filters.page = 1;
            fetchLogs();
        }, 300);
    };

    const updateSearch = (val) => {
        filters.search = val;
        debouncedFetch();
    };

    const updateModule = (val) => {
        filters.module = val;
        filters.page = 1;
        fetchLogs();
    };

    const updateUserId = (val) => {
        filters.user_id = val;
        filters.page = 1;
        fetchLogs();
    };

    const updateStoreId = (val) => {
        filters.store_id = val;
        filters.page = 1;
        fetchLogs();
    };

    const fetchLogs = async () => {
        isLoading.value = true;
        try {
            const res = await api.get('/activity-logs', { params: filters });
            logs.value = res.data?.data || [];
            stats.value = res.data?.stats || {};
            usersList.value = res.data?.users || [];
            storesList.value = res.data?.stores || [];
            modulesList.value = res.data?.modules_list || {};
            pagination.value = res.data?.pagination || pagination.value;
        } catch (e) {
            console.error('Failed to fetch activity logs:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const changePage = (page) => {
        filters.page = page;
        fetchLogs();
    };

    const openDetails = (log) => {
        selectedLog.value = log;
    };

    const closeDetails = () => {
        selectedLog.value = null;
    };

    onMounted(() => {
        fetchLogs();
    });

    return {
        logs,
        stats,
        filters,
        pagination,
        moduleOptions,
        userOptions,
        storeOptions,
        isLoading,
        selectedLog,
        updateSearch,
        updateModule,
        updateUserId,
        updateStoreId,
        fetchLogs,
        changePage,
        openDetails,
        closeDetails,
    };
}
