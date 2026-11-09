<script setup>
import { Printer, Undo2, Search, ReceiptText } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import EmptyState from '../Common/EmptyState.vue';
import ErrorState from '../Common/ErrorState.vue';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

defineProps({
  show: { type: Boolean, default: false },
  sales: { type: Array, default: () => [] },
  search: { type: String, default: '' },
  isLoading: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
  canReturn: { type: Boolean, default: false },
  offline: { type: Boolean, default: false },
});

defineEmits(['close', 'update:search', 'retry', 'reprint', 'return']);

const { formatMoney } = useFormatters();
</script>

<template>
  <AppModal :show="show" :title="$t('pos.recent_sales_title')" max-width="lg" @close="$emit('close')">
    <div class="p-4 space-y-3 font-tajawal select-none">
      <div class="relative">
        <Search class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
        <input
          type="search"
          :value="search"
          :placeholder="$t('pos.recent_sales_search')"
          class="w-full min-h-[44px] ps-10 pe-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm text-slate-900 dark:text-white"
          @input="$emit('update:search', $event.target.value)"
        />
      </div>

      <div v-if="isLoading" class="space-y-2" data-testid="pos-recent-sales-skeleton">
        <Skeleton v-for="n in 5" :key="n" height="h-16" />
      </div>

      <ErrorState
        v-else-if="errorMessage"
        compact
        :title="$t('pos.recent_sales_load_failed')"
        :message="errorMessage"
        @retry="$emit('retry')"
      />

      <EmptyState v-else-if="!sales.length" :icon="ReceiptText" :title="$t('pos.recent_sales_empty')" />

      <ul v-else class="space-y-2 max-h-[60vh] overflow-y-auto" data-testid="pos-recent-sales-list">
        <li
          v-for="sale in sales"
          :key="sale.id"
          class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex flex-wrap items-center gap-3"
        >
          <div class="flex-1 min-w-[160px] text-start">
            <div class="flex items-center gap-2">
              <span class="font-black font-mono text-sm text-slate-900 dark:text-white">{{ sale.invoice_number }}</span>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ sale.created_at }}</span>
              <span
                v-if="sale.is_cancelled"
                class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400"
              >
                {{ $t('pos.recent_sales_cancelled') }}
              </span>
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-300 truncate">{{ sale.customer_name }}</div>
            <div class="text-xs font-bold text-theme-primary">
              {{ formatMoney(sale.net_total) }} {{ $t('common.currency') }}
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="min-h-[44px] px-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-black flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
              :disabled="sale.is_cancelled"
              @click="$emit('reprint', sale)"
            >
              <Printer class="w-4 h-4" />
              <span>{{ $t('pos.recent_sales_reprint') }}</span>
            </button>
            <button
              v-if="canReturn"
              type="button"
              class="min-h-[44px] px-3 rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30 text-xs font-black flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
              :disabled="sale.is_cancelled || offline"
              @click="$emit('return', sale)"
            >
              <Undo2 class="w-4 h-4" />
              <span>{{ $t('pos.recent_sales_return') }}</span>
            </button>
          </div>
        </li>
      </ul>
    </div>
  </AppModal>
</template>
