<script setup>
import { Inbox } from 'lucide-vue-next';
import EmptyState from '../../Common/EmptyState.vue';
import ErrorState from '../../Common/ErrorState.vue';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import BillingReviewChip from './BillingReviewChip.vue';
import { useFormatters } from '../../../Composables/useFormatters';

defineProps({
  payments: { type: Array, default: () => [] },
  selectedId: { type: [Number, String], default: null },
  total: { type: Number, default: 0 },
  isLoading: { type: Boolean, default: false },
  loadError: { type: String, default: '' },
  now: { type: Number, default: () => Date.now() },
});

defineEmits(['select', 'retry']);

const { formatMoney } = useFormatters();
</script>

<template>
  <section
    class="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"
    :aria-label="$t('billing.console.verify.queue_title', { count: total })"
  >
    <h2 class="mb-3 px-1 text-sm font-black text-slate-900 dark:text-white">
      {{ $t('billing.console.verify.queue_title', { count: total }) }}
    </h2>

    <div v-if="isLoading" class="space-y-2" role="status" aria-busy="true" :aria-label="$t('common.loading')">
      <Skeleton v-for="row in 5" :key="row" height="h-16" rounded="rounded-xl" />
    </div>

    <ErrorState
      v-else-if="loadError"
      :title="$t('billing.console.queue.load_failed')"
      :message="loadError"
      @retry="$emit('retry')"
    />

    <EmptyState
      v-else-if="!payments.length"
      :icon="Inbox"
      :title="$t('billing.console.verify.empty')"
      :description="$t('billing.console.queue.empty_hint')"
    />

    <ul v-else class="space-y-2">
      <li v-for="payment in payments" :key="payment.id">
        <button
          type="button"
          class="min-h-14 w-full cursor-pointer space-y-1.5 rounded-xl border p-3 text-start transition active:scale-[0.99]"
          :class="
            payment.id === selectedId
              ? 'border-theme-primary bg-theme-primary/10'
              : 'border-slate-200 bg-slate-50 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950 dark:hover:border-slate-700'
          "
          :aria-current="payment.id === selectedId ? 'true' : undefined"
          :data-testid="`billing-payment-${payment.id}`"
          @click="$emit('select', payment.id)"
        >
          <span class="flex items-center justify-between gap-2 text-sm font-black text-slate-900 dark:text-white">
            <span class="truncate">{{ payment.tenant?.name }}</span>
            <span class="shrink-0 font-mono" dir="ltr"
              >{{ formatMoney(payment.amount, 3) }} {{ payment.currency }}</span
            >
          </span>
          <span class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
            <span class="font-mono" dir="ltr">{{ payment.invoice_number }}</span>
            <span>{{ payment.method?.label }}</span>
          </span>
          <BillingReviewChip :payment="payment" :now="now" />
        </button>
      </li>
    </ul>
  </section>
</template>
