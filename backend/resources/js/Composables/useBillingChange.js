import { computed, onBeforeUnmount, ref, watch } from 'vue';
import api from '@/Services/api';
import {
    CHANGE_ACTIONS,
    canPreview,
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

    const loadPreview = async () => {
        const query = { action: action.value, planId: planId.value, cycle: cycle.value, renew: renew.value };
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
        loadPreview,
        selectAction,
    };
}
