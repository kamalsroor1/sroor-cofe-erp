<script setup>
import { computed, toRef } from 'vue';
import { ChevronRight, Info, Lock, ReceiptText } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import BillingActionSwitch from './BillingActionSwitch.vue';
import BillingChangePreview from './BillingChangePreview.vue';
import BillingPlanChoice from './BillingPlanChoice.vue';
import SupportContact from './SupportContact.vue';
import { useBillingChange } from '@/Composables/useBillingChange';
import { useFormatters } from '@/Composables/useFormatters';
import { renewTooKey } from '@/helpers/billingChange';

const props = defineProps({
  subscription: { type: Object, required: true },
  // An open plan-change request already exists: a second one is refused by the API.
  hasPendingRequest: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
});

// `outcome` carries the mapped answer of the change-invoice call (invoice / held / duplicate / refused / failed).
const emit = defineEmits(['outcome', 'renew', 'open-invoice']);

const { formatCurrency } = useFormatters();

const {
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
} = useBillingChange(toRef(props, 'subscription'));

const noPlans = computed(() => subscribeFlow.value && candidates.value.length === 0);
const showPlans = computed(() => isUpgrade.value && candidates.value.length > 0);
const showRenewToo = computed(() => isUpgrade.value && !subscribeFlow.value && candidates.value.length > 0);
const upgradeBlocked = computed(() => isUpgrade.value && (graceLocked.value || props.hasPendingRequest));
const canSend = computed(
  () =>
    !!preview.value &&
    !previewLoading.value &&
    !previewFailed.value &&
    !upgradeBlocked.value &&
    !props.busy &&
    !submitting.value &&
    (!isUpgrade.value || !!plan.value)
);

const send = async () => {
  if (!canSend.value) return;
  if (!isUpgrade.value) {
    emit('renew', cycle.value);
    return;
  }
  const outcome = await submit();
  if (outcome) emit('outcome', outcome);
};
</script>

<template>
  <section
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-3 font-tajawal"
  >
    <EmptyState v-if="noPlans" :description="$t('subscription.billing.change.no_plans')">
      <SupportContact :support="subscription.support" />
    </EmptyState>

    <template v-else>
      <h2 v-if="subscribeFlow && !switchVisible" class="text-base font-black text-slate-900 dark:text-white">
        {{ $t('subscription.billing.change.choose_plan_title') }}
      </h2>

      <!-- An open unpaid invoice replaces the action area: no second invoice can be created (409). -->
      <div
        v-if="openInvoice"
        class="rounded-xl border border-amber-500 bg-amber-50 dark:bg-amber-500/10 p-3 space-y-3 text-amber-900 dark:text-amber-200"
        role="status"
      >
        <p class="flex items-start gap-2 text-sm font-black">
          <ReceiptText class="w-5 h-5 shrink-0" />
          <span>
            {{
              $t('subscription.billing.change.open_invoice', {
                number: openInvoice.number,
                amount: formatCurrency(openInvoice.total),
              })
            }}
          </span>
        </p>
        <BaseButton
          variant="primary"
          size="lg"
          full-width
          custom-class="min-h-14"
          :trailing-icon="ChevronRight"
          @click="emit('open-invoice', openInvoice.id)"
        >
          {{ $t('subscription.billing.change.continue_payment') }}
        </BaseButton>
      </div>

      <!-- Filed for a founder-price review: no invoice yet, the pending request card above tracks it. -->
      <div
        v-else-if="heldSubmitted"
        class="rounded-xl border border-amber-500 bg-amber-50 dark:bg-amber-500/10 p-3 space-y-1 text-amber-900 dark:text-amber-200"
        role="status"
      >
        <p class="flex items-start gap-2 text-sm font-black">
          <Lock class="w-5 h-5 shrink-0" />
          <span>{{ $t('subscription.billing.change.held_title') }}</span>
        </p>
        <p class="text-xs font-bold ps-7">{{ $t('subscription.billing.change.founder_note') }}</p>
      </div>

      <template v-else>
        <BillingActionSwitch
          v-if="switchVisible"
          :model-value="action"
          :upgrade-disabled="onTopPlan"
          @update:model-value="selectAction"
        />
        <p v-if="switchVisible && onTopPlan" class="text-xs font-bold text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.change.top_plan') }}
        </p>

        <BillingPlanChoice v-if="showPlans" v-model="planId" :plans="candidates" :cycle="cycle" />

        <button
          v-if="showRenewToo"
          type="button"
          role="switch"
          :aria-checked="renew"
          class="w-full min-h-11 rounded-xl border border-slate-200 dark:border-slate-700 px-3 flex items-center justify-between gap-3 text-sm font-bold text-slate-800 dark:text-slate-200 cursor-pointer text-start"
          @click="renew = !renew"
        >
          <span>{{ $t(`subscription.billing.change.${renewTooKey(cycle)}`) }}</span>
          <span
            class="relative w-10 h-6 shrink-0 rounded-full transition"
            :class="renew ? 'bg-theme-primary' : 'bg-slate-300 dark:bg-slate-600'"
            aria-hidden="true"
          >
            <span
              class="absolute top-0.5 start-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform"
              :class="renew ? 'ltr:translate-x-4 rtl:-translate-x-4' : ''"
            ></span>
          </span>
        </button>

        <BillingChangePreview
          v-if="!held"
          :preview="preview"
          :loading="previewLoading"
          :failed="previewFailed"
          :message="previewMessage"
          @retry="loadPreview"
        />

        <p v-if="trialDaysPending" class="text-xs font-medium text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.change.trial_days_pending') }}
        </p>

        <p
          v-if="held"
          class="flex items-start gap-2 rounded-xl border border-amber-500 bg-amber-50 dark:bg-amber-500/10 p-3 text-sm font-bold text-amber-900 dark:text-amber-200"
          role="status"
        >
          <Lock class="w-4 h-4 shrink-0 mt-0.5" />
          {{ $t('subscription.billing.change.founder_note') }}
        </p>

        <p
          v-if="isUpgrade && graceLocked"
          class="flex items-start gap-2 text-xs font-bold text-slate-600 dark:text-slate-300"
        >
          <Info class="w-4 h-4 shrink-0" />
          {{ $t('subscription.billing.change.locked_during_grace') }}
        </p>

        <BaseButton
          variant="primary"
          size="lg"
          full-width
          custom-class="min-h-14"
          :loading="busy || submitting"
          :disabled="!canSend"
          @click="send"
        >
          {{ $t(`subscription.billing.change.${ctaLabelKey}`) }}
        </BaseButton>
      </template>

      <p class="text-xs font-medium text-slate-400 dark:text-slate-500">
        {{ $t('subscription.billing.change.downgrade_soon') }}
      </p>
    </template>
  </section>
</template>
