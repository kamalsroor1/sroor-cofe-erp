import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/api';
import { useTrans } from './useTrans';
import { usePublicPlans } from './usePublicPlans';

export const SIGNUP_BUSINESS_TYPES = Object.freeze(['retail', 'wholesale', 'weighted', 'other']);

const emptyForm = () => ({
    business_name: '',
    contact_name: '',
    phone: '',
    email: '',
    city: '',
    business_type: '',
    plan_code: '',
    preferred_subdomain: '',
    notes: '',
    terms_accepted: false,
    website: '', // honeypot: real users never see it, so it must stay empty
});

/**
 * Public assisted-signup form (POST /public/signup-requests). 201 -> thank-you message from the
 * server, 422 -> inline field errors, 429 -> translated "too many attempts".
 */
export function useSignupRequest() {
    const { t } = useTrans();
    const route = useRoute();
    const { plans, brand, founder, currency, load: loadPlans, hasError: plansFailed } = usePublicPlans();

    const form = ref(emptyForm());
    const errors = ref({});
    const isSubmitting = ref(false);
    const formError = ref('');
    const successMessage = ref('');

    const businessTypeOptions = computed(() =>
        SIGNUP_BUSINESS_TYPES.map((value) => ({ value, label: t(`signup.business_types.${value}`) }))
    );
    const planOptions = computed(() => [
        { value: '', label: t('signup.form.plan_none') },
        ...plans.value.map((plan) => ({ value: plan.code, label: plan.name })),
    ]);

    // ?plan=<code> pre-selects a plan, but only one the public catalog really offers.
    const preselectPlan = () => {
        const wanted = String(route.query.plan || '');
        if (wanted && !form.value.plan_code && plans.value.some((plan) => plan.code === wanted)) {
            form.value.plan_code = wanted;
        }
    };
    watch(plans, preselectPlan);

    // The plan shown in the page aside; null while none is chosen ("not sure yet").
    const selectedPlan = computed(() => plans.value.find((plan) => plan.code === form.value.plan_code) || null);

    const buildPayload = () => {
        const payload = {};
        Object.entries(form.value).forEach(([key, value]) => {
            const text = typeof value === 'string' ? value.trim() : value;
            if (key === 'website' || key === 'terms_accepted' || text !== '') payload[key] = text;
        });
        return payload;
    };

    const submit = async () => {
        if (isSubmitting.value) return;
        isSubmitting.value = true;
        errors.value = {};
        formError.value = '';
        try {
            const res = await api.post('/public/signup-requests', buildPayload());
            successMessage.value = res.data?.message || t('signup.submitted');
        } catch (e) {
            const status = e.response?.status;
            if (status === 422) {
                errors.value = e.response.data?.errors || {};
            } else if (status === 429) {
                formError.value = t('signup.form.throttled');
            } else {
                formError.value = e.userMessage || t('signup.form.failed');
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    return {
        form,
        errors,
        isSubmitting,
        formError,
        successMessage,
        businessTypeOptions,
        planOptions,
        plansFailed,
        selectedPlan,
        founder,
        currency,
        brand,
        loadPlans,
        submit,
    };
}
