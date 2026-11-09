<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg space-y-4 font-tajawal"
  >
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
      <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400">
        {{ $t(returnType === 'sales_return' ? 'returns.select_invoice' : 'returns.select_purchase') }}
      </h2>
      <button
        v-if="selected"
        type="button"
        @click="$emit('clear')"
        class="text-xs font-bold text-theme-primary cursor-pointer min-h-[44px] px-2"
      >
        {{ $t('returns.change_source') }}
      </button>
    </div>

    <!-- Selected document -->
    <div v-if="selected" class="text-sm font-bold text-slate-900 dark:text-white flex flex-wrap gap-x-4 gap-y-1">
      <span class="font-mono">{{ selected.number }}</span>
      <span>{{ selected.party_name }}</span>
      <span class="text-slate-500 font-sans">{{ selected.date }}</span>
    </div>

    <!-- Search + results -->
    <template v-else>
      <BaseSearchInput
        v-model="search"
        :loading="isLoadingSources"
        :placeholder="$t('returns.source_search_placeholder')"
        :debounce="300"
        @search="$emit('search')"
      />

      <div v-if="isLoadingSources && sources.length === 0" class="space-y-2">
        <div v-for="n in 3" :key="n" class="h-12 rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
      </div>

      <div v-else-if="errorMessage" class="p-4 text-center text-xs font-bold text-rose-600 dark:text-rose-400">
        {{ errorMessage }}
      </div>

      <div v-else-if="sources.length === 0" class="p-6 text-center text-xs font-bold text-slate-500">
        {{ $t('returns.no_sources_found') }}
      </div>

      <ul v-else class="divide-y divide-slate-200 dark:divide-slate-800 max-h-72 overflow-y-auto">
        <li v-for="source in sources" :key="source.id">
          <button
            type="button"
            @click="$emit('select', source)"
            class="w-full min-h-[44px] py-2 px-2 flex items-center justify-between gap-3 text-start hover:bg-slate-50 dark:hover:bg-slate-800/60 rounded-lg cursor-pointer"
          >
            <span class="font-mono text-xs font-bold text-slate-900 dark:text-white">{{ source.number }}</span>
            <span class="flex-1 text-xs text-slate-600 dark:text-slate-300 truncate">{{ source.party_name }}</span>
            <span class="text-[11px] text-slate-500 font-sans">{{ source.date }}</span>
            <span class="text-xs font-mono font-bold text-theme-primary">{{ source.net_total }}</span>
          </button>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';

const search = defineModel('search', { type: String, default: '' });

defineProps({
  returnType: { type: String, required: true },
  sources: { type: Array, default: () => [] },
  selected: { type: Object, default: null },
  isLoadingSources: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
});

defineEmits(['search', 'select', 'clear']);
</script>
