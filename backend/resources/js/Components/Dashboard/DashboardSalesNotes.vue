<template>
  <div v-if="showReturns || showSplit" class="space-y-0.5 text-[10px] sm:text-[11px] font-bold">
    <div v-if="showReturns" class="flex items-center justify-between gap-1 min-w-0 text-rose-500 dark:text-rose-400">
      <span class="truncate">{{ returnsLabel }}</span>
      <span class="font-mono font-black shrink-0">−{{ formatMoney(returns) }} {{ currencyLabel }}</span>
    </div>
    <p v-if="showSplit" class="text-slate-500 dark:text-slate-400 font-medium leading-snug">
      {{
        $t('dashboard.cash_credit_before_returns', { cash: formatMoney(cash || 0), credit: formatMoney(credit || 0) })
      }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useReturnsDisplay } from '../../Composables/useReturnsDisplay';

const props = defineProps({
  returns: { type: [String, Number], default: null },
  returnsLabel: { type: String, default: '' },
  cash: { type: [String, Number], default: null },
  credit: { type: [String, Number], default: null },
});

const { formatMoney, currencyLabel } = useFormatters();
const { hasAmount } = useReturnsDisplay();

const showReturns = computed(() => hasAmount(props.returns));
// The cash/credit split is gross; it only needs the "before returns" caption when returns exist.
const showSplit = computed(() => showReturns.value && props.cash !== null && props.credit !== null);
</script>
