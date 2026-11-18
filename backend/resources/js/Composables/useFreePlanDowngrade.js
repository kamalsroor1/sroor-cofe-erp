import { computed, ref } from 'vue';
import api from '@/Services/api';
import { confirmDialog, notifyError, notifySuccess } from '@/helpers/alert';
import { trans } from '@/helpers/trans';
import { useAppConfigStore } from '@/stores/appConfig';
import { useFormatters } from '@/Composables/useFormatters';

const CYCLES = ['monthly', 'yearly'];

/**
 * PLAN-13: "Switch to the free plan at the end of my term". A paid shop asks for the permanent free plan
 * through the plan-change request endpoint; the server stores it as `scheduled` (applied by the daily
 * sweep when the paid term ends, cancellable until then) or applies it at once when no paid term is running.
 * `onChanged` re-reads the subscription summary after the write.
 */
export function useFreePlanDowngrade(subscription, onChanged) {
    const appConfig = useAppConfigStore();
    const { formatDate } = useFormatters();
    const isSubmitting = ref(false);

    const freePlan = computed(() =>
        (subscription.value?.available_plans || []).find((p) => p.is_free && !p.is_current)
    );
    const endsAt = computed(() => subscription.value?.subscription?.ends_at || null);
    // Mirrors the server: a trial, or a term that already ended, has nothing left to wait for.
    const appliesNow = computed(() => {
        if (subscription.value?.tenant_status?.value === 'trial') return true;
        return !endsAt.value || new Date(endsAt.value).getTime() <= Date.now();
    });
    const isOffered = computed(
        () =>
            !!freePlan.value &&
            !subscription.value?.plan?.is_free &&
            !subscription.value?.pending_plan_change &&
            subscription.value?.tenant_status?.access !== 'blocked'
    );

    const cycle = () => {
        const current = subscription.value?.subscription?.billing_cycle?.value;
        return CYCLES.includes(current) ? current : 'monthly';
    };

    const request = async () => {
        if (!freePlan.value || isSubmitting.value) return false;
        const confirmed = await confirmDialog({
            title: trans('subscription.downgrade.confirm_title'),
            text: appliesNow.value
                ? trans('subscription.downgrade.confirm_text_now', { plan: freePlan.value.name })
                : trans('subscription.downgrade.confirm_text_scheduled', {
                      plan: freePlan.value.name,
                      date: formatDate(endsAt.value),
                  }),
            confirmButtonText: trans('subscription.downgrade.confirm_button'),
            cancelButtonText: trans('subscription.downgrade.keep_plan'),
        });
        if (!confirmed) return false;

        isSubmitting.value = true;
        try {
            const res = await api.post('/billing/plan-change-requests', {
                kind: 'plan_change',
                requested_plan_code: freePlan.value.code,
                billing_cycle: cycle(),
            });
            notifySuccess(res.data?.message || trans('subscription.downgrade.requested'));
            // Applied at once: the plan, its features and limits changed, so re-read the app context too.
            if (res.data?.data?.status?.value === 'completed')
                await appConfig.fetchBootstrapContext().catch(() => null);
            await onChanged();
            return true;
        } catch (e) {
            notifyError(e.userMessage);
            await onChanged().catch(() => null);
            return false;
        } finally {
            isSubmitting.value = false;
        }
    };

    return { freePlan, isOffered, appliesNow, isSubmitting, request };
}
