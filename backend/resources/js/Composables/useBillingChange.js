import { computed, onBeforeUnmount, ref, watch } from 'vue';
import api from '@/Services/api';
import { notifyError, notifySuccess } from '@/helpers/alert';
import { trans } from '@/helpers/trans';
import {
    CHANGE_ACTIONS,
    canPreview,
    changeInvoiceBody,
    changeOutcome,
    ctaKey,
    currentCycle,
    defaultAction,
    isSubscribeFlow,
    previewQuery,
    showsActionSwitch,
    upgradeCandidates,
} from '@/helpers/billingChange';

/**
 * #232 UI §1: state of the /billing upgrade / renew panel. The price preview is always the server's
 * (GET /billing/change-preview); nothing is computed here. A newer request wins over an older one,
 * and the previous numbers stay while a new preview loads.
 * `submit()` issues the invoice (POST /billing/change-invoice) and returns the mapped outcome
 * (see `changeOutcome`) so the page can open the payment screen or re-read its state.
 * @param {import('vue').Ref<object|null>} subscription  GET /billing/subscription payload
 */
export function useBillingChange(subscription) {
    const cycle = computed(() => currentCycle(subscription.value));
    const candidates = computed(() => upgradeCandidates(subscription.value, cycle.value));
    const subscribeFlow = computed(() => isSubscribeFlow(subscription.value));
    const switchVisible = computed(() => showsActionSwitch(subscription.value));

    const action = ref(defaultAction(subscription.value));
    const planId = ref(candidates.value[0]?.id ?? null);
    const renew = ref(false);

    const preview = ref(null);
    const previewLoading = ref(false);
    const previewFailed = ref(false);
    const previewMessage = ref('');
    const submitting = ref(false);
    // The request was filed for a founder-price review: the amber "held" card replaces the action area.
    const heldSubmitted = ref(false);
    let sequence = 0;

    const plan = computed(() => candidates.value.find((p) => p.id === planId.value) || null);
    const isUpgrade = computed(() => action.value === CHANGE_ACTIONS.UPGRADE);
    const onTopPlan = computed(() => !subscribeFlow.value && candidates.value.length === 0);
    const held = computed(() => preview.value?.held_for_price_review === true);
    const openInvoice = computed(() => preview.value?.open_invoice || null);
    const graceLocked = computed(() => isUpgrade.value && subscription.value?.tenant_status?.temporary_access === true);
    const trialDaysPending = computed(() => preview.value?.trial_days_policy === 'pending');
    const ctaLabelKey = computed(() =>
        ctaKey({ held: held.value, action: action.value, subscribe: subscribeFlow.value })
    );

    const queryState = () => ({
        action: action.value,
        planId: planId.value,
        cycle: cycle.value,
        renew: renew.value,
        subscribe: subscribeFlow.value,
    });

    const loadPreview = async () => {
        const query = queryState();
        if (!canPreview(query)) {
            sequence += 1;
            preview.value = null;
            previewLoading.value = false;
            previewFailed.value = false;
            return;
        }
        const mine = ++sequence;
        previewLoading.value = true;
        previewFailed.value = false;
        previewMessage.value = '';
        try {
            const res = await api.get('/billing/change-preview', { params: previewQuery(query) });
            if (mine !== sequence) return;
            preview.value = res.data.data;
        } catch (e) {
            if (mine !== sequence) return;
            preview.value = null;
            previewFailed.value = true;
            previewMessage.value = e.response?.status === 422 ? e.userMessage || '' : '';
        } finally {
            if (mine === sequence) previewLoading.value = false;
        }
    };

    // A refreshed subscription can change the candidates (a plan became current): keep the choice valid.
    watch(
        candidates,
        (list) => {
            if (!list.some((p) => p.id === planId.value)) planId.value = list[0]?.id ?? null;
            if (switchVisible.value && list.length === 0 && action.value === CHANGE_ACTIONS.UPGRADE) {
                action.value = CHANGE_ACTIONS.RENEW;
            }
        },
        { immediate: true }
    );

    watch([action, planId, renew, cycle], loadPreview, { immediate: true });
    onBeforeUnmount(() => {
        sequence += 1;
    });

    const submit = async () => {
        if (submitting.value || !isUpgrade.value || !planId.value) return null;
        submitting.value = true;
        let outcome;
        try {
            const res = await api.post('/billing/change-invoice', changeInvoiceBody(queryState()));
            outcome = changeOutcome(res);
        } catch (e) {
            outcome = e.response ? changeOutcome(e.response) : { type: 'failed', message: '' };
            if (outcome.type === 'failed') outcome.message = e.userMessage || outcome.message;
        } finally {
            submitting.value = false;
        }
        announce(outcome);
        return outcome;
    };

    const announce = (outcome) => {
        if (outcome.type === 'invoice') notifySuccess(outcome.message);
        else if (outcome.type === 'held') {
            heldSubmitted.value = true;
            notifySuccess(outcome.message);
        } else if (outcome.type === 'duplicate') {
            notifySuccess(trans('subscription.billing.renewal.existing_opened', { number: outcome.number }));
        } else notifyError(outcome.message);
    };

    const selectAction = (next) => {
        if (next === CHANGE_ACTIONS.UPGRADE && onTopPlan.value) return;
        action.value = next;
    };

    return {
        action,
        planId,
        renew,
        cycle,
        candidates,
        plan,
        subscribeFlow,
        switchVisible,
        isUpgrade,
        onTopPlan,
        held,
        openInvoice,
        graceLocked,
        trialDaysPending,
        ctaLabelKey,
        preview,
        previewLoading,
        previewFailed,
        previewMessage,
        submitting,
        heldSubmitted,
        loadPreview,
        selectAction,
        submit,
    };
}
