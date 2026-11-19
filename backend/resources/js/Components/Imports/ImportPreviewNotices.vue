<script setup>
import { AlertTriangle, FolderPlus, Info } from 'lucide-vue-next';

defineProps({
  headerRow: { type: Number, default: 1 },
  ignoredColumns: { type: Array, default: () => [] },
  newCategories: { type: Array, default: () => [] },
  confirmNewCategories: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:confirmNewCategories']);
</script>

<template>
  <div class="space-y-3 font-tajawal" data-testid="import-preview-notices">
    <p
      v-if="headerRow > 1"
      class="flex items-start gap-2 p-3 rounded-xl border border-sky-200 dark:border-sky-500/30 bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 text-xs font-bold"
      data-testid="import-header-row"
    >
      <Info class="w-4 h-4 mt-0.5 shrink-0" />
      <span>{{ $t('import.ui.header_row_found', { row: headerRow }) }}</span>
    </p>

    <div
      v-if="ignoredColumns.length"
      class="flex items-start gap-2 p-3 rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300"
      data-testid="import-ignored-columns"
    >
      <AlertTriangle class="w-4 h-4 mt-0.5 shrink-0" />
      <div class="space-y-1 min-w-0">
        <p class="text-xs font-black">{{ $t('import.ui.ignored_columns_title') }}</p>
        <p class="text-xs font-bold break-words">
          {{ $t('import.ui.ignored_columns_text', { columns: ignoredColumns.join(' - ') }) }}
        </p>
      </div>
    </div>

    <label
      v-if="newCategories.length"
      class="flex items-start gap-3 p-4 min-h-[44px] rounded-2xl border border-violet-200 dark:border-violet-500/30 bg-violet-50 dark:bg-violet-500/10 cursor-pointer"
      data-testid="import-new-categories"
    >
      <input
        type="checkbox"
        class="w-5 h-5 mt-0.5 shrink-0 accent-violet-600"
        :checked="confirmNewCategories"
        :disabled="disabled"
        data-testid="import-confirm-categories"
        @change="$emit('update:confirmNewCategories', $event.target.checked)"
      />
      <span class="space-y-1 min-w-0">
        <span class="flex items-center gap-1.5 text-sm font-black text-slate-900 dark:text-white">
          <FolderPlus class="w-4 h-4 shrink-0" />
          {{ $t('import.ui.new_categories_title') }}
        </span>
        <span class="block text-xs font-bold text-violet-700 dark:text-violet-300 break-words">
          {{ $t('import.ui.new_categories_text', { count: newCategories.length, names: newCategories.join(' - ') }) }}
        </span>
        <span class="block text-xs font-black text-slate-700 dark:text-slate-200">
          {{ $t('import.ui.confirm_new_categories') }}
        </span>
        <span v-if="!confirmNewCategories" class="block text-xs font-bold text-rose-600 dark:text-rose-400">
          {{ $t('import.ui.confirm_new_categories_required') }}
        </span>
      </span>
    </label>
  </div>
</template>
