import { reactive, ref } from 'vue';
import api from '../Services/api';
import { useTrans } from './useTrans';

const emptyForm = () => ({
    contact_name: '',
    phone: '',
    email: '',
    shop_code: '',
    reason: '',
    confirm: false,
    // Honeypot: hidden from people, bots fill it.
    website: '',
});

/**
 * MOB-05: the public "delete my account and data" request form.
 * POST /api/v1/public/account-deletion-requests (no auth, no tenant, throttled).
 */
export function useAccountDeletionRequest() {
    const { t } = useTrans();

    const form = reactive(emptyForm());
    const errors = ref({});
    const formError = ref('');
    const isSubmitting = ref(false);
    const successMessage = ref('');

    const submit = async () => {
        if (isSubmitting.value) return;
        isSubmitting.value = true;
        errors.value = {};
        formError.value = '';
        try {
            const res = await api.post('/public/account-deletion-requests', { ...form });
            successMessage.value = res.data?.message || t('account_deletion.form.success_title');
            Object.assign(form, emptyForm());
        } catch (e) {
            const status = e.response?.status;
            if (status === 422) {
                errors.value = e.response?.data?.errors || {};
            } else if (status === 429) {
                formError.value = t('account_deletion.form.too_many');
            } else {
                formError.value = e.userMessage || t('account_deletion.form.error_title');
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    return { form, errors, formError, isSubmitting, successMessage, submit };
}
