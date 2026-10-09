import { ref, onMounted } from 'vue';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useProfile() {
    const { t } = useTrans();

    const isLoading = ref(false);
    const error = ref(false);
    const errorMessage = ref('');
    const isSubmitting = ref(false);

    const form = ref({
        name: '',
        phone: '',
        email: '',
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
        theme_preference: 'dark',
    });

    const updateField = (field, val) => {
        form.value[field] = val;
    };

    const fetchProfile = async () => {
        isLoading.value = true;
        error.value = false;
        errorMessage.value = '';
        try {
            const res = await api.get('/profile');
            const u = res.data?.data || {};
            form.value.name = u.name || '';
            form.value.phone = u.phone || '';
            form.value.email = u.email || '';
            form.value.theme_preference = u.theme_preference || 'dark';
        } catch (e) {
            error.value = true;
            errorMessage.value = e.userMessage || e.message || t('common.error_occurred');
            console.error('Failed to load profile:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const submitProfile = async () => {
        isSubmitting.value = true;
        try {
            await api.put('/profile', form.value);
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('profile.profile_updated_success'),
                timer: 1500,
                showConfirmButton: false,
            });
            form.value.current_password = '';
            form.value.new_password = '';
            form.value.new_password_confirmation = '';
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('common.error'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    onMounted(() => {
        fetchProfile();
    });

    return {
        error,
        errorMessage,
        isLoading,
        isSubmitting,
        form,
        updateField,
        fetchProfile,
        submitProfile,
    };
}
