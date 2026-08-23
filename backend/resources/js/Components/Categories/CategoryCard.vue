<template>
  <div
    class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs hover:border-theme-primary transition-all duration-200 flex flex-col justify-between space-y-4 group font-tajawal"
  >
    <!-- Top Info -->
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <div
          class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-2xl shrink-0 shadow-2xs group-hover:scale-110 transition-transform"
        >
          {{ category.icon || '☕' }}
        </div>
        <div class="min-w-0">
          <h4 class="font-black text-sm text-slate-900 dark:text-white truncate group-hover:text-theme-primary transition-colors">
            {{ category.name }}
          </h4>
          <div class="flex items-center gap-2 mt-1">
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-theme-light text-theme-primary border border-theme-border">
              {{ category.items_count || 0 }} {{ $t('inventory.items_unit') }}
            </span>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
              :class="category.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-500'"
            >
              {{ category.is_active ? $t('common.active') : $t('common.inactive') }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Actions with ActionMenu Standard -->
    <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800/80 text-xs">
      <span class="text-[10px] text-slate-400 font-mono">
        #{{ category.sort_order ?? 0 }}
      </span>

      <ActionMenu
        :items="getCategoryActions(category)"
        :title="category.name"
        button-class="h-8 w-8 min-w-[32px] p-0"
      />
    </div>
  </div>
</template>

<script setup>
import { Pencil, Trash2 } from 'lucide-vue-next';
import ActionMenu from '../ActionMenu.vue';
import { trans } from '../../helpers/trans';

defineProps({
  category: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['edit', 'delete']);

const getCategoryActions = (cat) => [
  {
    label: trans('common.edit'),
    icon: Pencil,
    onClick: () => emit('edit', cat),
  },
  {
    label: trans('common.delete'),
    icon: Trash2,
    variant: 'danger',
    onClick: () => emit('delete', cat),
  },
];
</script>
