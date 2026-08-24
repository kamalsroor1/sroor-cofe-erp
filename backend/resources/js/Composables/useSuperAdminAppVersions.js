import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useTrans } from './useTrans';
import DarkSwal from '../helpers/alert';

export function useSuperAdminAppVersions() {
    const { t } = useTrans();

    const versions = ref([]);
    const summary = ref({});
    const isLoading = ref(false);
    const isCreateModalOpen = ref(false);
    const isSubmitting = ref(false);
    const selectedApkFile = ref(null);

    const form = ref({
        platform: 'android',
        version_name: '',
        version_code: 2,
        min_version_code: 1,
        is_force_update: false,
        release_notes_ar: '',
        is_active: true,
    });

    const handleFileUpload = (e) => {
        selectedApkFile.value = e.target.files[0] || null;
    };

    const fetchVersions = async () => {
        isLoading.value = true;
        try {
            const res = await api.get('/super-admin/app-versions');
            versions.value = res.data?.versions?.data || [];
            summary.value = res.data?.summary || {};
        } catch (e) {
            console.error('Failed to load app versions:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const openCreateModal = () => {
        const nextCode = versions.value.length ? Math.max(...versions.value.map(v => v.version_code)) + 1 : 2;
        form.value = {
            platform: 'android',
            version_name: `1.${nextCode - 1}.0`,
            version_code: nextCode,
            min_version_code: 1,
            is_force_update: false,
            release_notes_ar: '• تحسينات عامة في الأداء والسرعة واستقرار النظام.',
            is_active: true,
        };
        selectedApkFile.value = null;
        isCreateModalOpen.value = true;
    };

    const updateFormField = (field, val) => {
        form.value[field] = val;
    };

    const submitCreateVersion = async () => {
        isSubmitting.value = true;
        try {
            const formData = new FormData();
            formData.append('platform', form.value.platform);
            formData.append('version_name', form.value.version_name);
            formData.append('version_code', form.value.version_code);
            formData.append('min_version_code', form.value.min_version_code);
            formData.append('is_force_update', form.value.is_force_update ? '1' : '0');
            formData.append('release_notes_ar', form.value.release_notes_ar);
            formData.append('is_active', form.value.is_active ? '1' : '0');

            if (selectedApkFile.value) {
                formData.append('apk_file', selectedApkFile.value);
            }

            await api.post('/super-admin/app-versions', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            DarkSwal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('super.release_published_success'),
                timer: 1500,
                showConfirmButton: false,
            });

            isCreateModalOpen.value = false;
            fetchVersions();
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('super.release_publish_failed'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    const toggleActive = async (v) => {
        try {
            await api.patch(`/super-admin/app-versions/${v.id}/toggle-active`);
            v.is_active = !v.is_active;
        } catch (e) {
            DarkSwal.fire({ icon: 'error', title: t('common.error'), text: t('super.status_update_failed') });
        }
    };

    const deleteVersion = async (v) => {
        const result = await DarkSwal.fire({
            title: t('super.delete_version_confirm_title'),
            text: t('super.delete_version_confirm_text', { version: v.version_name }),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
            confirmButtonColor: '#ef4444',
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/super-admin/app-versions/${v.id}`);
                DarkSwal.fire({ icon: 'success', title: t('common.success'), text: t('super.version_deleted_success') });
                fetchVersions();
            } catch (e) {
                DarkSwal.fire({ icon: 'error', title: t('common.error'), text: t('super.version_delete_failed') });
            }
        }
    };

    onMounted(() => {
        fetchVersions();
    });

    return {
        versions,
        summary,
        isLoading,
        isCreateModalOpen,
        isSubmitting,
        form,
        fetchVersions,
        openCreateModal,
        updateFormField,
        handleFileUpload,
        submitCreateVersion,
        toggleActive,
        deleteVersion,
    };
}
