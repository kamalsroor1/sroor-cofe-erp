<script setup>
import { computed } from 'vue';
import { CheckCircle2, Printer } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { dCmp } from '../../helpers/decimal';

const props = defineProps({
  show: { type: Boolean, default: false },
  report: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  failed: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
});

defineEmits(['close', 'print', 'retry']);

const { formatMoney, currencyLabel } = useFormatters();

// Same lines as the 80mm print sheet; a figure the server withholds is dropped, never shown as 0.
const lines = computed(() => {
  const r = props.report || {};
  return [
    { key: 'opening', label: 'treasury.opening_float', value: r.opening_cash_balance },
    { key: 'cash-sales', label: 'treasury.cash_sales', value: r.total_cash_sales },
    { key: 'receipts', label: 'treasury.customer_collections', value: r.total_customer_receipts },
    { key: 'expenses', label: 'treasury.cash_expenses', value: r.total_expenses },
    { key: 'suppliers', label: 'treasury.supplier_cash_payments', value: r.total_supplier_paid },
    { key: 'refunds', label: 'treasury.cash_refunds_label', value: r.total_refunds },
    { key: 'expected', label: 'treasury.expected_in_drawer', value: r.expected_cash_balance, strong: true },
    { key: 'actual', label: 'treasury.actual_closing_cash', value: r.actual_cash_balance },
    { key: 'difference', label: 'treasury.cash_difference', value: r.cash_difference, strong: true, diff: true },
  ].filter((line) => line.value !== null && line.value !== undefined);
});

const diffClass = (value) => {
  const sign = dCmp(value, '0');
  if (sign === 0) return 'text-emerald-600 dark:text-emerald-400';
  return sign > 0 ? 'text-sky-600 dark:text-sky-400' : 'text-rose-600 dark:text-rose-400';
};
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('treasury.z_report_title')"
    :icon="CheckCircle2"
    max-width="md"
    @close="$emit('close')"
  >
    <div class="space-y-4 font-tajawal" data-testid="shift-closed-result">
      <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400" role="status">
        {{ $t('treasury.shift_closed_success') }}
      </p>

      <div v-if="loading" class="space-y-2" aria-busy="true">
        <div v-for="n in 6" :key="n" class="h-8 rounded-lg bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
      </div>

      <div v-else-if="failed" class="space-y-3" role="alert">
        <p class="text-sm font-bold text-rose-600 dark:text-rose-400">
          {{ errorMessage || $t('treasury.z_report_load_failed') }}
        </p>
        <BaseButton type="button" variant="secondary" size="md" class="min-h-[44px]" @click="$emit('retry')">
          {{ $t('common.retry') }}
        </BaseButton>
      </div>

      <dl v-else-if="report" class="divide-y divide-slate-100 dark:divide-slate-800" data-testid="shift-closed-lines">
        <div
          v-for="line in lines"
          :key="line.key"
          class="flex items-center justify-between gap-3 py-2 text-sm"
          :class="line.strong ? 'font-black' : ''"
        >
          <dt class="font-bold text-slate-600 dark:text-slate-400">{{ $t(line.label) }}</dt>
          <dd
            class="font-mono font-black"
            :class="line.diff ? diffClass(line.value) : 'text-slate-900 dark:text-white'"
            dir="ltr"
          >
            {{ formatMoney(line.value) }} {{ currencyLabel }}
          </dd>
        </div>
      </dl>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
        <BaseButton type="button" variant="secondary" size="md" class="min-h-[44px]" @click="$emit('close')">
          {{ $t('common.close') }}
        </BaseButton>
        <BaseButton
          type="button"
          variant="primary"
          size="md"
          class="min-h-[44px]"
          :disabled="!report"
          data-testid="shift-closed-print"
          @click="$emit('print')"
        >
          <Printer class="w-4 h-4" />
          <span>{{ $t('treasury.print_z_report') }}</span>
        </BaseButton>
      </div>
    </div>
  </AppModal>
</template>
