<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl overflow-hidden font-tajawal">
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="4" />
    </div>

    <div v-else-if="records.length > 0">
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs">
          <thead class="bg-slate-100/90 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-400 font-bold font-tajawal">
            <tr>
              <th class="p-4 text-start">{{ $t('trash.item_name_col') }}</th>
              <th class="p-4 text-start">{{ $t('trash.description_code_col') }}</th>
              <th class="p-4 text-start">{{ $t('trash.deleted_at_col') }}</th>
              <th class="p-4 text-end">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-mono">
            <tr v-for="item in records" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
              <td class="p-4 font-sans font-bold text-slate-900 dark:text-white font-tajawal">{{ item.title }}</td>
              <td class="p-4 text-slate-500 dark:text-slate-400 font-sans font-tajawal">{{ item.subtitle }}</td>
              <td class="p-4 text-slate-500 dark:text-slate-400 font-sans">{{ item.deleted_at }}</td>
              <td class="p-4 text-end font-sans">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    @click="$emit('restore', item)"
                    class="min-h-[30px] px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-emerald-950/40 border border-slate-300 dark:border-slate-700 text-emerald-600 dark:text-emerald-400 rounded-xl text-xs font-bold transition flex items-center gap-1.5 font-tajawal cursor-pointer active:scale-95"
                  >
                    <RotateCcw class="w-3.5 h-3.5" />
                    <span>{{ $t('common.restore') }}</span>
                  </button>

                  <button
                    type="button"
                    @click="$emit('force-delete', item)"
                    class="min-h-[30px] px-3 py-1.5 bg-slate-100 hover:bg-rose-50 dark:bg-slate-800 dark:hover:bg-rose-950/40 border border-slate-300 dark:border-slate-700 text-rose-600 dark:text-rose-400 rounded-xl text-xs font-bold transition flex items-center gap-1.5 font-tajawal cursor-pointer active:scale-95"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                    <span>{{ $t('common.force_delete') }}</span>
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
          v-for="item in records"
          :key="item.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2.5"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ item.title }}</h4>
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.subtitle }}</p>
            </div>
            <span class="text-[10px] text-slate-400 font-mono shrink-0">{{ item.deleted_at }}</span>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button
              type="button"
              @click="$emit('restore', item)"
              class="min-h-[36px] px-3.5 py-1.5 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
            >
              <RotateCcw class="w-3.5 h-3.5" />
              <span>{{ $t('common.restore') }}</span>
            </button>
            <button
              type="button"
              @click="$emit('force-delete', item)"
              class="min-h-[36px] px-3.5 py-1.5 bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
            >
              <Trash2 class="w-3.5 h-3.5" />
              <span>{{ $t('common.force_delete') }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 font-mono">
        <span class="font-tajawal">{{ $t('trash.total_deleted_items', { count: pagination.total }) }}</span>
        <div class="flex items-center gap-2 font-sans font-tajawal">
          <button
            type="button"
            :disabled="pagination.current_page === 1"
            @click="$emit('page-change', pagination.current_page - 1)"
            class="min-h-[36px] px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-50 border border-slate-300 dark:border-slate-700 rounded-xl cursor-pointer active:scale-95"
          >
            {{ $t('common.previous') }}
          </button>
          <span class="font-mono">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button
            type="button"
            :disabled="pagination.current_page === pagination.last_page"
            @click="$emit('page-change', pagination.current_page + 1)"
            class="min-h-[36px] px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-50 border border-slate-300 dark:border-slate-700 rounded-xl cursor-pointer active:scale-95"
          >
            {{ $t('common.next') }}
          </button>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('trash.empty_trash_title')"
      :description="$t('trash.empty_trash_desc')"
      icon="🗑️"
    />
  </div>
</template>

<script setup>
import { Trash2, RotateCcw } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';

defineProps({
  records: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, per_page: 15 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['restore', 'force-delete', 'page-change']);
</script>
