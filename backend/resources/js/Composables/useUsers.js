import { ref, reactive, onMounted } from 'vue';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useUsers() {
    const { t } = useTrans();

    const users = ref([]);
    const rolesList = ref([]);
    const storesList = ref([]);
    const isLoading = ref(false);
    const error = ref(false);
    const errorMessage = ref('');
    const isSubmitting = ref(false);

    const filters = reactive({
        search: '',
        role: 'all',
        page: 1,
    });

    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    const showModal = ref(false);
    const isEditing = ref(false);
    const editingId = ref(null);

    const form = reactive({
        name: '',
        phone: '',
        email: '',
        password: '',
        role: 'cashier',
        default_store_id: null,
        is_active: true,
    });

    let debounceTimer = null;
    const debouncedFetch = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            filters.page = 1;
            fetchUsers();
        }, 300);
    };

    const updateSearch = (val) => {
        filters.search = val;
        debouncedFetch();
    };

    const updateRoleFilter = (val) => {
        filters.role = val;
        filters.page = 1;
        fetchUsers();
    };

    const updateFormField = (field, val) => {
        form[field] = val;
    };

    const fetchUsers = async () => {
        isLoading.value = true;
        error.value = false;
        errorMessage.value = '';
        try {
            const res = await api.get('/users', { params: filters });
            users.value = res.data?.data || [];
            rolesList.value = res.data?.roles || [];
            storesList.value = res.data?.stores || [];
            pagination.value = res.data?.pagination || pagination.value;
        } catch (e) {
            error.value = true;
            errorMessage.value = e.userMessage || e.message || t('common.error_occurred');
            console.error('Failed to fetch users:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const changePage = (page) => {
        filters.page = page;
        fetchUsers();
    };

    const openCreateModal = () => {
        isEditing.value = false;
        editingId.value = null;
        Object.assign(form, {
            name: '',
            phone: '',
            email: '',
            password: '',
            role: rolesList.value[0]?.id || 'cashier',
            default_store_id: storesList.value[0]?.id || null,
            is_active: true,
            has_pos_pin: false,
        });
        showModal.value = true;
    };

    const openEditModal = (u) => {
        isEditing.value = true;
        editingId.value = u.id;
        Object.assign(form, {
            name: u.name,
            phone: u.phone,
            email: u.email || '',
            password: '',
            role: u.primary_role || 'cashier',
            default_store_id: u.default_store_id,
            is_active: !!u.is_active,
            has_pos_pin: !!u.has_pos_pin,
        });
        showModal.value = true;
    };

    const submitForm = async () => {
        isSubmitting.value = true;
        try {
            if (isEditing.value) {
                await api.put(`/users/${editingId.value}`, form);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('users.user_updated_success'),
                    timer: 1500,
                    showConfirmButton: false,
                });
            } else {
                await api.post('/users', form);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('users.user_created_success'),
                    timer: 1500,
                    showConfirmButton: false,
                });
            }
            showModal.value = false;
            fetchUsers();
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('users.user_save_failed'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    const toggleActive = async (u) => {
        try {
            const res = await api.patch(`/users/${u.id}/toggle-active`);
            u.is_active = res.data?.is_active;
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('users.user_toggle_active_failed'),
            });
        }
    };

    const deleteUser = async (u) => {
        const result = await Swal.fire({
            title: t('users.delete_user_confirm_title', { name: u.name }),
            text: t('users.delete_user_confirm_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#334155',
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/users/${u.id}`);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('users.user_deleted_success'),
                    timer: 1500,
                    showConfirmButton: false,
                });
                fetchUsers();
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: t('common.error'),
                    text: e.response?.data?.message || t('users.user_delete_failed'),
                });
            }
        }
    };

    const unlockLogin = async (u) => {
        const result = await Swal.fire({
            title: t('users.unlock_login_confirm', { name: u.name }),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
        });

        if (!result.isConfirmed) return;

        try {
            const res = await api.post(`/users/${u.id}/unlock-login`);
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: res.data?.message || t('users.unlock_login_success'),
                timer: 1500,
                showConfirmButton: false,
            });
            fetchUsers();
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('users.unlock_login_failed'),
            });
        }
    };

    const updateUserPosPin = async (userId, pin) => {
        isSubmitting.value = true;
        try {
            const res = await api.put(`/users/${userId}/pos-pin`, { pin });
            const hasPin = res.data?.has_pos_pin ?? pin !== null;
            form.has_pos_pin = hasPin;
            const targetUser = users.value.find((u) => u.id === userId);
            if (targetUser) {
                targetUser.has_pos_pin = hasPin;
            }
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: res.data?.message || t('pos.pos_pin_updated'),
                timer: 1500,
                showConfirmButton: false,
            });
            return true;
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('common.error'),
            });
            return false;
        } finally {
            isSubmitting.value = false;
        }
    };

    const clearUserPosPin = async (userId) => {
        const result = await Swal.fire({
            title: t('users.pos_pin_clear_confirm_title'),
            text: t('users.pos_pin_clear_confirm_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#334155',
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
        });

        if (!result.isConfirmed) return false;

        const success = await updateUserPosPin(userId, null);
        if (success) {
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('users.pos_pin_cleared_success'),
                timer: 1500,
                showConfirmButton: false,
            });
        }
        return success;
    };

    const updateEditingUserPosPin = (pin) => {
        if (!editingId.value) return false;
        return updateUserPosPin(editingId.value, pin);
    };

    const clearEditingUserPosPin = () => {
        if (!editingId.value) return false;
        return clearUserPosPin(editingId.value);
    };

    onMounted(() => {
        fetchUsers();
    });

    return {
        error,
        errorMessage,
        users,
        rolesList,
        storesList,
        isLoading,
        isSubmitting,
        filters,
        pagination,
        showModal,
        isEditing,
        form,
        updateSearch,
        updateRoleFilter,
        updateFormField,
        fetchUsers,
        changePage,
        openCreateModal,
        openEditModal,
        submitForm,
        toggleActive,
        deleteUser,
        unlockLogin,
        updateUserPosPin,
        clearUserPosPin,
        updateEditingUserPosPin,
        clearEditingUserPosPin,
    };
}
