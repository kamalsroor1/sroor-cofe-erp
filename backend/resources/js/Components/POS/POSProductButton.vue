<template>
  <button
    @click="$emit('add-item', item)"
    class="relative flex flex-col items-center justify-center p-2.5 rounded-2xl border-s-[3px] transition-all duration-75 active:scale-95 cursor-pointer select-none group min-h-[100px] max-h-[120px] aspect-square w-full"
    :style="buttonStyle"
  >
    <!-- Code badge -->
    <span v-if="item.code" class="absolute top-1.5 end-2 text-[10px] font-mono opacity-40">#{{ item.code }}</span>
    
    <!-- Name -->
    <span class="text-sm font-black text-slate-800 dark:text-slate-100 text-center leading-tight line-clamp-2 mb-1 mt-2">{{ item.name }}</span>
    
    <!-- Price + Stock -->
    <div class="flex items-center gap-1.5 mt-auto">
      <span class="text-xs font-mono font-bold" :style="{ color: categoryColor }">{{ formatPrice(item) }}</span>
      <span class="w-2 h-2 rounded-full shrink-0" :class="stockDotClass"></span>
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

const buttonStyle = computed(() => ({
  borderColor: props.categoryColor,
  backgroundColor: `${props.categoryColor}15`,
}));

const stockDotClass = computed(() => {
  const stock = parseFloat(props.item.current_stock || 0);
  const minStock = parseFloat(props.item.min_stock_level || 0);
  if (stock <= 0) return 'bg-rose-500';
  if (stock <= minStock) return 'bg-amber-500';
  return 'bg-emerald-500';
});
</script>
