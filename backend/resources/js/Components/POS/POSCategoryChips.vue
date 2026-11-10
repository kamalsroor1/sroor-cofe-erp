<script setup>
import { computed } from 'vue';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  categories: { type: Array, default: () => [] },
  activeCategoryId: { type: [Number, String, null], default: null },
});

defineEmits(['select-category']);

const { t } = useTrans();

// Same ids the sidebar emits: null = everything, string = smart filters, number = a database category.
const chips = computed(() => [
  { id: null, label: t('common.all_items') },
  { id: 'favorites', label: t('pos.favorites_tab') },
  { id: 'newest', label: t('pos.newest_tab') },
  { id: 'in_stock', label: t('pos.in_stock_tab') },
  { id: 'low_stock', label: t('pos.low_stock_tab') },
  ...props.categories.map((cat) => ({ id: cat.id, label: cat.name })),
]);
</script>

<template>
  <nav
    class="flex items-center gap-2 px-3 py-2 overflow-x-auto custom-scrollbar bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 shrink-0 font-tajawal select-none"
    :aria-label="$t('pos.phone_categories_label')"
    data-testid="pos-category-chips"
  >
    <button
      v-for="chip in chips"
      :key="String(chip.id)"
      type="button"
      :aria-pressed="activeCategoryId === chip.id"
      class="min-h-11 px-4 rounded-full border text-sm whitespace-nowrap shrink-0 transition active:scale-95 cursor-pointer"
      :class="
        activeCategoryId === chip.id
          ? 'bg-theme-primary text-slate-950 border-theme-primary font-black'
          : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 font-bold'
      "
      @click="$emit('select-category', chip.id)"
    >
      {{ chip.label }}
    </button>
  </nav>
</template>
