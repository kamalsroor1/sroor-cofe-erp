import { ref, onMounted } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useRoles() {
    const { t } = useTrans();

    const roles = ref([]);
    const selectedRole = ref(null);
    const permissionModules = ref({});
    const activePermissions = ref([]);
    const isLoading = ref(false);
    const isSaving = ref(false);

    const fetchMatrix = async (roleId = null) => {
        isLoading.value = true;
        try {
            const res = await api.get('/roles', { params: { role_id: roleId } });
            const data = res.data?.data || {};
            roles.value = data.roles || [];
            permissionModules.value = data.permission_modules || {};

            if (roleId) {
                selectedRole.value = roles.value.find(r => r.id === roleId) || roles.value[0];
            } else {
                selectedRole.value = data.selected_role || roles.value[0];
            }

            activePermissions.value = [...(selectedRole.value?.permissions || [])];
        } catch (e) {
            console.error('Failed to load roles matrix:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const selectRole = (role) => {
        selectedRole.value = role;
        activePermissions.value = [...(role.permissions || [])];
    };

    const togglePermission = (permKey, isChecked) => {
        if (isChecked) {
            if (!activePermissions.value.includes(permKey)) {
                activePermissions.value.push(permKey);
            }
        } else {
            activePermissions.value = activePermissions.value.filter(k => k !== permKey);
        }
    };

    const toggleModule = (permissionsObj, selectAll) => {
        const keys = Object.keys(permissionsObj);
        if (selectAll) {
            keys.forEach(k => {
                if (!activePermissions.value.includes(k)) {
                    activePermissions.value.push(k);
                }
            });
        } else {
            activePermissions.value = activePermissions.value.filter(k => !keys.includes(k));
        }
    };

    const savePermissions = async () => {
        if (!selectedRole.value || selectedRole.value.name === 'admin') return;

        isSaving.value = true;
        try {
            await api.put(`/roles/${selectedRole.value.id}/permissions`, {
                permissions: activePermissions.value,
            });

            selectedRole.value.permissions = [...activePermissions.value];
            selectedRole.value.permissions_count = activePermissions.value.length;

            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('roles.permissions_saved_success'),
                timer: 1500,
                showConfirmButton: false,
            });
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('roles.permissions_save_failed'),
            });
        } finally {
            isSaving.value = false;
        }
    };

    onMounted(() => {
        fetchMatrix();
    });

    return {
        roles,
        selectedRole,
        permissionModules,
        activePermissions,
        isLoading,
        isSaving,
        fetchMatrix,
        selectRole,
        togglePermission,
        toggleModule,
        savePermissions,
    };
}
