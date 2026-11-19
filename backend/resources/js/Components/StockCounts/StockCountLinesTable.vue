<script setup>
import { X } from 'lucide-vue-next';
import Skeleton from '@/Components/Common/Skeletons/Skeleton.vue';
import { useTrans } from '@/Composables/useTrans';
import { useFormatters } from '@/Composables/useFormatters';
import { dCmp, dMul } from '@/helpers/decimal';

const props = defineProps({
  lines: { type: Array, default: () => [] },
  isLoading: { type: Boolean, default: false },
  editable: { type: Boolean, default: false },
  hideSystem: { type: Boolean, default: false },
  showCost: { type: Boolean, default: false },
  highlightId: { type: Number, default: null },
  countedOf: { type: Function, required: true },
  differenceOf: { type: Function, required: true },
});

const emit = defineEmits(['update']);
const { t } = useTrans();
const { formatQty, formatMoney } = useFormatters();

const diffClass = (diff) => {
  if (diff === null) return 'text-slate-400';
  const sign = dCmp(diff, '0');
  if (sign > 0) return 'text-emerald-600 dark:text-emerald-400';
  if (sign < 0) return 'text-rose-600 dark:text-rose-400';
  return 'text-slate-500 dark:text-slate-400';
};

const signed = (value) => (dCmp(value, '0') > 0 ? `+${formatQty(value)}` : formatQty(value));

const valueOf = (line) => {
  const diff = props.differenceOf(line);
  if (diff === null || line.unit_cost === null) return null;
  return dMul(diff, line.unit_cost);
};

const onInput = (line, event) => emit('update', line.item_id, event.target.value);
</script>

<template>
  <section
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden font-tajawal"
    data-testid="stock-count-lines"
  >
    <div
      class="hidden md:grid grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))] gap-3 px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800"
    >
      <span>{{ t('stock_counts.item') }}</span>
      <span class="text-center">{{ t('stock_counts.system_qty') }}</span>
      <span class="text-center">{{ t('stock_counts.counted_qty') }}</span>
      <span class="text-center">{{ t('stock_counts.difference') }}</span>
      <span class="text-center">{{ t('stock_counts.difference_value') }}</span>
    </div>

    <div v-if="isLoading" class="p-4 space-y-3" aria-busy="true">
      <Skeleton v-for="n in 6" :key="n" height="h-12" rounded="rounded-2xl" />
    </div>

    <p v-else-if="lines.length === 0" class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">
      {{ t('stock_counts.no_lines') }}
    </p>

    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
      <li
        v-for="line in lines"
        :key="line.item_id"
        :data-testid="`stock-count-line-${line.item_code}`"
        class="grid grid-cols-2 md:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))] gap-x-3 gap-y-2 items-center px-4 py-3 transition-colors"
        :class="highlightId === line.item_id ? 'bg-theme-light' : ''"
      >
        <div class="col-span-2 md:col-span-1 min-w-0">
          <p class="font-bold text-sm text-slate-800 dark:text-slate-100 truncate">{{ line.item_name }}</p>
          <p class="text-[11px] text-slate-400 font-mono">{{ line.item_code }} · {{ line.unit }}</p>
        </div>

        <div class="text-start md:text-center">
          <span class="md:hidden text-[10px] text-slate-400 block">{{ t('stock_counts.system_qty') }}</span>
          <span v-if="hideSystem" class="text-[11px] text-purple-500">{{ t('stock_counts.hidden_blind') }}</span>
          <span v-else class="font-mono text-sm text-slate-700 dark:text-slate-200">{{
            formatQty(line.system_qty)
          }}</span>
        </div>

        <div class="text-start md:text-center">
          <span class="md:hidden text-[10px] text-slate-400 block">{{ t('stock_counts.counted_qty') }}</span>
          <div v-if="editable" class="flex items-center gap-1">
            <input
              type="text"
              inputmode="decimal"
              autocomplete="off"
              :value="countedOf(line) ?? ''"
              :placeholder="t('stock_counts.not_counted')"
              :aria-label="`${t('stock_counts.counted_qty')} ${line.item_name}`"
              data-testid="stock-count-qty"
              class="w-full min-h-[44px] px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-center font-mono text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
              @change="onInput(line, $event)"
            />
            <button
              v-if="countedOf(line) !== null && countedOf(line) !== undefined"
              type="button"
              class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer"
              :title="t('stock_counts.clear')"
              :aria-label="t('stock_counts.clear')"
              @click="emit('update', line.item_id, null)"
            >
              <X class="w-4 h-4" />
            </button>
          </div>
          <span v-else class="font-mono text-sm text-slate-700 dark:text-slate-200">
            {{ line.counted_qty === null ? t('stock_counts.not_counted') : formatQty(line.counted_qty) }}
          </span>
        </div>

        <div class="text-start md:text-center">
          <span class="md:hidden text-[10px] text-slate-400 block">{{ t('stock_counts.difference') }}</span>
          <span class="font-mono text-sm font-bold" :class="diffClass(differenceOf(line))">
            {{ hideSystem || differenceOf(line) === null ? '—' : signed(differenceOf(line)) }}
          </span>
        </div>

        <div class="text-start md:text-center">
          <span class="md:hidden text-[10px] text-slate-400 block">{{ t('stock_counts.difference_value') }}</span>
          <span class="font-mono text-sm" :class="diffClass(valueOf(line))">
            {{ hideSystem || !showCost || valueOf(line) === null ? '—' : formatMoney(valueOf(line)) }}
          </span>
        </div>
      </li>
    </ul>
  </section>
</template>
