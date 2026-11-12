<template>
  <div class="space-y-4 font-tajawal" data-testid="return-summary">
    <div
      class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg space-y-4"
    >
      <h2
        class="text-xs font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2"
      >
        <BarChart3 class="w-4 h-4" />
        <span>{{ $t('returns.document_financial_summary') }}</span>
      </h2>

      <div class="space-y-2.5 font-mono text-xs">
        <div class="flex justify-between text-slate-600 dark:text-slate-300 font-sans font-tajawal">
          <span>{{ $t('inventory.total_items_count') }}:</span>
          <span class="font-mono text-slate-900 dark:text-white font-bold">{{ itemsCount }}</span>
        </div>

        <div
          class="flex justify-between text-base font-black text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-sans font-tajawal"
        >
          <span>{{ $t('returns.total_returns_val') }}:</span>
          <span class="font-mono text-rose-600 dark:text-rose-400"
            >{{ formatMoney(netTotal) }} {{ currencyLabel }}</span
          >
        </div>

        <RefundMethodPicker
          v-if="isSales"
          class="pt-2 border-t border-slate-200 dark:border-slate-800"
          :model-value="refundMethod"
          :locked="refundLocked"
          :error="refundError"
          :hint="refundHint"
          @update:model-value="$emit('update:refund-method', $event)"
        />
      </div>

      <!-- Submit Button -->
      <BaseButton
        type="button"
        variant="primary"
        size="lg"
        :loading="isSubmitting"
        :disabled="isSubmitting || itemsCount === 0"
        @click="$emit('submit-return')"
        class="w-full shadow-lg shadow-theme-primary font-black flex items-center justify-center gap-2"
      >
        <RotateCcw class="w-4 h-4" />
        <span>{{ $t('returns.confirm_return_save_btn') }}</span>
      </BaseButton>
    </div>
  </div>
</template>

<script setup>
import { RotateCcw, BarChart3 } from 'lucide-vue-next';
import RefundMethodPicker from './RefundMethodPicker.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney, currencyLabel } = useFormatters();

defineProps({
  itemsCount: { type: Number, default: 0 },
  netTotal: { type: Number, default: 0 },
  isSales: { type: Boolean, default: true },
  refundMethod: { type: String, default: 'balance' },
  refundLocked: { type: Boolean, default: false },
  refundError: { type: String, default: '' },
  refundHint: { type: String, default: '' },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['update:refund-method', 'submit-return']);
</script>
