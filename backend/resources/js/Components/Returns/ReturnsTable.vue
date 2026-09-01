<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-xl font-tajawal">
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="6" />
    </div>

    <div v-else-if="returnsList.length > 0">
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="py-3 px-4 text-start font-bold">#</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('returns.doc_number') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('returns.return_type') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('returns.party_col') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('returns.return_value') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('returns.reason') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="(ret, idx) in returnsList"
              :key="ret.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors"
            >
              <td class="py-3.5 px-4 font-mono text-slate-500">
                {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
              </td>
              <td class="py-3.5 px-4 font-mono font-bold text-theme-primary">
                {{ ret.return_number }}
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold border font-tajawal"
                  :class="ret.return_type === 'sales_return' ? 'bg-cyan-500/10 border-cyan-500/30 text-cyan-600 dark:text-cyan-400' : 'bg-theme-light border-theme-border text-theme-primary'"
                >
                  {{ ret.return_type === 'sales_return' ? $t('returns.sales_return_option') : $t('returns.purchase_return_option') }}
                </span>
              </td>
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white font-tajawal">{{ ret.party_name }}</div>
                <div v-if="ret.party_phone" class="text-[10px] text-slate-500 font-mono mt-0.5">
                  {{ ret.party_phone }}
                </div>
              </td>
              <td class="py-3.5 px-4 font-mono text-slate-500 dark:text-slate-400">
                {{ ret.return_date }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-black text-rose-600 dark:text-rose-400 text-sm">
                {{ formatMoney(ret.total_amount) }} {{ $t('common.currency') }}
              </td>
              <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 font-tajawal max-w-xs truncate">
                {{ ret.reason || '—' }}
              </td>
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1">
                  <!-- Preview Details Button -->
                  <button
                    type="button"
                    @click="$emit('open-details', ret)"
                    class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all cursor-pointer active:scale-95"
                    :title="$t('returns.view_return_details_hint')"
                  >
                    <Eye class="w-4 h-4" />
                  </button>

                  <!-- Delete Button -->
                  <button
                    type="button"
                    @click="$emit('delete-return', ret)"
                    class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer active:scale-95"
                    :title="$t('returns.archive_return_hint')"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Tactile Cards -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="ret in returnsList"
          :key="ret.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2.5"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <span class="font-mono font-bold text-theme-primary text-xs">{{ ret.return_number }}</span>
              <h4 class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ ret.party_name }}</h4>
            </div>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
              :class="ret.return_type === 'sales_return' ? 'bg-cyan-500/10 border-cyan-500/30 text-cyan-600 dark:text-cyan-400' : 'bg-theme-light border-theme-border text-theme-primary'"
            >
              {{ ret.return_type === 'sales_return' ? $t('returns.sales_return_option') : $t('returns.purchase_return_option') }}
            </span>
          </div>

          <div class="flex items-center justify-between text-xs pt-1">
            <span class="text-slate-500 font-mono">{{ ret.return_date }}</span>
            <span class="font-mono font-black text-rose-600 dark:text-rose-400 text-sm">
              {{ formatMoney(ret.total_amount) }} {{ $t('common.currency') }}
            </span>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button
              type="button"
              @click="$emit('open-details', ret)"
              class="min-h-[36px] px-3.5 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
            >
              <Eye class="w-3.5 h-3.5 text-cyan-500" />
              <span>{{ $t('common.details') }}</span>
            </button>

            <button
              type="button"
              @click="$emit('delete-return', ret)"
              class="min-h-[36px] px-3.5 py-1.5 bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
            >
              <Trash2 class="w-3.5 h-3.5" />
              <span>{{ $t('common.delete') }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination Bar -->
      <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <div class="text-xs text-slate-500 dark:text-slate-400">
          {{ $t('returns.total_results_returns', { count: pagination.total }) }}
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            @click="$emit('page-change', pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="min-h-[36px] px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer active:scale-95"
          >
            {{ $t('common.previous') }}
          </button>
          <span class="px-3 py-1.5 text-xs font-mono text-slate-600 dark:text-slate-300 font-bold">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <button
            type="button"
            @click="$emit('page-change', pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="min-h-[36px] px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer active:scale-95"
          >
            {{ $t('common.next') }}
          </button>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('returns.no_returns_found')"
      :description="$t('returns.no_returns_description')"
      :icon="'🔄'"
    >
      <template #action>
        <router-link
          to="/returns/create"
          class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black font-tajawal shadow-lg shadow-theme-primary"
        >
          {{ $t('returns.add_first_return') }}
        </router-link>
      </template>
    </EmptyState>
  </div>
</template>

<script setup>
import { Eye, Trash2 } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';

defineProps({
  returnsList: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, per_page: 15 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['open-details', 'delete-return', 'page-change']);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
