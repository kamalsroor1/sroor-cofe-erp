<script setup>
import { computed } from 'vue';
import { CheckCircle2, Clock, FileSearch, XCircle } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { invoiceStatusCard, paidSummary, rejectionReasonOf } from '@/helpers/billingInvoiceState';

const props = defineProps({
  invoice: { type: Object, required: true },
  planName: { type: String, default: '' },
  busy: { type: Boolean, default: false },
});

const emit = defineEmits(['withdraw', 'upload-again', 'view-invoice']);

const { formatDateTime } = useFormatters();

const card = computed(() => invoiceStatusCard(props.invoice.review_state));
const summary = computed(() => paidSummary(props.invoice));

const TONE = {
  warning: 'bg-amber-50 border-amber-500 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200',
  success: 'bg-emerald-50 border-emerald-500 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200',
  danger: 'bg-rose-50 border-rose-500 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200',
  neutral: 'bg-slate-100 border-slate-400 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};
const ICON = { warning: Clock, success: CheckCircle2, danger: XCircle, neutral: FileSearch };

const params = computed(() => ({
  time: props.invoice.temporary_access?.ends_at ? formatDateTime(props.invoice.temporary_access.ends_at) : '',
  reason: rejectionReasonOf(props.invoice),
  plan: props.planName,
  start: summary.value.start ? formatDateTime(summary.value.start) : '',
  end: summary.value.end ? formatDateTime(summary.value.end) : '',
}));

const ACTION_EVENT = { withdraw: 'withdraw', upload_again: 'upload-again', view_invoice: 'view-invoice' };
const ACTION_LABEL = {
  withdraw: 'subscription.billing.pay.withdraw_and_replace',
  upload_again: 'subscription.billing.pay.upload_again',
  view_invoice: 'subscription.billing.pay.view_invoice',
};
</script>

<template>
  <div v-if="card" class="rounded-2xl border-s-4 border p-3 space-y-3" :class="TONE[card.tone]" role="status">
    <div class="flex items-start gap-2.5">
      <component :is="ICON[card.tone]" class="w-5 h-5 shrink-0 mt-0.5" aria-hidden="true" />
      <div class="min-w-0 space-y-1">
        <p class="text-sm font-black">
          {{ card.titleKey ? $t(card.titleKey) : invoice.status?.label }}
        </p>
        <p v-if="card.bodyKey" class="text-sm font-bold leading-relaxed">{{ $t(card.bodyKey, params) }}</p>
        <p v-if="invoice.review_state === 'rejected'" class="text-xs font-bold">
          {{ $t('subscription.billing.pay.state_data_safe') }}
        </p>
      </div>
    </div>
    <div v-if="card.actions.length" class="flex flex-wrap gap-2">
      <BaseButton
        v-for="action in card.actions"
        :key="action"
        variant="outline"
        :loading="busy && action === 'withdraw'"
        :disabled="busy"
        @click="emit(ACTION_EVENT[action])"
      >
        {{ $t(ACTION_LABEL[action]) }}
      </BaseButton>
    </div>
  </div>
</template>
