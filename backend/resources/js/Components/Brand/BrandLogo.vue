<script setup>
import { computed } from 'vue';
import BrandMark from './BrandMark.vue';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  layout: {
    type: String,
    default: 'horizontal',
    validator: (value) => ['horizontal', 'stacked'].includes(value),
  },
  /** Mark size: number = px, string = any CSS length. The wordmark scales with it. */
  size: { type: [Number, String], default: 40 },
  variant: {
    type: String,
    default: 'auto',
    validator: (value) => ['auto', 'light', 'dark', 'mono', 'app-icon'].includes(value),
  },
  /** Small product label next to the wordmark. */
  product: {
    type: String,
    default: '',
    validator: (value) => ['', 'erp', 'pos'].includes(value),
  },
  showTagline: { type: Boolean, default: false },
  animated: { type: Boolean, default: false },
});

const emit = defineEmits(['done']);

const { t } = useTrans();

const cssSize = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size));
const isStacked = computed(() => props.layout === 'stacked');
</script>

<template>
  <div
    class="brand-logo inline-flex"
    :class="isStacked ? 'flex-col items-center gap-3 text-center' : 'flex-row items-center gap-3'"
    :style="{ '--bl-size': cssSize }"
  >
    <BrandMark :size="cssSize" :variant="variant" :animated="animated" decorative @done="emit('done')" />
    <div class="min-w-0" :class="isStacked ? 'space-y-1' : 'space-y-0.5'">
      <div class="flex items-center gap-2" :class="isStacked ? 'justify-center' : ''">
        <span
          class="font-brand font-semibold leading-none text-slate-900 dark:text-white"
          style="font-size: calc(var(--bl-size) * 0.62)"
          >{{ t('brand.name') }}</span
        >
        <span
          v-if="product"
          class="rounded-md px-1.5 py-0.5 font-mono text-[10px] font-bold leading-none"
          style="background: var(--brand-primary); color: #fff"
          >{{ t(`brand.product_${product}`) }}</span
        >
      </div>
      <p v-if="showTagline" class="text-xs font-medium leading-relaxed text-slate-500 dark:text-slate-400">
        {{ t('brand.tagline') }}
      </p>
    </div>
  </div>
</template>
