<template>
  <button
    @click="$emit('add-item', item)"
    type="button"
    data-testid="pos-product-tile"
    class="@container/tile relative flex flex-col gap-1.5 p-2.5 rounded-xl border-s-[3px] border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900/90 hover:bg-slate-50 dark:hover:bg-slate-800/70 shadow-xs hover:shadow-md transition-all duration-150 active:scale-[0.98] cursor-pointer select-none group min-h-[104px] w-full text-start"
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

      <span
        class="flex-1 min-w-0 text-sm font-black text-slate-900 dark:text-white leading-snug line-clamp-2 group-hover:text-theme-primary transition-colors"
        dir="auto"
      >
        {{ item.name }}
      </span>
    </div>

    <!-- Badges: weight, unit, stock (green = available, amber = running low, red = out) -->
    <div class="flex items-center flex-wrap gap-1">
      <span
        v-if="item.is_weighted"
        class="inline-flex items-center gap-0.5 px-1.5 rounded text-xs font-bold shrink-0 bg-sky-500/10 text-sky-700 dark:text-sky-400"
        data-testid="pos-weight-badge"
        :title="$t('pos.weight_badge')"
      >
        <Scale class="w-3 h-3" aria-hidden="true" />
        <span>{{ $t('pos.weight_badge') }}</span>
      </span>

      <span
        v-if="item.unit"
        class="px-1.5 rounded text-xs font-bold shrink-0 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
        data-testid="pos-unit-badge"
        :title="$t('pos.unit_badge')"
      >
        {{ unitLabel(item.unit) }}
      </span>

      <span
        class="inline-flex items-center gap-1 px-1.5 rounded text-xs font-bold shrink-0"
        :class="stock.badgeClass"
        data-testid="pos-stock-badge"
        :data-stock-level="stock.level"
        :title="$t('pos.stock_label')"
      >
        <span class="w-1.5 h-1.5 rounded-full" :class="stock.dotClass" aria-hidden="true"></span>
        <span v-if="stock.label">{{ stock.label }}</span>
        <span class="font-mono">{{ formatStock(item.current_stock) }}</span>
      </span>
    </div>

    <!-- One price: the selected tier (the minimum price is never shown, only that one exists) -->
    <div class="flex items-end justify-between gap-2 w-full mt-auto" data-testid="pos-product-prices">
      <span class="flex items-baseline gap-1.5 min-w-0">
        <span
          class="text-lg leading-none font-black font-mono tracking-tight text-slate-900 dark:text-white"
          :data-testid="`pos-price-${shown.tier}`"
          data-price-slot="main"
        >
          {{ formatMoney(shown.value) }}
        </span>
        <span
          v-if="shown.tier === 'wholesale'"
          class="px-1.5 rounded text-xs font-bold bg-purple-500/10 text-purple-700 dark:text-purple-300"
          >{{ $t('pos.price_wholesale') }}</span
        >
        <Lock
          v-if="item.has_min_price && shown.tier === 'retail'"
          class="w-3.5 h-3.5 shrink-0 self-center text-amber-500"
          role="img"
          :aria-label="$t('pos.min_price_protected')"
          :title="$t('pos.min_price_protected')"
        />
      </span>

      <!-- The code only shows when the card is wide enough to hold it without squeezing the price -->
      <span
        class="hidden @[210px]/tile:block min-w-0 truncate text-xs font-mono font-bold text-slate-500 dark:text-slate-400"
        data-testid="pos-product-sku"
      >
        #{{ item.code || `ITM-${item.id}` }}
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
import { displayPrice } from '../../helpers/posCartLines';

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

const shown = computed(() => displayPrice(props.item, props.activePriceTier));

const formatStock = (stock) => {
  const num = parseFloat(stock || 0);
  if (num <= 0) return '0';
  return num % 1 === 0 ? num.toFixed(0) : num.toFixed(2);
};

const STOCK_LEVELS = {
  out: { dotClass: 'bg-rose-500', badgeClass: 'bg-rose-500/10 text-rose-700 dark:text-rose-400' },
  low: { dotClass: 'bg-amber-500', badgeClass: 'bg-amber-500/15 text-amber-700 dark:text-amber-400' },
  ok: { dotClass: 'bg-emerald-500', badgeClass: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' },
};

const stock = computed(() => {
  const current = parseFloat(props.item.current_stock || 0);
  const minimum = parseFloat(props.item.min_stock_level || 0);
  const level = current <= 0 ? 'out' : current <= minimum ? 'low' : 'ok';
  const label = level === 'out' ? t('pos.stock_out_short') : level === 'low' ? t('pos.stock_low_short') : '';
  return { level, label, ...STOCK_LEVELS[level] };
});
</script>
