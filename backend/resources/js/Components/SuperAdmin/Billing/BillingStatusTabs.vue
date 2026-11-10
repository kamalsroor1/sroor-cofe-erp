<script setup>
import { PAYMENT_TABS } from '../../../Composables/useBillingPayments';

defineProps({
  modelValue: { type: String, required: true },
  pendingCount: { type: Number, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <div
    role="tablist"
    class="flex gap-1.5 overflow-x-auto p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800"
  >
    <button
      v-for="tab in PAYMENT_TABS"
      :key="tab"
      type="button"
      role="tab"
      :aria-selected="modelValue === tab"
      class="min-h-[44px] flex-1 sm:flex-none px-4 rounded-xl text-xs font-black whitespace-nowrap transition cursor-pointer active:scale-[0.98] flex items-center justify-center gap-2"
      :class="
        modelValue === tab
          ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
      "
      @click="$emit('update:modelValue', tab)"
    >
      <span>{{ $t(`billing.console.queue.tabs.${tab}`) }}</span>
      <span
        v-if="tab === 'pending' && pendingCount"
        class="min-w-5 h-5 px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-black flex items-center justify-center"
      >
        {{ pendingCount }}
      </span>
    </button>
  </div>
</template>
