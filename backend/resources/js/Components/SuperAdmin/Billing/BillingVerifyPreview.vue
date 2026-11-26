<script setup>
import { computed } from 'vue';
import { AlertTriangle } from 'lucide-vue-next';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import { isPositiveMoney } from '../../../helpers/billingReview';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  preview: { type: Object, required: true },
  currency: { type: String, default: '' },
});

const { formatMoney, formatDateTime } = useFormatters();

const data = computed(() => props.preview.data);
const loading = computed(() => props.preview.state === 'loading');
const money = (amount) => `${formatMoney(amount, 3)} ${props.currency}`.trim();
const overpaid = computed(() => isPositiveMoney(data.value?.overpaid_amount));
const owes = computed(() => isPositiveMoney(data.value?.remaining_due));
const showTax = computed(() => isPositiveMoney(data.value?.vat));
const changed = computed(() => data.value && data.value.previous_total !== data.value.total);
</script>

<template>
  <section
    v-if="preview.state !== 'idle'"
    class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950"
    aria-live="polite"
    :aria-busy="loading"
    data-testid="verify-preview"
  >
    <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('billing.console.verify.preview_title') }}</h3>

    <p
      v-if="preview.state === 'error'"
      role="alert"
      class="rounded-lg border border-rose-500 bg-rose-50 p-2 text-xs font-bold text-rose-900 dark:bg-rose-500/10 dark:text-rose-200"
    >
      {{ $t('billing.console.verify.preview_failed') }}
    </p>

    <div v-else-if="!data" class="space-y-2" role="status" :aria-label="$t('billing.console.verify.preview_loading')">
      <Skeleton height="h-4" />
      <Skeleton height="h-4" width="w-2/3" />
      <Skeleton height="h-6" width="w-1/2" />
    </div>

    <div
      v-else
      class="space-y-2 text-xs font-bold text-slate-700 dark:text-slate-300"
      :class="{ 'opacity-50': loading }"
    >
      <p>
        {{ data.repriced ? $t('billing.console.verify.preview_repriced') : $t('billing.console.verify.preview_fixed') }}
      </p>
      <p v-if="data.repriced" class="text-slate-500 dark:text-slate-400">
        {{
          $t(
            data.basis === 'transfer'
              ? 'billing.console.verify.preview_basis_transfer'
              : 'billing.console.verify.preview_basis_approval'
          )
        }}
        <span v-if="data.effective_moment"> - {{ formatDateTime(data.effective_moment) }}</span>
      </p>
      <p v-if="data.repriced && data.remaining_days !== null">
        {{
          $t('billing.console.verify.preview_remaining_days', { days: data.remaining_days, period: data.period_days })
        }}
      </p>

      <ul class="space-y-1">
        <li v-for="(line, index) in data.lines" :key="index" class="flex items-start justify-between gap-3">
          <span class="min-w-0">{{ line.description }}</span>
          <span class="shrink-0 font-mono" dir="ltr">{{ money(line.amount) }}</span>
        </li>
      </ul>

      <dl class="space-y-1 border-t border-slate-200 pt-2 dark:border-slate-800">
        <div v-if="showTax" class="flex justify-between gap-3">
          <dt>{{ $t('billing.console.verify.preview_vat', { rate: data.tax_rate }) }}</dt>
          <dd class="font-mono" dir="ltr">{{ money(data.vat) }}</dd>
        </div>
        <div class="flex justify-between gap-3 text-base font-black text-slate-900 dark:text-white">
          <dt>{{ $t('billing.console.verify.preview_total') }}</dt>
          <dd class="font-mono" dir="ltr">{{ money(data.total) }}</dd>
        </div>
        <div v-if="changed" class="flex justify-between gap-3 text-slate-500 dark:text-slate-400">
          <dt>{{ $t('billing.console.verify.preview_previous_total') }}</dt>
          <dd class="font-mono line-through" dir="ltr">{{ money(data.previous_total) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt>{{ $t('billing.console.verify.preview_paid') }}</dt>
          <dd class="font-mono" dir="ltr">{{ money(data.paid_amount) }}</dd>
        </div>
      </dl>

      <div
        v-if="overpaid"
        role="status"
        class="space-y-1 rounded-lg border border-amber-500 bg-amber-50 p-2 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
        data-testid="verify-overpaid"
      >
        <p class="flex items-center justify-between gap-3 text-sm font-black">
          <span class="flex items-center gap-1.5">
            <AlertTriangle class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ $t('billing.console.verify.preview_overpaid') }}
          </span>
          <span class="font-mono" dir="ltr">{{ money(data.overpaid_amount) }}</span>
        </p>
        <p>{{ $t('billing.console.verify.preview_overpaid_note') }}</p>
      </div>
      <div
        v-if="owes"
        role="status"
        class="rounded-lg border border-amber-500 bg-amber-50 p-2 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
      >
        <p class="flex justify-between gap-3 text-sm font-black">
          <span>{{ $t('billing.console.verify.preview_remaining_due') }}</span>
          <span class="font-mono" dir="ltr">{{ money(data.remaining_due) }}</span>
        </p>
        <p>{{ $t('billing.console.verify.preview_partial_note') }}</p>
      </div>
    </div>
  </section>
</template>
