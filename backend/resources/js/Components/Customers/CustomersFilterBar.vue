<template>
  <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
    <!-- Search Input -->
    <div class="flex-1">
      <BaseSearchInput
        :model-value="searchQuery"
        @update:model-value="$emit('update:searchQuery', $event)"
        @input="$emit('search')"
        :placeholder="$t('contacts.search_customer_placeholder')"
      />
    </div>

    <!-- Debt Status Filter Pills -->
    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700 overflow-x-auto">
      <button
        type="button"
        @click="$emit('set-debt-status', 'all')"
        class="min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
        :class="debtStatus === 'all' ? 'bg-theme-primary text-white font-bold shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
      >
        {{ $t('common.all') }}
      </button>

      <button
        type="button"
        @click="$emit('set-debt-status', 'debtor')"
        class="min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
        :class="debtStatus === 'debtor' ? 'bg-rose-500/20 text-rose-500 dark:text-rose-400 border border-rose-500/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
      >
        {{ $t('contacts.debtors_only') }}
      </button>

      <button
        type="button"
        @click="$emit('set-debt-status', 'zero')"
        class="min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
        :class="debtStatus === 'zero' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
      >
        {{ $t('contacts.settled_only') }}
      </button>

      <button
        type="button"
        @click="$emit('set-debt-status', 'creditor')"
        class="min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
        :class="debtStatus === 'creditor' ? 'bg-cyan-500/20 text-cyan-600 dark:text-cyan-400 border border-cyan-500/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
      >
        {{ $t('contacts.creditors_only') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';

defineProps({
  searchQuery: { type: String, default: '' },
  debtStatus: { type: String, default: 'all' },
});

defineEmits(['update:searchQuery', 'search', 'set-debt-status']);
</script>
