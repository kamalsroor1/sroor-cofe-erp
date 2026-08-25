<template>
  <button
    @click="$emit('add-item', item)"
    type="button"
    class="relative flex flex-col justify-between p-3.5 rounded-2xl border-s-[4px] border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900/90 hover:bg-slate-50 dark:hover:bg-slate-800/70 shadow-xs hover:shadow-md transition-all duration-150 active:scale-[0.98] cursor-pointer select-none group min-h-[110px] w-full text-start"
    :style="{ borderInlineStartColor: categoryColor }"
  >
    <!-- Top Row: Code Badge & Live Stock Indicator -->
    <div class="flex items-center justify-between gap-2 w-full">
      <span class="text-[10px] font-mono font-bold text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded-md truncate max-w-[120px]">
        #{{ item.code || `ITM-${item.id}` }}
      </span>

      <span
        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-mono font-bold shrink-0"
        :class="stockBadgeClass"
      >
        <span class="w-1.5 h-1.5 rounded-full" :class="stockDotClass"></span>
        <span>{{ formatStock(item.current_stock) }}</span>
      </span>
    </div>
    
    <!-- Middle: Product Name (Bold, Clear, 2 lines max with good breathing room) -->
    <div class="my-2 min-h-[38px] flex items-center">
      <span class="text-xs sm:text-sm font-black text-slate-900 dark:text-white leading-snug line-clamp-2 group-hover:text-theme-primary transition-colors">
        {{ item.name }}
      </span>
    </div>
    
    <!-- Bottom Row: Price & Quick Add Button -->
    <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800/60 w-full mt-auto">
      <div class="flex items-baseline gap-1">
        <span class="text-sm sm:text-base font-black font-mono tracking-tight" :style="{ color: categoryColor }">
          {{ formatPrice(item) }}
        </span>
        <span class="text-[10px] font-bold text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
      </div>

      <span class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-800 group-hover:bg-theme-primary text-slate-500 group-hover:text-slate-950 flex items-center justify-center text-xs font-black transition-all shadow-2xs">
        +
      </span>
    </div>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { useFormatters } from '../../Composables/useFormatters';

const props = defineProps({
  item: { type: Object, required: true },
  categoryColor: { type: String, default: '#64748B' },
  categoryColorLight: { type: String, default: '#f1f5f9' },
  activePriceTier: { type: String, default: 'retail' },
});

defineEmits(['add-item']);

const { formatMoney } = useFormatters();

const formatPrice = (item) => {
  const price = props.activePriceTier === 'wholesale'
    ? (item.price_wholesale || item.price_retail)
    : (item.price_retail || item.price_wholesale);
  return formatMoney(price);
};

const formatStock = (stock) => {
  const num = parseFloat(stock || 0);
  if (num <= 0) return '0';
  return num % 1 === 0 ? num.toFixed(0) : num.toFixed(2);
};

const stockDotClass = computed(() => {
  const stock = parseFloat(props.item.current_stock || 0);
  const minStock = parseFloat(props.item.min_stock_level || 0);
  if (stock <= 0) return 'bg-rose-500';
  if (stock <= minStock) return 'bg-amber-500';
  return 'bg-emerald-500';
});

const stockBadgeClass = computed(() => {
  const stock = parseFloat(props.item.current_stock || 0);
  const minStock = parseFloat(props.item.min_stock_level || 0);
  if (stock <= 0) return 'bg-rose-500/10 text-rose-500';
  if (stock <= minStock) return 'bg-amber-500/10 text-amber-500';
  return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
});
</script>
