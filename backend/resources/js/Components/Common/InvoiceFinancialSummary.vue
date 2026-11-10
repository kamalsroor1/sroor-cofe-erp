<script setup>
import DynamicIcon from 'DynamicIcon.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { currencyLabel } = useFormatters();
defineProps({
  title: { type: String, default: '' },
  subtotal: { type: Number, default: null }, // null = don't show subtotal row
  netTotal: { type: Number, required: true },
  discountAmount: { type: [Number, String], default: null }, // null = no discount input
  paidAmount: { type: [Number, String], default: null }, // null = no paid input
  remainingAmount: { type: Number, default: null }, // null = no remaining row
  isProcessing: { type: Boolean, default: false },
  isDisabled: { type: Boolean, default: false },
  submitLabel: { type: String, default: '' },
  submitIcon: { type: String, default: 'check-circle2' },
});

defineEmits(['update:discountAmount', 'update:paidAmount', 'submit']);

const formatMoney = (val) => {
  if (!val && val !== 0) return '0.000';
  return Number(val).toFixed(3);
};
</script>

<template>
  <div
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4 sticky top-20"
  >
    <h2
      class="text-base font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-3"
    >
      {{ title }}
    </h2>

    <div class="space-y-3 font-mono">
      <!-- Subtotal row (optional) -->
      <div v-if="subtotal !== null" class="flex items-center justify-between text-xs">
        <span class="text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('common.subtotal') }}:</span>
        <span class="text-slate-900 dark:text-white font-bold">{{ formatMoney(subtotal) }} {{ currencyLabel }}</span>
      </div>

      <!-- Discount input (optional) -->
      <div v-if="discountAmount !== null" class="space-y-1.5 pt-2 border-t border-slate-200 dark:border-slate-800">
        <label class="text-xs font-bold text-slate-700 dark:text-slate-300 font-tajawal"
          >{{ $t('common.discount') }} ({{ currencyLabel }})</label
        >
        <input
          :value="discountAmount"
          type="number"
          step="0.01"
          min="0"
          class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-theme-primary font-mono font-bold focus:outline-none shadow-inner"
          @input="$emit('update:discountAmount', $event.target.value)"
        />
      </div>

      <!-- Net Total -->
      <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-200 dark:border-slate-800">
        <span class="text-slate-700 dark:text-slate-300 font-bold font-tajawal">{{ $t('common.net') }}:</span>
        <span class="text-base font-black text-theme-primary">{{ formatMoney(netTotal) }} {{ currencyLabel }}</span>
      </div>

      <!-- Paid input (optional) -->
      <div v-if="paidAmount !== null" class="space-y-1.5 pt-2 border-t border-slate-200 dark:border-slate-800">
        <label class="text-xs font-bold text-slate-700 dark:text-slate-300 font-tajawal"
          >{{ $t('common.paid') }} ({{ currencyLabel }})</label
        >
        <input
          :value="paidAmount"
          type="number"
          step="0.01"
          min="0"
          class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-emerald-600 dark:text-emerald-400 font-mono font-black focus:outline-none shadow-inner"
          @input="$emit('update:paidAmount', $event.target.value)"
        />
      </div>

      <!-- Remaining row (optional) -->
      <div
        v-if="remainingAmount !== null"
        class="flex items-center justify-between text-xs pt-2 border-t border-slate-200 dark:border-slate-800"
      >
        <span class="text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('common.remaining') }}:</span>
        <span class="text-rose-600 dark:text-rose-400 font-black"
          >{{ formatMoney(remainingAmount) }} {{ currencyLabel }}</span
        >
      </div>
    </div>

    <!-- Submit Button -->
    <button
      type="submit"
      :disabled="isProcessing || isDisabled"
      class="w-full h-12 rounded-2xl btn-primary-theme font-black text-sm flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer disabled:opacity-50 shadow-theme-primary"
    >
      <DynamicIcon :name="submitIcon" class="w-4 h-4" />
      <span>{{ isProcessing ? $t('common.save') + '...' : submitLabel }}</span>
    </button>
  </div>
</template>
