import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/api';
import { useAuthStore } from '../stores/auth';
import { useTrans } from './useTrans';
import { confirmDialog, notifyError } from '../helpers/alert';

export const IMPORT_TYPES = {
    items: { permission: 'items.create', listPath: '/items' },
    customers: { permission: 'customers.manage', listPath: '/customers' },
    suppliers: { permission: 'suppliers.manage', listPath: '/suppliers' },
    categories: { permission: 'items.create', listPath: '/categories' },
};

const ACCEPTED_EXTENSIONS = ['xlsx', 'csv'];

export function useImport() {
    const { t } = useTrans();
    const route = useRoute();
    const authStore = useAuthStore();

    const step = ref('type'); // type | upload | preview | result
    const type = ref(null);
    const file = ref(null);
    const preview = ref(null);
    const result = ref(null);
    const filter = ref('all');
    const skipInvalid = ref(false);
    const isPreviewing = ref(false);
    const isCommitting = ref(false);
    const isDownloading = ref(false);
    const notice = ref('');

    const allowedTypes = computed(() =>
        Object.keys(IMPORT_TYPES).filter((key) => authStore.can(IMPORT_TYPES[key].permission))
    );

    const activeStore = computed(() => {
        const store = authStore.currentStore;
        const stored = localStorage.getItem('current_store_id');
        if (!store?.id || stored === 'all') return null;
        return store;
    });

    const summary = computed(() => preview.value?.summary || { total: 0, valid: 0, invalid: 0, duplicates: 0 });
    const importableCount = computed(() => summary.value.valid);

    const visibleRows = computed(() => {
        const rows = preview.value?.rows || [];
        return filter.value === 'all' ? rows : rows.filter((row) => row.status === filter.value);
    });

    const needsSkipConfirmation = computed(() => summary.value.invalid > 0);
    const canCommit = computed(
        () => importableCount.value > 0 && (!needsSkipConfirmation.value || skipInvalid.value) && !isCommitting.value
    );

    const selectType = (key) => {
        if (allowedTypes.value.includes(key)) type.value = key;
    };

    const goToUpload = () => {
        if (!type.value || !activeStore.value) return;
        notice.value = '';
        step.value = 'upload';
    };

    const initFromRoute = () => {
        const wanted = String(route.query.type || '');
        if (allowedTypes.value.includes(wanted)) {
            type.value = wanted;
            if (activeStore.value) step.value = 'upload';
        }
    };

    const backToType = () => {
        file.value = null;
        preview.value = null;
        step.value = 'type';
    };

    const backToUpload = (message = '') => {
        file.value = null;
        preview.value = null;
        skipInvalid.value = false;
        filter.value = 'all';
        notice.value = message;
        step.value = 'upload';
    };

    const downloadTemplate = async () => {
        if (!type.value || isDownloading.value) return;
        isDownloading.value = true;
        try {
            const response = await api.get(`/imports/${type.value}/template`, { responseType: 'blob' });
            const url = URL.createObjectURL(response.data);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${type.value}-import-template.xlsx`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        } catch {
            notifyError(t('import.ui.template_failed'));
        } finally {
            isDownloading.value = false;
        }
    };

    const uploadFile = async (selected) => {
        if (!selected || isPreviewing.value) return;
        const extension = selected.name.split('.').pop().toLowerCase();
        if (!ACCEPTED_EXTENSIONS.includes(extension)) {
            notice.value = t('import.ui.invalid_file_type');
            return;
        }
        notice.value = '';
        file.value = selected;
        isPreviewing.value = true;
        try {
            const body = new FormData();
            body.append('file', selected);
            const { data } = await api.post(`/imports/${type.value}/preview`, body, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            preview.value = data.data;
            skipInvalid.value = false;
            filter.value = 'all';
            step.value = 'preview';
        } catch (error) {
            file.value = null;
            notice.value = error.userMessage || t('import.ui.preview_failed');
        } finally {
            isPreviewing.value = false;
        }
    };

    const buildConfirmText = () => {
        const lines = [
            t('import.ui.confirm_text', {
                valid: summary.value.valid,
                invalid: skipInvalid.value ? summary.value.invalid : 0,
                duplicates: summary.value.duplicates,
            }),
        ];
        if (type.value === 'items' && activeStore.value) {
            lines.push(t('import.ui.confirm_stock', { store: activeStore.value.name }));
        }
        return lines.join('\n');
    };

    const commit = async () => {
        if (!canCommit.value) return;
        const confirmed = await confirmDialog({
            title: t('import.ui.confirm_title'),
            text: buildConfirmText(),
            confirmButtonText: t('import.ui.confirm_button'),
            icon: 'question',
        });
        if (!confirmed) return;

        isCommitting.value = true;
        try {
            const { data } = await api.post(`/imports/${type.value}/commit`, {
                token: preview.value.token,
                skip_invalid: skipInvalid.value,
            });
            result.value = data.data;
            step.value = 'result';
        } catch (error) {
            if (error.response?.status === 404) {
                backToUpload(t('import.ui.session_expired'));
            } else if (error.response?.status !== 403) {
                notifyError(t('common.error'), error.userMessage);
            }
        } finally {
            isCommitting.value = false;
        }
    };

    const restart = () => {
        result.value = null;
        backToUpload();
    };

    return {
        step,
        type,
        file,
        preview,
        result,
        filter,
        skipInvalid,
        isPreviewing,
        isCommitting,
        isDownloading,
        notice,
        allowedTypes,
        activeStore,
        summary,
        importableCount,
        visibleRows,
        needsSkipConfirmation,
        canCommit,
        listPath: computed(() => (type.value ? IMPORT_TYPES[type.value].listPath : '/')),
        initFromRoute,
        selectType,
        goToUpload,
        backToType,
        backToUpload,
        downloadTemplate,
        uploadFile,
        commit,
        restart,
    };
}
