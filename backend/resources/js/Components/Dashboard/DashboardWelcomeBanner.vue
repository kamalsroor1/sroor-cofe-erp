<template>
  <header
    class="flex items-center justify-between gap-3 min-w-0 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 sm:px-5 shadow-xs"
    data-testid="dashboard-header"
  >
    <div class="flex items-center gap-2.5 min-w-0">
      <span
        class="hidden sm:inline-flex items-center justify-center w-9 h-9 rounded-xl bg-theme-primary/10 text-theme-primary shrink-0"
      >
        <Coffee class="w-4 h-4 stroke-[2.5]" />
      </span>
      <div class="min-w-0">
        <h1 class="text-base sm:text-xl font-black text-slate-900 dark:text-white tracking-tight truncate">
          {{ companyName || $t('dashboard.company_title') }}
        </h1>
        <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 font-bold truncate">{{ todayLabel }}</p>
      </div>
    </div>
    <div class="flex items-center gap-2 shrink-0">
      <router-link
        v-if="canSell"
        to="/pos"
        data-testid="dashboard-pos-btn"
        class="min-h-[44px] px-4 bg-linear-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-black text-xs rounded-2xl shadow-sm flex items-center justify-center gap-1.5 whitespace-nowrap transition active:scale-95 cursor-pointer select-none"
      >
        <Zap class="w-4 h-4 shrink-0" />
        <span>{{ $t('dashboard.pos_short') }}</span>
      </router-link>
      <button
        type="button"
        data-testid="dashboard-refresh-btn"
        :disabled="loading"
        class="min-h-[44px] min-w-[44px] bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-2xl transition active:scale-95 cursor-pointer flex items-center justify-center shrink-0"
        :title="$t('dashboard.refresh_live')"
        :aria-label="$t('dashboard.refresh_live')"
        @click="$emit('refresh')"
      >
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
      </button>
      <router-link
        :to="{ path: '/help', query: { article: 'getting-started' } }"
        data-testid="contextual-help-btn"
        :title="$t('help.contextual_button_label')"
        :aria-label="$t('help.contextual_button_label')"
        class="min-h-[44px] min-w-[44px] bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-theme-primary border border-slate-300 dark:border-slate-700 rounded-2xl transition active:scale-95 flex items-center justify-center shrink-0"
      >
        <CircleHelp class="w-4 h-4" />
      </router-link>
    </div>
  </header>
</template>
<script setup>
import { computed } from 'vue';
import { Zap, RefreshCw, Coffee, CircleHelp } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

defineProps({
  companyName: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  canSell: { type: Boolean, default: true },
});
defineEmits(['refresh']);

const { formatDate } = useFormatters();
const todayLabel = computed(() => formatDate(new Date(), { weekday: 'long', day: 'numeric', month: 'long' }));
</script>
