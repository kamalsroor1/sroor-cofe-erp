<template>
  <div class="space-y-4 font-tajawal">
    <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg space-y-4">
      <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
        <span>📊</span>
        <span>{{ $t('returns.document_financial_summary') }}</span>
      </h2>

      <div class="space-y-2.5 font-mono text-xs">
        <div class="flex justify-between text-slate-600 dark:text-slate-300 font-sans font-tajawal">
          <span>{{ $t('inventory.total_items_count') }}:</span>
          <span class="font-mono text-slate-900 dark:text-white font-bold">{{ itemsCount }}</span>
        </div>

        <div class="flex justify-between text-base font-black text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-sans font-tajawal">
          <span>{{ $t('returns.total_returns_val') }}:</span>
          <span class="font-mono text-rose-600 dark:text-rose-400">{{ formatMoney(netTotal) }} {{ $t('common.currency') }}</span>
        </div>

        <!-- Refund cash from drawer -->
        <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-1">
          <BaseNumberInput
            :model-value="refundAmount"
            @update:model-value="$emit('update:refund-amount', $event)"
            :label="$t('returns.refund_cash_from_drawer')"
            :hint="$t('returns.refund_zero_hint')"
            :decimals="3"
            class="font-mono text-emerald-600 dark:text-emerald-400 font-bold"
          />
        </div>
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
import { RotateCcw } from 'lucide-vue-next';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  itemsCount: { type: Number, default: 0 },
  netTotal: { type: Number, default: 0 },
  refundAmount: { type: [Number, String], default: 0 },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['update:refund-amount', 'submit-return']);
</script>
