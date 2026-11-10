<script setup>
import { computed } from 'vue';
import { CalendarClock, CreditCard, Crown, RefreshCw, Repeat, ShieldAlert } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import BillingStatusBadge from './BillingStatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useTrans } from '@/Composables/useTrans';
import { subscriptionTone, toneClass } from '@/helpers/billing';

const props = defineProps({
  data: { type: Object, required: true },
  busy: { type: Boolean, default: false },
});

defineEmits(['renew', 'change-plan']);

const { t } = useTrans();
const { formatDate, formatNumber } = useFormatters();

const sub = computed(() => props.data.subscription || {});
const access = computed(() => props.data.tenant_status?.access || 'full');
const isTrial = computed(() => sub.value.status === 'trialing' || sub.value.status === 'trial');
const endDate = computed(() => (isTrial.value ? sub.value.trial_ends_at : sub.value.ends_at));
const daysLeft = computed(() =>
  sub.value.days_left === null || sub.value.days_left === undefined ? null : Number(sub.value.days_left)
);
const isDanger = computed(
  () => access.value !== 'full' || sub.value.status === 'past_due' || (daysLeft.value !== null && daysLeft.value < 0)
);
const isWarning = computed(() => !isDanger.value && daysLeft.value !== null && daysLeft.value < 7);

const countdownTone = computed(() => (isDanger.value ? 'danger' : isWarning.value ? 'warning' : 'success'));
const countdownText = computed(() => {
  const days = daysLeft.value;
  if (days === null) return t('subscription.billing.plan.no_end_date');
  if (days < 0) return t('subscription.billing.plan.overdue_days', { days: formatNumber(Math.abs(days)) });
  if (days === 0) return t('subscription.billing.plan.expires_today');
  if (days === 1) return t('subscription.billing.plan.day_left_one');
  return t('subscription.billing.plan.days_left', { days: formatNumber(days) });
});

const cycleLabel = computed(() =>
  sub.value.billing_cycle ? t(`billing.billing_cycle.${sub.value.billing_cycle}`) : '-'
);
const notice = computed(() => {
  if (access.value === 'read_only') return t('subscription.billing.plan.read_only_notice');
  if (access.value === 'blocked') return t('subscription.billing.plan.blocked_notice');
  return '';
});
</script>

<template>
  <section
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal"
  >
    <header class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2.5">
        <CreditCard class="w-5 h-5 text-theme-primary" />
        <h2 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">
          {{ $t('subscription.billing.plan.card_title') }}
        </h2>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <BillingStatusBadge
          :label="data.tenant_status?.label || ''"
          :tone="access === 'full' ? subscriptionTone(sub.status) : 'danger'"
        />
        <span
          v-if="sub.is_founder"
          class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-black"
          :class="toneClass('warning')"
        >
          <Crown class="w-3 h-3" />
          {{ $t('subscription.billing.plan.founder') }}
        </span>
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

    <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3 min-w-0">
        <dt class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.plan.plan') }}
        </dt>
        <dd class="mt-1 text-sm font-black text-slate-900 dark:text-white truncate">{{ data.plan?.name }}</dd>
      </div>
      <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3 min-w-0">
        <dt class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.plan.cycle') }}
        </dt>
        <dd class="mt-1 text-sm font-black text-slate-900 dark:text-white flex items-center gap-1.5">
          <Repeat class="w-3.5 h-3.5 text-slate-400" />
          {{ cycleLabel }}
        </dd>
      </div>
      <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3 min-w-0">
        <dt class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
          {{ isTrial ? $t('subscription.billing.plan.trial_ends_at') : $t('subscription.billing.plan.ends_at') }}
        </dt>
        <dd class="mt-1 text-sm font-black text-slate-900 dark:text-white flex items-center gap-1.5">
          <CalendarClock class="w-3.5 h-3.5 text-slate-400" />
          {{ endDate ? formatDate(endDate) : $t('subscription.billing.plan.no_end_date') }}
        </dd>
      </div>
      <div class="rounded-2xl border p-3 min-w-0" :class="toneClass(countdownTone)">
        <dt class="text-[11px] font-bold opacity-80">
          {{ $t('subscription.billing.plan.open_invoices', { count: formatNumber(data.open_invoices_count || 0) }) }}
        </dt>
        <dd class="mt-1 text-sm font-black">{{ countdownText }}</dd>
      </div>
    </dl>

    <div class="flex flex-wrap gap-2">
      <BaseButton variant="primary" :icon="RefreshCw" :loading="busy" @click="$emit('renew')">
        {{ $t('subscription.billing.plan.request_renewal') }}
      </BaseButton>
      <BaseButton variant="secondary" @click="$emit('change-plan')">
        {{ $t('subscription.billing.plan.change_plan') }}
      </BaseButton>
    </div>
  </section>
</template>
