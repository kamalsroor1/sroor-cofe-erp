<script setup>
import { Banknote, Wallet } from 'lucide-vue-next';
import { REFUND_CASH, REFUND_BALANCE } from '../../helpers/refundMethod';

defineProps({
  modelValue: { type: String, default: REFUND_BALANCE },
  locked: { type: Boolean, default: false },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
});

defineEmits(['update:modelValue']);

const options = [
  { key: REFUND_CASH, label: 'returns.refund_method_cash', icon: Banknote },
  { key: REFUND_BALANCE, label: 'returns.refund_method_balance', icon: Wallet },
];
</script>

<template>
  <div class="space-y-2 font-tajawal" data-testid="refund-method-picker">
    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('returns.refund_method') }}</span>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" role="group" :aria-label="$t('returns.refund_method')">
      <button
        v-for="option in options"
        :key="option.key"
        type="button"
        :data-testid="`refund-method-${option.key}`"
        :aria-pressed="modelValue === option.key"
        :disabled="locked && option.key !== modelValue"
        class="min-h-[44px] px-3 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 text-center transition active:scale-95 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
        :class="
          modelValue === option.key
            ? 'bg-theme-primary text-slate-950 font-black border-theme-primary'
            : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'
        "
        @click="$emit('update:modelValue', option.key)"
      >
        <component :is="option.icon" class="w-4 h-4 shrink-0" aria-hidden="true" />
        <span>{{ $t(option.label) }}</span>
      </button>
    </div>
    <p v-if="locked" class="text-[11px] text-slate-500 dark:text-slate-400" data-testid="refund-walk-in-hint">
      {{ $t('returns.refund_method_walk_in_cash') }}
    </p>
    <p v-if="hint" class="text-[11px] text-slate-500 dark:text-slate-400" data-testid="refund-cash-hint">
      {{ hint }}
    </p>
    <p
      v-if="error"
      role="alert"
      class="text-xs font-bold text-rose-600 dark:text-rose-400 p-2 rounded-lg bg-rose-50 dark:bg-rose-500/10"
      data-testid="refund-cap-error"
    >
      {{ error }}
    </p>
  </div>
</template>
