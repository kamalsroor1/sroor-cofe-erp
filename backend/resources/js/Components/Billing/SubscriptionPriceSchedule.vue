<script setup>
import { computed } from 'vue';
import { BellRing, TrendingUp } from 'lucide-vue-next';
import { useFormatters } from '@/Composables/useFormatters';
import { toneClass } from '@/helpers/billing';

const props = defineProps({
  /** price_schedule of the subscription payload (step, current_price, next_step, next_price, notice_due...). */
  schedule: { type: Object, default: null },
});

const { formatMoney, formatDate } = useFormatters();

const STEP_TONES = { founder: 'warning', transition: 'info', list: 'neutral' };
const stepTone = computed(() => STEP_TONES[props.schedule?.step?.value] || 'neutral');
const hasNext = computed(() => Boolean(props.schedule?.next_step && props.schedule?.next_step_at));
</script>

<template>
  <section
    v-if="schedule"
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal"
    data-testid="price-schedule"
  >
    <header class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2.5">
        <TrendingUp class="w-5 h-5 text-theme-primary" />
        <h2 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">
          {{ $t('subscription.billing.schedule.title') }}
        </h2>
      </div>
      <span
        class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-black"
        :class="toneClass(stepTone)"
      >
        {{ schedule.step.label }}
      </span>
    </header>

    <div
      v-if="schedule.notice_due && hasNext"
      class="flex items-start gap-2 rounded-2xl border p-3 text-xs font-bold leading-relaxed"
      :class="toneClass('warning')"
      role="alert"
      data-testid="price-notice"
    >
      <BellRing class="w-4 h-4 shrink-0 mt-0.5" />
      <span>
        {{
          $t('subscription.billing.schedule.notice', {
            date: formatDate(schedule.next_step_at),
            price: formatMoney(schedule.next_price),
          })
        }}
      </span>
    </div>

    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3 min-w-0">
        <dt class="text-xs font-bold text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.schedule.current_price') }}
        </dt>
        <dd class="mt-1 text-sm font-black text-slate-900 dark:text-white">
          {{ formatMoney(schedule.current_price) }}
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
            / {{ $t(`subscription.billing.schedule.per_${schedule.billing_cycle}`) }}
          </span>
        </dd>
        <dd class="text-xs font-medium text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.schedule.excl_vat') }}
        </dd>
      </div>
      <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3 min-w-0">
        <dt class="text-xs font-bold text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.schedule.list_price') }}
        </dt>
        <dd class="mt-1 text-sm font-black text-slate-900 dark:text-white">{{ formatMoney(schedule.list_price) }}</dd>
      </div>
      <div v-if="hasNext" class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3 min-w-0">
        <dt class="text-xs font-bold text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.schedule.next_step', { step: schedule.next_step.label }) }}
        </dt>
        <dd class="mt-1 text-sm font-black text-slate-900 dark:text-white">{{ formatMoney(schedule.next_price) }}</dd>
        <dd class="text-xs font-medium text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.schedule.from_date', { date: formatDate(schedule.next_step_at) }) }}
        </dd>
      </div>
    </dl>

    <p v-if="hasNext" class="text-xs font-bold text-slate-600 dark:text-slate-300">
      {{
        $t('subscription.billing.schedule.next_sentence', {
          date: formatDate(schedule.next_step_at),
          price: formatMoney(schedule.next_price),
        })
      }}
    </p>
  </section>
</template>
