<template>
  <AppModal :show="show" :title="$t('pos.invoice_saved_success')" max-width="md" @close="$emit('close')">
    <div class="text-center p-4 space-y-4 font-tajawal select-none">
      <div
        class="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 flex items-center justify-center mx-auto"
      >
        <CheckCircle2 class="w-8 h-8 stroke-[2.5]" />
      </div>
      <div>
        <h3 class="text-base font-black text-slate-900 dark:text-white">
          {{ $t('pos.invoice_number_badge', { number: invoice?.invoice_number }) }}
        </h3>
        <p class="text-xs text-slate-500 mt-1">
          {{ $t('pos.net_amount_label', { amount: formatMoney(invoice?.net_amount) }) }}
        </p>
      </div>
      <div
        v-if="hasChange"
        data-testid="pos-success-change"
        class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30"
      >
        <span class="text-sm font-bold text-emerald-700 dark:text-emerald-400">{{ $t('pos.change_due_label') }}</span>
        <span
          data-testid="pos-success-change-amount"
          class="text-xl font-black font-mono text-emerald-700 dark:text-emerald-400"
        >
          {{ formatMoney(changeAmount) }} <span class="text-xs font-bold">{{ $t('common.currency') }}</span>
        </span>
      </div>
      <div class="flex gap-2">
        <button
          type="button"
          @click="$emit('print')"
          class="min-h-[44px] flex-1 py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-xl text-xs font-black transition flex items-center justify-center gap-2 cursor-pointer"
        >
          <Printer class="w-4 h-4" />
          <span>{{ $t('pos.print_receipt') }}</span>
        </button>
        <button
          type="button"
          @click="$emit('close')"
          class="min-h-[44px] flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-black transition cursor-pointer"
        >
          {{ $t('pos.new_invoice_enter') }}
        </button>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import { Printer, CheckCircle2 } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { isPositive } from '../../helpers/decimal';

const { formatMoney } = useFormatters();

const props = defineProps({
  show: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
  // Change handed back to the customer, as returned by the server (change_amount).
  changeAmount: { type: [String, Number], default: '0.000' },
});

const hasChange = computed(() => isPositive(props.changeAmount));

defineEmits(['close', 'print']);
</script>
