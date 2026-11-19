<template>
  <div
    class="item-label flex flex-col items-stretch justify-between overflow-hidden bg-white text-black font-tajawal"
    :style="boxStyle"
    data-testid="item-label"
  >
    <div v-if="settings.show_name" class="item-label__name font-bold text-center leading-tight" :style="nameStyle">
      {{ label.name }}
    </div>

    <div v-if="settings.show_barcode && label.code" class="flex-1 min-h-0 flex flex-col justify-center" dir="ltr">
      <BarcodeSvg :code="label.code" :symbology="settings.symbology" />
    </div>

    <div
      v-if="settings.show_code && label.code"
      class="text-center font-mono leading-none tracking-wider"
      :style="codeStyle"
      dir="ltr"
      data-testid="label-code"
    >
      {{ label.code }}
    </div>

    <div
      v-if="settings.show_price && label.price != null"
      class="text-center font-black leading-tight"
      :style="priceStyle"
      data-testid="label-price"
    >
      <span dir="ltr">{{ formatMoney(label.price) }}</span>
      <span class="ms-1 font-bold">{{ currencyLabel }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BarcodeSvg from './BarcodeSvg.vue';
import { LABEL_PRESETS, DEFAULT_LABEL_SETTINGS } from '../../../helpers/labelPrint';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  label: { type: Object, required: true },
  settings: { type: Object, required: true },
});

const { formatMoney, currencyLabel } = useFormatters();

const preset = computed(() => LABEL_PRESETS[props.settings.preset] || LABEL_PRESETS[DEFAULT_LABEL_SETTINGS.preset]);
// Type sizes follow the label height (designed on a 25mm label).
const scale = computed(() => preset.value.height / 25);
const pt = (base) => `${(base * scale.value).toFixed(1)}pt`;

const boxStyle = computed(() => ({
  width: `${preset.value.width}mm`,
  height: `${preset.value.height}mm`,
  padding: `${(1.5 * scale.value).toFixed(2)}mm ${(2 * scale.value).toFixed(2)}mm`,
  gap: `${(0.6 * scale.value).toFixed(2)}mm`,
}));
const nameStyle = computed(() => ({ fontSize: pt(7) }));
const codeStyle = computed(() => ({ fontSize: pt(5.5) }));
const priceStyle = computed(() => ({ fontSize: pt(9) }));
</script>

<style scoped>
.item-label {
  box-sizing: border-box;
  break-inside: avoid;
  page-break-inside: avoid;
}

.item-label__name {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
}
</style>
