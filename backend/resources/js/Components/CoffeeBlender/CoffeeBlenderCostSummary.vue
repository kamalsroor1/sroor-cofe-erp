<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4 sticky top-6">
    <h2 class="text-xs font-bold text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
      <span>📊</span>
      <span>{{ $t('inventory.blend_cost_summary') }}</span>
    </h2>

    <div class="space-y-2.5 font-mono text-xs">
      <div class="flex justify-between text-slate-500 dark:text-slate-400 font-sans">
        <span>{{ $t('inventory.total_weight_label') }}</span>
        <span class="font-mono text-slate-900 dark:text-white font-bold">
          {{ targetWeightGrams }} {{ $t('inventory.unit_gram') }} ({{ (targetWeightGrams / 1000).toFixed(3) }} {{ $t('inventory.unit_weight_short') }})
        </span>
      </div>

      <div class="flex justify-between text-slate-500 dark:text-slate-400 font-sans">
        <span>{{ $t('inventory.estimated_raw_cost') }}</span>
        <span class="font-mono text-rose-500 font-bold">{{ formatMoney(totalCalculatedCost) }} {{ $t('common.currency') }}</span>
      </div>

      <div class="flex justify-between text-base font-black text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-sans">
        <span>{{ $t('inventory.suggested_retail_price') }}:</span>
        <span class="font-mono text-emerald-500 text-lg font-black">{{ formatMoney(totalCalculatedPrice) }} {{ $t('common.currency') }}</span>
      </div>

      <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400 font-sans">
        <span>{{ $t('inventory.profit_margin') }}:</span>
        <span class="font-mono font-bold text-theme-primary">
          {{ profitMargin }}% ({{ formatMoney(totalCalculatedPrice - totalCalculatedCost) }} {{ $t('common.currency') }})
        </span>
      </div>
    </div>

    <!-- Customer Selection -->
    <div class="space-y-1.5 pt-3 border-t border-slate-200 dark:border-slate-800">
      <BaseSelect
        :model-value="selectedCustomerId"
        @update:model-value="$emit('update:selectedCustomerId', $event)"
        :label="$t('contacts.customer')"
        :options="customerOptions"
        :required="true"
      />
    </div>

    <!-- Issue Invoice Button -->
    <button
      type="button"
      @click="$emit('submit-invoice')"
      :disabled="isSubmitting || componentsCount === 0"
      class="w-full min-h-[48px] bg-theme-gradient text-white shadow-theme-primary rounded-2xl font-black text-xs shadow-xl shadow-theme-primary transition active:scale-[0.99] disabled:opacity-40 cursor-pointer flex items-center justify-center gap-2 font-sans"
    >
      <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
      <Zap v-else class="w-4 h-4 fill-white text-white" />
      <span>{{ $t('inventory.blend_invoice_btn') }}</span>
    </button>
  </div>
</template>

<script setup>
import { Zap } from 'lucide-vue-next';
import BaseSelect from '../Form/BaseSelect.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  targetWeightGrams: { type: Number, default: 250 },
  totalCalculatedCost: { type: Number, default: 0 },
  totalCalculatedPrice: { type: Number, default: 0 },
  profitMargin: { type: Number, default: 0 },
  customerOptions: { type: Array, default: () => [] },
  selectedCustomerId: { type: [Number, String], default: null },
  isSubmitting: { type: Boolean, default: false },
  componentsCount: { type: Number, default: 0 },
});

defineEmits(['update:selectedCustomerId', 'submit-invoice']);
</script>
