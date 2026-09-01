<template>
  <div class="pt-2 border-t border-slate-200 dark:border-slate-800 shrink-0 font-tajawal select-none">
    <div class="flex items-center justify-between text-xs font-bold text-slate-500 mb-2">
      <span class="flex items-center gap-1.5">
        <Star class="w-4 h-4 text-amber-500 fill-amber-400 shrink-0" />
        <span>{{ $t('pos.popular_fast_items') }}</span>
      </span>
      <span class="text-[11px] text-slate-400">{{ $t('pos.one_touch_add') }}</span>
    </div>

    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        @click="$emit('add-item', item)"
        class="min-h-[48px] px-3 py-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-theme-primary text-start transition shrink-0 active:scale-95 cursor-pointer shadow-2xs group flex items-center gap-2.5 max-w-[220px]"
      >
        <div class="w-7 h-7 rounded-lg bg-theme-light text-theme-primary flex items-center justify-center text-xs font-black shrink-0 group-hover:bg-theme-primary group-hover:text-slate-950 transition-colors">
          <Plus class="w-3.5 h-3.5" />
        </div>
        <div class="min-w-0">
          <div class="text-xs font-black text-slate-900 dark:text-white truncate">{{ item.name }}</div>
          <div class="text-[11px] font-mono font-bold text-emerald-500 mt-0.5">{{ formatMoney(getItemPrice(item)) }} {{ $t('common.currency') }}</div>
        </div>
      </button>
    </div>
  </div>
</template>

<script setup>
import { Star, Plus } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

const props = defineProps({
  items: { type: Array, default: () => [] },
  activePriceTier: { type: String, default: 'retail' },
});

defineEmits(['add-item']);

const getItemPrice = (item) => {
  if (!item) return 0;
  const retail = parseFloat(item.selling_price ?? item.price_retail ?? item.price ?? 0);
  const wholesale = parseFloat(item.min_selling_price ?? item.price_wholesale ?? retail);
  return props.activePriceTier === 'wholesale' ? (wholesale > 0 ? wholesale : retail) : (retail > 0 ? retail : wholesale);
};
</script>
