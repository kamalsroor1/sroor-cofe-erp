<script setup>
import { computed } from 'vue';
import { Clock, Lock, RefreshCw } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseButton from '../Common/BaseButton.vue';
import POSShiftOpenForm from './POSShiftOpenForm.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { hasMetric } from '../../helpers/dashboardMetrics';
import { visibleRows } from '../../helpers/journalScope';

const props = defineProps({
  show: { type: Boolean, default: false },
  shift: { type: Object, default: null },
  metrics: { type: Object, default: null },
  openingCash: { type: String, default: '0' },
  countedCash: { type: String, default: '' },
  canManage: { type: Boolean, default: false },
  isSubmitting: { type: Boolean, default: false },
  isReportLoading: { type: Boolean, default: false },
  reportFailed: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
});

const emit = defineEmits([
  'close',
  'refresh',
  'submit-open',
  'submit-close',
  'update:openingCash',
  'update:countedCash',
]);

const { formatMoney, formatDateTime, currencyLabel } = useFormatters();

const rows = computed(() => {
  const m = props.metrics || {};
  // A figure the server withholds (null for a cashier who did not open the shift) is dropped, never shown as 0.
  return visibleRows([
    { key: 'opening', label: 'pos.shift_report_opening_cash', value: m.opening_cash_balance },
    { key: 'cash-sales', label: 'pos.shift_report_cash_sales', value: m.total_cash_sales },
    { key: 'receipts', label: 'pos.shift_report_receipts', value: m.total_customer_receipts },
    { key: 'credit', label: 'pos.shift_report_credit_sales', value: m.total_credit_sales },
    { key: 'expenses', label: 'pos.shift_report_expenses', value: m.total_expenses },
    { key: 'suppliers', label: 'pos.shift_report_supplier_paid', value: m.total_supplier_paid },
    { key: 'refunds', label: 'pos.shift_report_refunds', value: m.total_refunds },
  ]);
});
</script>

<template>
  <AppModal
    :show="show"
    :title="shift ? $t('pos.shift_x_report') : $t('pos.shift_gate_title')"
    :subtitle="shift ? $t('pos.shift_report_readonly') : $t('pos.shift_gate_message')"
    :icon="Clock"
    max-width="md"
    @close="emit('close')"
  >
    <div class="p-4 space-y-4 font-tajawal" data-testid="pos-shift-panel">
      <POSShiftOpenForm
        v-if="!shift"
        :opening-cash="openingCash"
        :can-manage="canManage"
        :is-submitting="isSubmitting"
        :error-message="errorMessage"
        @update:opening-cash="emit('update:openingCash', $event)"
        @submit="emit('submit-open')"
      />

      <template v-else>
        <div class="flex items-center justify-between gap-2">
          <p class="text-xs font-bold text-slate-500 dark:text-slate-400">
            {{ $t('pos.shift_report_number') }}:
            <span class="font-mono text-slate-900 dark:text-white">{{ shift.shift_number || shift.id }}</span>
            <span v-if="shift.opened_at" class="block mt-0.5">
              {{ $t('pos.shift_report_opened_at') }}: {{ formatDateTime(shift.opened_at) }}
            </span>
          </p>
          <button
            type="button"
            class="min-h-[44px] min-w-[44px] px-3 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center gap-1.5 text-xs font-bold cursor-pointer disabled:opacity-40"
            :disabled="isReportLoading"
            :aria-label="$t('pos.shift_report_refresh')"
            @click="emit('refresh')"
          >
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isReportLoading }" />
            <span>{{ $t('pos.shift_report_refresh') }}</span>
          </button>
        </div>

        <p v-if="reportFailed" class="text-sm font-bold text-rose-600 dark:text-rose-400" role="alert">
          {{ $t('pos.shift_report_failed') }}
        </p>

        <div v-if="isReportLoading && !metrics" class="space-y-2" aria-busy="true">
          <div v-for="n in 6" :key="n" class="h-8 rounded-lg bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
        </div>
        <p
          v-else-if="metrics && rows.length === 0 && !hasMetric(metrics.expected_cash_balance)"
          class="text-sm font-bold text-slate-500 dark:text-slate-400"
          data-testid="pos-shift-report-hidden"
        >
          {{ $t('pos.shift_report_unavailable') }}
        </p>
        <dl v-else-if="metrics" class="divide-y divide-slate-100 dark:divide-slate-800" data-testid="pos-shift-report">
          <div v-for="row in rows" :key="row.key" class="flex items-center justify-between py-2 text-sm">
            <dt class="font-bold text-slate-600 dark:text-slate-400">{{ $t(row.label) }}</dt>
            <dd class="font-mono font-black text-slate-900 dark:text-white" dir="ltr">
              {{ formatMoney(row.value) }}
            </dd>
          </div>
          <div v-if="hasMetric(metrics.expected_cash_balance)" class="flex items-center justify-between py-3 text-base">
            <dt class="font-black text-slate-900 dark:text-white">{{ $t('pos.shift_report_expected') }}</dt>
            <dd class="font-mono font-black text-emerald-600 dark:text-emerald-400" dir="ltr">
              {{ formatMoney(metrics.expected_cash_balance) }} {{ currencyLabel }}
            </dd>
          </div>
        </dl>

        <!-- Close shift -->
        <div v-if="canManage" class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-3">
          <div class="space-y-1.5">
            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400" for="pos-shift-counted-cash">
              {{ $t('pos.shift_close_counted_cash') }} ({{ currencyLabel }})
            </label>
            <input
              id="pos-shift-counted-cash"
              :value="countedCash"
              type="text"
              inputmode="decimal"
              autocomplete="off"
              dir="ltr"
              class="w-full min-h-[48px] px-3.5 text-lg font-black font-mono rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 outline-hidden"
              @input="emit('update:countedCash', $event.target.value)"
            />
          </div>
          <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('pos.shift_close_warning') }}</p>
          <p v-if="errorMessage" class="text-sm font-bold text-rose-600 dark:text-rose-400" role="alert">
            {{ errorMessage }}
          </p>
          <BaseButton
            type="button"
            variant="danger"
            size="lg"
            class="w-full min-h-[48px]"
            :loading="isSubmitting"
            :disabled="countedCash === ''"
            data-testid="pos-shift-close"
            @click="emit('submit-close')"
          >
            <Lock class="w-5 h-5" />
            <span>{{ $t('pos.shift_close_confirm') }}</span>
          </BaseButton>
        </div>
        <div
          v-else
          class="flex items-start gap-3 p-3 rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-300 text-sm font-bold"
        >
          <Lock class="w-5 h-5 shrink-0" />
          <p>{{ $t('pos.shift_gate_no_permission') }}</p>
        </div>
      </template>
    </div>
  </AppModal>
</template>
