<script setup>
import { computed } from 'vue';
import { AlertTriangle, CheckCircle2, FileSearch } from 'lucide-vue-next';
import BillingReviewChip from './BillingReviewChip.vue';
import { amountsDiffer, invoiceTypeLabel, isPositiveMoney } from '../../../helpers/billingReview';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  payment: { type: Object, required: true },
  now: { type: Number, default: () => Date.now() },
});

const { formatMoney, formatDateTime } = useFormatters();

const mismatch = computed(() => amountsDiffer(props.payment.amount, props.payment.invoice_total));
const typeLabel = computed(() => invoiceTypeLabel(props.payment));
const isReceipt = computed(() => props.payment.receipt_classification?.value === 'transfer_receipt_image');
const classification = computed(() => props.payment.receipt_classification);
const graceEnds = computed(() => props.payment.invoice_temporary_access?.ends_at);
const showGraceEnd = computed(
  () => props.payment.status?.value === 'pending' && props.payment.invoice_temporary_access?.state === 'active'
);

const label = 'text-xs font-bold text-slate-500 dark:text-slate-400';
const value = 'text-sm font-black text-slate-900 dark:text-white';
</script>

<template>
  <section class="space-y-3" :aria-label="$t('billing.console.verify.facts_title')">
    <header class="space-y-1">
      <h2 class="truncate text-lg font-black text-slate-900 dark:text-white">{{ payment.tenant?.name }}</h2>
      <p class="flex flex-wrap items-center gap-x-2 text-xs font-bold text-slate-600 dark:text-slate-300">
        <span class="font-mono" dir="ltr">{{ payment.invoice_number }}</span>
        <span v-if="typeLabel">{{ $t(typeLabel.key, typeLabel.params) }}</span>
      </p>
      <BillingReviewChip :payment="payment" :now="now" />
    </header>

    <dl class="grid grid-cols-1 gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-950 sm:grid-cols-2">
      <div>
        <dt :class="label">{{ $t('billing.console.verify.amount_due') }}</dt>
        <dd :class="value" class="font-mono" dir="ltr">
          {{ formatMoney(payment.invoice_total, 3) }} {{ payment.currency }}
        </dd>
      </div>
      <div>
        <dt :class="label">{{ $t('billing.console.verify.declared_amount') }}</dt>
        <dd
          :class="[value, mismatch ? 'text-rose-700 dark:text-rose-300' : '']"
          class="flex items-center gap-1.5 font-mono"
          dir="ltr"
        >
          <AlertTriangle v-if="mismatch" class="h-4 w-4 shrink-0" aria-hidden="true" />
          <span>{{ formatMoney(payment.amount, 3) }} {{ payment.currency }}</span>
        </dd>
      </div>
      <div>
        <dt :class="label">{{ $t('billing.console.queue.fields.method') }}</dt>
        <dd :class="value">{{ payment.method?.label }}</dd>
      </div>
      <div>
        <dt :class="label">{{ $t('billing.console.queue.fields.reference') }}</dt>
        <dd :class="value" class="break-all font-mono" dir="ltr">{{ payment.payer_reference || '-' }}</dd>
      </div>
      <div>
        <dt :class="label">{{ $t('billing.console.queue.fields.submitted_at') }}</dt>
        <dd :class="value">{{ formatDateTime(payment.submitted_at) }}</dd>
      </div>
      <div v-if="classification">
        <dt :class="label">{{ $t('billing.console.verify.receipt_title') }}</dt>
        <dd :class="value" class="flex items-center gap-1.5" :title="classification.reason || undefined">
          <component :is="isReceipt ? CheckCircle2 : FileSearch" class="h-4 w-4 shrink-0" aria-hidden="true" />
          <span>{{
            isReceipt ? $t('billing.console.verify.classifier_receipt') : $t('billing.console.verify.classifier_other')
          }}</span>
        </dd>
      </div>
      <div v-else>
        <dt :class="label">{{ $t('billing.console.verify.receipt_title') }}</dt>
        <dd :class="value">{{ $t('billing.console.verify.classifier_none') }}</dd>
      </div>
      <div v-if="showGraceEnd && graceEnds">
        <dt :class="label">{{ $t('billing.console.verify.grace_ends') }}</dt>
        <dd :class="value">{{ formatDateTime(graceEnds) }}</dd>
      </div>
      <div v-if="payment.transferred_at">
        <dt :class="label">{{ $t('billing.console.verify.verified_transfer_time') }}</dt>
        <dd :class="value">{{ formatDateTime(payment.transferred_at) }}</dd>
      </div>
      <div v-if="payment.reviewed_by">
        <dt :class="label">{{ $t('billing.console.queue.fields.reviewed_by') }}</dt>
        <dd :class="value">{{ payment.reviewed_by.name }}</dd>
      </div>
    </dl>

    <p
      v-if="payment.status?.value === 'verified' && isPositiveMoney(payment.invoice_overpaid_amount)"
      class="rounded-xl border border-amber-500 bg-amber-50 p-3 text-xs font-bold text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
      role="status"
    >
      {{ $t('billing.console.verify.verified_overpaid', { amount: formatMoney(payment.invoice_overpaid_amount, 3) }) }}
      <span class="block">{{ $t('billing.console.verify.preview_overpaid_note') }}</span>
    </p>
    <p
      v-if="payment.term_already_ended"
      class="rounded-xl border border-amber-500 bg-amber-50 p-3 text-xs font-bold text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
      role="status"
    >
      {{ $t('billing.console.verify.term_already_ended') }}
    </p>
    <p
      v-if="payment.rejection_reason"
      class="rounded-xl border border-rose-500 bg-rose-50 p-3 text-xs font-bold text-rose-900 dark:bg-rose-500/10 dark:text-rose-200"
    >
      <span class="block opacity-80">{{ $t('billing.console.queue.fields.rejection_reason') }}</span>
      {{ payment.rejection_reason }}
    </p>
  </section>
</template>
