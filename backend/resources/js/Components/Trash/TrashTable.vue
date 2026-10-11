<template>
  <div
    class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl overflow-hidden font-tajawal"
  >
    <DataTable
      data-testid="trash-table"
      :rows="records"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      @page-change="$emit('page-change', $event)"
      :empty-title="$t('trash.empty_trash_title')"
      :empty-message="$t('trash.empty_trash_desc')"
    >
      <template #cell-title="{ row }">
        <span class="font-sans font-bold text-slate-900 dark:text-white font-tajawal">{{ row.title }}</span>
      </template>

      <template #cell-subtitle="{ row }">
        <span class="text-slate-500 dark:text-slate-400 font-sans font-tajawal">{{ row.subtitle }}</span>
      </template>

      <template #cell-deleted_at="{ row }">
        <span class="text-slate-500 dark:text-slate-400 font-sans">{{ row.deleted_at }}</span>
      </template>

      <template #cell-actions="{ row }">
        <div class="flex items-center justify-end gap-2 font-sans">
          <button
            type="button"
            @click="$emit('restore', row)"
            data-testid="action-restore"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-emerald-950/40 border border-slate-300 dark:border-slate-700 text-emerald-600 dark:text-emerald-400 rounded-xl text-xs font-bold transition flex items-center gap-1.5 font-tajawal cursor-pointer active:scale-95"
          >
            <RotateCcw class="w-3.5 h-3.5" />
            <span class="hidden lg:inline">{{ $t('common.restore') }}</span>
          </button>

          <button
            type="button"
            @click="$emit('force-delete', row)"
            data-testid="action-delete"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 px-3 py-1.5 bg-slate-100 hover:bg-rose-50 dark:bg-slate-800 dark:hover:bg-rose-950/40 border border-slate-300 dark:border-slate-700 text-rose-600 dark:text-rose-400 rounded-xl text-xs font-bold transition flex items-center gap-1.5 font-tajawal cursor-pointer active:scale-95"
          >
            <Trash2 class="w-3.5 h-3.5" />
            <span class="hidden lg:inline">{{ $t('common.force_delete') }}</span>
          </button>
        </div>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Trash2, RotateCcw } from 'lucide-vue-next';
import DataTable from '@/Components/Common/DataTable.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  records: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, per_page: 15 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['restore', 'force-delete', 'page-change']);

const columns = computed(() => [
  { key: 'title', label: t('trash.item_name_col') },
  { key: 'subtitle', label: t('trash.description_code_col') },
  { key: 'deleted_at', label: t('trash.deleted_at_col') },
  { key: 'actions', label: t('common.actions') },
]);
</script>
