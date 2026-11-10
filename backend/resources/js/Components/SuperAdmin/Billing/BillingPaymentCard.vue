<script setup>
import { computed } from 'vue';
import { Check, X, FileText, Store } from 'lucide-vue-next';
import StatusBadge from '../../Common/StatusBadge.vue';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  payment: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
});

defineEmits(['receipt', 'verify', 'reject']);

const { formatMoney, formatDate, formatDateTime } = useFormatters();

const STATUS_VARIANTS = { pending: 'warning', verified: 'success', rejected: 'danger' };

const statusVariant = computed(() => STATUS_VARIANTS[props.payment.status?.value] || 'slate');
const isPending = computed(() => props.payment.status?.value === 'pending');
</script>

<template>
  <article
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 space-y-4 shadow-xs font-tajawal"
    :data-testid="`billing-payment-${payment.id}`"
  >
    <header class="flex items-start justify-between gap-3">
      <div class="min-w-0 space-y-1">
        <div class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white">
          <Store class="w-4 h-4 text-indigo-500 shrink-0" />
          <span class="truncate">{{ payment.tenant?.name }}</span>
        </div>
        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400" dir="ltr">
          {{ payment.invoice_number }}
        </p>
      </div>
      <StatusBadge :label="payment.status?.label" :variant="statusVariant" dot />
    </header>

    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
      <div>
        <dt class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('billing.console.queue.fields.amount') }}</dt>
        <dd class="font-black text-slate-900 dark:text-white text-base" dir="ltr">
          {{ formatMoney(payment.amount, 3) }} <span class="text-[11px] text-slate-500">{{ payment.currency }}</span>
        </dd>
      </div>
      <div>
        <dt class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('billing.console.queue.fields.method') }}</dt>
        <dd class="font-black text-slate-800 dark:text-slate-200">{{ payment.method?.label }}</dd>
      </div>
      <div>
        <dt class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('billing.console.queue.fields.reference') }}</dt>
        <dd class="font-black text-slate-800 dark:text-slate-200 break-all" dir="ltr">
          {{ payment.payer_reference || '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('billing.console.queue.fields.paid_at') }}</dt>
        <dd class="font-black text-slate-800 dark:text-slate-200">{{ formatDate(payment.paid_at) }}</dd>
      </div>
      <div>
        <dt class="text-slate-500 dark:text-slate-400 font-bold">
          {{ $t('billing.console.queue.fields.submitted_at') }}
        </dt>
        <dd class="font-black text-slate-800 dark:text-slate-200">{{ formatDateTime(payment.submitted_at) }}</dd>
      </div>
      <div v-if="payment.reviewed_by">
        <dt class="text-slate-500 dark:text-slate-400 font-bold">
          {{ $t('billing.console.queue.fields.reviewed_by') }}
        </dt>
        <dd class="font-black text-slate-800 dark:text-slate-200">{{ payment.reviewed_by.name }}</dd>
      </div>
    </dl>

    <p
      v-if="payment.rejection_reason"
      class="rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 p-3 text-xs font-bold text-rose-700 dark:text-rose-300"
    >
      <span class="block text-[11px] opacity-80">{{ $t('billing.console.queue.fields.rejection_reason') }}</span>
      {{ payment.rejection_reason }}
    </p>

    <footer class="flex flex-wrap items-center gap-2 pt-1">
      <button
        type="button"
        :disabled="!payment.has_receipt"
        :title="payment.has_receipt ? undefined : $t('billing.console.queue.no_receipt')"
        class="min-h-[44px] px-4 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs font-black flex items-center gap-2 cursor-pointer transition active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
        @click="$emit('receipt', payment)"
      >
        <FileText class="w-4 h-4" />
        <span>{{
          payment.has_receipt ? $t('billing.console.queue.view_receipt') : $t('billing.console.queue.no_receipt')
        }}</span>
      </button>

      <template v-if="canManage && isPending">
        <button
          type="button"
          :disabled="busy"
          class="min-h-[44px] px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black flex items-center gap-2 cursor-pointer transition active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
          @click="$emit('verify', payment)"
        >
          <Check class="w-4 h-4" />
          <span>{{ $t('billing.console.queue.verify') }}</span>
        </button>
        <button
          type="button"
          :disabled="busy"
          class="min-h-[44px] px-4 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-black flex items-center gap-2 cursor-pointer transition active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
          @click="$emit('reject', payment)"
        >
          <X class="w-4 h-4" />
          <span>{{ $t('billing.console.queue.reject') }}</span>
        </button>
      </template>
    </footer>
  </article>
</template>
