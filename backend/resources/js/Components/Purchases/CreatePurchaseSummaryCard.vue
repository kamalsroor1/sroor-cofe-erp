<template>
  <div
    class="p-4 sm:p-5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-lg grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 font-tajawal min-w-0"
  >
    <div class="space-y-3 min-w-0">
      <div class="min-w-0">
        <BaseTextarea
          :model-value="notes"
          @update:model-value="$emit('update:notes', $event)"
          :label="$t('purchases.invoice_notes')"
          :placeholder="$t('purchases.invoice_notes_placeholder')"
          :rows="2"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 min-w-0">
        <div class="min-w-0">
          <BaseNumberInput
            :model-value="paidAmount"
            @update:model-value="$emit('update:paidAmount', $event)"
            :label="$t('purchases.paid_to_supplier')"
            :decimals="3"
            class="font-mono text-emerald-600 dark:text-emerald-400 font-bold"
          />
        </div>

        <div class="min-w-0">
          <BaseNumberInput
            :model-value="discountAmount"
            @update:model-value="$emit('update:discountAmount', $event)"
            :label="$t('purchases.discount_earned')"
            :decimals="3"
            class="font-mono text-rose-600 dark:text-rose-400 font-bold"
          />
        </div>
      </div>
    </div>

    <!-- Total Calculation Ledger -->
    <div
      class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2.5 font-mono text-xs self-center w-full min-w-0"
    >
      <div
        class="flex items-center justify-between gap-2 text-slate-600 dark:text-slate-300 font-sans font-tajawal min-w-0"
      >
        <span class="min-w-0 break-words">{{ $t('purchases.items_total_value') }}</span>
        <span class="font-mono font-bold shrink-0">{{ formatMoney(subtotal) }} {{ currencyLabel }}</span>
      </div>
      <div
        v-if="discount > 0"
        class="flex items-center justify-between gap-2 text-rose-500 font-sans font-tajawal min-w-0"
      >
        <span class="min-w-0 break-words">{{ $t('purchases.discount_earned') }}</span>
        <span class="font-mono font-bold shrink-0">-{{ formatMoney(discount) }} {{ currencyLabel }}</span>
      </div>
      <div
        class="flex items-center justify-between gap-2 text-base font-black text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-sans font-tajawal min-w-0"
      >
        <span class="min-w-0 break-words">{{ $t('purchases.final_net_total') }}</span>
        <span class="font-mono text-theme-primary shrink-0">{{ formatMoney(netTotal) }} {{ currencyLabel }}</span>
      </div>
      <div
        v-if="remaining > 0"
        class="flex items-center justify-between gap-2 text-rose-500 font-bold font-sans font-tajawal pt-1 min-w-0"
      >
        <span class="min-w-0 break-words">{{ $t('purchases.remaining_debt') }}</span>
        <span class="font-mono shrink-0">{{ formatMoney(remaining) }} {{ currencyLabel }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney, currencyLabel } = useFormatters();

defineProps({
  notes: { type: String, default: '' },
  paidAmount: { type: [Number, String], default: 0 },
  discountAmount: { type: [Number, String], default: 0 },
  subtotal: { type: Number, default: 0 },
  discount: { type: Number, default: 0 },
  netTotal: { type: Number, default: 0 },
  remaining: { type: Number, default: 0 },
});

defineEmits(['update:notes', 'update:paidAmount', 'update:discountAmount']);
</script>
