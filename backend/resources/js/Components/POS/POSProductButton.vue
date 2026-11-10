<template>
  <button
    @click="$emit('add-item', item)"
    type="button"
    data-testid="pos-product-tile"
    class="relative flex flex-col gap-1.5 p-2.5 rounded-xl border-s-[3px] border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900/90 hover:bg-slate-50 dark:hover:bg-slate-800/70 shadow-xs hover:shadow-md transition-all duration-150 active:scale-[0.98] cursor-pointer select-none group min-h-[112px] w-full text-start"
    :style="{ borderInlineStartColor: categoryColor }"
  >
    <div class="flex items-start gap-2 w-full min-w-0">
      <img
        v-if="showImage && imageSrc && !imageFailed"
        :src="imageSrc"
        :alt="item.name"
        loading="lazy"
        class="w-12 h-12 rounded-lg object-cover shrink-0 bg-slate-100 dark:bg-slate-800"
        data-testid="pos-product-image"
        @error="imageFailed = true"
      />

      <div class="flex-1 min-w-0 space-y-1">
        <!-- Badges: weight, unit, stock -->
        <div class="flex items-center flex-wrap gap-1">
          <span
            v-if="item.is_weighted"
            class="inline-flex items-center gap-0.5 px-1 rounded text-[9px] font-bold shrink-0 bg-sky-500/10 text-sky-600 dark:text-sky-400"
            data-testid="pos-weight-badge"
            :title="$t('pos.weight_badge')"
          >
            <Scale class="w-3 h-3" aria-hidden="true" />
            <span>{{ $t('pos.weight_badge') }}</span>
          </span>

          <span
            v-if="item.unit"
            class="px-1 rounded text-[9px] font-bold shrink-0 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400"
            data-testid="pos-unit-badge"
            :title="$t('pos.unit_badge')"
          >
            {{ unitLabel(item.unit) }}
          </span>

          <span
            class="inline-flex items-center gap-1 px-1 rounded text-[9px] font-mono font-bold shrink-0"
            :class="stockBadgeClass"
            :title="$t('pos.stock_label')"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="stockDotClass"></span>
            <span>{{ formatStock(item.current_stock) }}</span>
          </span>
        </div>

        <span
          class="block text-xs font-black text-slate-900 dark:text-white leading-snug line-clamp-2 group-hover:text-theme-primary transition-colors"
        >
          {{ item.name }}
        </span>

        <span class="block text-[9px] font-mono font-bold text-slate-400 dark:text-slate-500 truncate">
          #{{ item.code || `ITM-${item.id}` }}
        </span>
      </div>
    </div>

    <!-- Prices: retail / wholesale (the minimum price is never shown, only that one exists) -->
    <div class="grid grid-cols-2 gap-1.5 w-full mt-auto" data-testid="pos-product-prices">
      <span
        v-for="price in prices"
        :key="price.key"
        class="rounded-lg px-1.5 py-1 border flex flex-col"
        :class="
          price.active
            ? 'border-theme-primary bg-theme-primary/10'
            : 'border-slate-200 dark:border-slate-700/70 bg-slate-50 dark:bg-slate-800/50'
        "
        :data-testid="`pos-price-${price.key}`"
      >
        <span class="flex items-center justify-between gap-1 text-[9px] font-bold text-slate-500 dark:text-slate-400">
          <span class="truncate">{{ price.label }}</span>
          <Lock
            v-if="price.key === 'retail' && item.has_min_price"
            class="w-3 h-3 shrink-0 text-amber-500"
            role="img"
            :aria-label="$t('pos.min_price_protected')"
            :title="$t('pos.min_price_protected')"
          />
        </span>
        <span
          class="text-xs sm:text-sm font-black font-mono tracking-tight"
          :style="price.active ? { color: categoryColor } : {}"
          :class="price.active ? '' : 'text-slate-700 dark:text-slate-200'"
        >
          {{ formatMoney(price.value) }}
        </span>
      </span>
    </div>
  </button>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Lock, Scale } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';
import { useUnits } from '../../Composables/useUnits';
import { useTrans } from '../../Composables/useTrans';
import { priceFor } from '../../helpers/posCartLines';

const props = defineProps({
  item: { type: Object, required: true },
  categoryColor: { type: String, default: '#64748B' },
  activePriceTier: { type: String, default: 'retail' },
  showImage: { type: Boolean, default: false },
});

defineEmits(['add-item']);

const { formatMoney } = useFormatters();
const { unitLabel } = useUnits();
const { t } = useTrans();

const imageFailed = ref(false);

// A bare storage path is served from /storage; absolute URLs are used as they are.
const imageSrc = computed(() => {
  const image = props.item.image;
  if (!image) return '';
  return /^(https?:)?\/\//.test(image) || image.startsWith('/') ? image : `/storage/${image}`;
});

const prices = computed(() => [
  {
    key: 'retail',
    label: t('pos.price_retail'),
    value: priceFor(props.item, 'retail'),
    active: props.activePriceTier !== 'wholesale',
  },
  {
    key: 'wholesale',
    label: t('pos.price_wholesale'),
    value: priceFor(props.item, 'wholesale'),
    active: props.activePriceTier === 'wholesale',
  },
]);

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
