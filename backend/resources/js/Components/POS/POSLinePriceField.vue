<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { ChevronDown, Lock } from 'lucide-vue-next';
import { toWesternDigits } from '../../helpers/digits';
import { priceFor } from '../../helpers/posCartLines';
import { focusPosSearch } from '../../helpers/posFocus';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  line: { type: Object, required: true },
  index: { type: Number, required: true },
  compact: { type: Boolean, default: false },
  // Cart row: the field shrinks with its column instead of keeping a fixed width, and drops the currency suffix.
  fluid: { type: Boolean, default: false },
});

const emit = defineEmits(['update-price']);

const { formatMoney, currencyLabel } = useFormatters();
const { t } = useTrans();

const inputRef = ref(null);
const isOpen = ref(false);
const highlighted = ref(-1);
const popupStyle = ref({});
const listId = `pos-price-list-${Math.random().toString(36).slice(2, 9)}`;

// Retail and wholesale only. The minimum price is never shown to the cashier, just an indicator that one exists.
const options = computed(() => {
  const retail = priceFor(props.line, 'retail');
  const wholesale = priceFor(props.line, 'wholesale');
  const list = [];
  if (retail > 0) list.push({ key: 'retail', label: t('pos.price_retail'), value: retail });
  if (wholesale > 0 && wholesale !== retail)
    list.push({ key: 'wholesale', label: t('pos.price_wholesale'), value: wholesale });
  return list;
});

const hasList = computed(() => options.value.length > 0 || !!props.line.has_min_price);
const isCurrent = (option) => parseFloat(props.line.unit_price) === option.value;

const place = () => {
  const rect = inputRef.value?.getBoundingClientRect();
  if (!rect) return;
  const width = Math.max(rect.width, 208);
  const left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);
  const below = window.innerHeight - rect.bottom;
  popupStyle.value =
    below < 220
      ? { left: `${left}px`, width: `${width}px`, bottom: `${window.innerHeight - rect.top + 4}px` }
      : { left: `${left}px`, width: `${width}px`, top: `${rect.bottom + 4}px` };
};

const close = () => {
  isOpen.value = false;
  highlighted.value = -1;
  window.removeEventListener('scroll', close, true);
  window.removeEventListener('resize', close);
};

const open = () => {
  if (!hasList.value || isOpen.value) return;
  place();
  isOpen.value = true;
  highlighted.value = options.value.findIndex(isCurrent);
  window.addEventListener('scroll', close, true);
  window.addEventListener('resize', close);
};

const toggle = () => {
  if (isOpen.value) {
    close();
    return;
  }
  inputRef.value?.focus();
  nextTick(open);
};

const choose = (option) => {
  emit('update-price', { index: props.index, value: option.value });
  if (inputRef.value) inputRef.value.value = String(option.value);
  close();
  focusPosSearch();
};

const onInput = (event) => {
  const value = toWesternDigits(event.target.value)
    .replace(/[^0-9.]/g, '')
    .replace(/^(\d*\.\d*)\./, '$1');
  if (value !== event.target.value) event.target.value = value;
  highlighted.value = -1;
  emit('update-price', { index: props.index, value });
};

const onFocus = (event) => {
  event.target.dataset.original = event.target.value;
  event.target.select();
  open();
};

const onBlur = (event) => {
  close();
  const parsed = parseFloat(event.target.value);
  if (isNaN(parsed) || parsed <= -1) event.target.value = props.line.unit_price ?? '';
};

const move = (step) => {
  if (!isOpen.value) {
    open();
    return;
  }
  const count = options.value.length;
  if (!count) return;
  highlighted.value = (highlighted.value + step + count) % count;
};

// Enter takes the highlighted tier price, otherwise the typed price; either way focus returns to the search box.
const onEnter = () => {
  const option = options.value[highlighted.value];
  if (isOpen.value && option) {
    choose(option);
    return;
  }
  close();
  focusPosSearch();
};

// Esc reverts to the value before the edit and goes back to the search box.
const onEsc = (event) => {
  const original = event.target.dataset.original;
  if (original !== undefined) {
    event.target.value = original;
    emit('update-price', { index: props.index, value: original });
  }
  close();
  focusPosSearch();
};

onBeforeUnmount(close);
</script>

<template>
  <div class="flex items-center gap-1" :class="fluid ? 'w-full min-w-0' : ''">
    <div class="relative" :class="fluid ? 'flex-1 min-w-0 max-w-32' : ''">
      <input
        ref="inputRef"
        type="text"
        inputmode="decimal"
        role="combobox"
        autocomplete="off"
        :value="line.unit_price"
        :data-line-id="line.id"
        data-line-field="price"
        data-testid="pos-line-price"
        :aria-label="$t('pos.item_price')"
        :aria-invalid="!(parseFloat(line.unit_price) > 0)"
        :aria-expanded="isOpen"
        :aria-controls="listId"
        :aria-activedescendant="highlighted >= 0 ? `${listId}-${highlighted}` : undefined"
        class="aria-invalid:border-rose-500 aria-invalid:bg-rose-50 dark:aria-invalid:bg-rose-500/10 text-start font-mono font-black bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:border-theme-primary focus:outline-none"
        :class="
          compact
            ? 'w-18 h-9 pointer-coarse:h-11 px-1.5 text-xs'
            : fluid
              ? 'w-full min-w-0 h-9 pointer-coarse:h-11 ps-2 pe-8 text-sm'
              : 'w-24 h-9 pointer-coarse:h-11 ps-2 pe-8 text-sm'
        "
        @focus="onFocus"
        @blur="onBlur"
        @input="onInput"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="onEnter"
        @keydown.esc.prevent="onEsc"
        @keydown.tab="close"
      />
      <button
        v-if="hasList && !compact"
        type="button"
        tabindex="-1"
        :aria-label="$t('pos.price_list_open')"
        class="absolute inset-y-0 end-0 w-8 pointer-coarse:w-11 flex items-center justify-center text-slate-400 hover:text-theme-primary cursor-pointer"
        @mousedown.prevent
        @click="toggle"
      >
        <ChevronDown class="w-4 h-4" aria-hidden="true" />
      </button>
    </div>

    <button
      v-if="hasList && compact"
      type="button"
      tabindex="-1"
      :aria-label="$t('pos.price_list_open')"
      class="w-9 h-9 pointer-coarse:w-11 pointer-coarse:h-11 rounded-lg border border-slate-300 dark:border-slate-700 flex items-center justify-center text-slate-500 cursor-pointer"
      @mousedown.prevent
      @click="toggle"
    >
      <ChevronDown class="w-4 h-4" aria-hidden="true" />
    </button>

    <Lock
      v-if="line.has_min_price"
      class="w-3.5 h-3.5 shrink-0 text-amber-500"
      role="img"
      :aria-label="$t('pos.min_price_protected')"
      :title="$t('pos.min_price_protected')"
    />
    <span v-if="!fluid" class="text-[10px] font-bold text-slate-400 font-tajawal">{{ currencyLabel }}</span>

    <Teleport to="body">
      <div
        v-if="isOpen"
        :id="listId"
        role="listbox"
        data-testid="pos-price-list"
        class="fixed z-[70] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xl p-1 font-tajawal"
        :style="popupStyle"
      >
        <p class="px-2 py-1 text-[10px] font-bold text-slate-500 dark:text-slate-400">
          {{ $t('pos.price_list_hint') }}
        </p>
        <button
          v-for="(option, i) in options"
          :id="`${listId}-${i}`"
          :key="option.key"
          type="button"
          role="option"
          tabindex="-1"
          :aria-selected="isCurrent(option)"
          :data-testid="`pos-price-option-${option.key}`"
          class="w-full min-h-11 px-2.5 rounded-lg flex items-center justify-between gap-3 text-start cursor-pointer transition-colors"
          :class="
            highlighted === i || isCurrent(option)
              ? 'bg-theme-primary/15 text-slate-950 dark:text-white'
              : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
          "
          @mousedown.prevent
          @click="choose(option)"
        >
          <span class="text-xs font-bold">{{ option.label }}</span>
          <span class="font-mono font-black text-sm">{{ formatMoney(option.value) }}</span>
        </button>
        <p
          v-if="line.has_min_price"
          class="px-2.5 py-2 flex items-center gap-1.5 text-[11px] font-bold text-amber-600 dark:text-amber-400"
        >
          <Lock class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
          <span>{{ $t('pos.price_min_locked') }}</span>
        </p>
        <p class="px-2 pb-1 text-[10px] text-slate-400">{{ $t('pos.price_custom_hint') }}</p>
      </div>
    </Teleport>
  </div>
</template>
