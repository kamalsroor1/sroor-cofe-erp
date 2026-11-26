<script setup>
import { computed } from 'vue';
import { Crown, ShieldAlert } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import BillingStatusBadge from './BillingStatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useTrans } from '@/Composables/useTrans';
import { toneClass } from '@/helpers/billing';
import { planStatus } from '@/helpers/billingChange';

const props = defineProps({
  data: { type: Object, required: true },
});

defineEmits(['change-plan']);

const { t } = useTrans();
const { formatDate, formatNumber } = useFormatters();

const sub = computed(() => props.data.subscription || {});
const access = computed(() => props.data.tenant_status?.access || 'full');
const isTrial = computed(() => props.data.tenant_status?.value === 'trial');
const endDate = computed(() => (isTrial.value ? sub.value.trial_ends_at : sub.value.ends_at));
const daysLeft = computed(() =>
  sub.value.days_left === null || sub.value.days_left === undefined ? null : Number(sub.value.days_left)
);
const status = computed(() => planStatus(props.data));

const periodLine = computed(() => {
  const days = daysLeft.value;
  if (!endDate.value || days === null) return t('subscription.billing.plan.no_end_date');
  if (days < 0) return t('subscription.billing.plan.overdue_days', { days: formatNumber(Math.abs(days)) });
  return t('subscription.billing.change.period_ends', {
    date: formatDate(endDate.value),
    days: formatNumber(days),
  });
});

const notice = computed(() => {
  if (access.value === 'read_only') return t('subscription.billing.plan.read_only_notice');
  if (access.value === 'blocked') return t('subscription.billing.plan.blocked_notice');
  return '';
});
</script>

<template>
  <section
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-3 font-tajawal"
  >
    <header class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="font-black text-base sm:text-lg text-slate-900 dark:text-white min-w-0">
        {{
          $t('subscription.billing.change.your_plan', {
            plan: data.plan?.name || '',
            cycle: sub.billing_cycle?.label || '',
          })
        }}
      </h2>
      <div class="flex flex-wrap items-center gap-2">
        <span
          v-if="sub.is_founder"
          class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-black"
          :class="toneClass('warning')"
        >
          <Crown class="w-3 h-3" />
          {{ $t('subscription.billing.plan.founder') }}
        </span>
        <BillingStatusBadge :label="$t(`subscription.billing.change.status.${status.key}`)" :tone="status.tone" />
      </div>
    </header>

    <div
      v-if="notice"
      class="flex items-start gap-2 rounded-2xl border p-3 text-xs font-bold leading-relaxed"
      :class="toneClass('danger')"
      role="alert"
    >
      <ShieldAlert class="w-4 h-4 shrink-0 mt-0.5" />
      <span>{{ notice }}</span>
    </div>

    <p class="text-sm font-bold text-slate-600 dark:text-slate-300">{{ periodLine }}</p>

    <!-- Add-on requests (and a yearly plan request) still go through the request form. -->
    <BaseButton variant="secondary" @click="$emit('change-plan')">
      {{ $t('subscription.billing.plan.change_plan') }}
    </BaseButton>
  </section>
</template>
