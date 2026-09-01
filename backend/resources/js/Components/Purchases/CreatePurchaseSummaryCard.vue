<template>
  <div class="p-5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-lg grid grid-cols-1 sm:grid-cols-2 gap-6 font-tajawal">
    <div class="space-y-3">
      <div>
        <BaseTextarea
          :model-value="notes"
          @update:model-value="$emit('update:notes', $event)"
          :label="$t('purchases.invoice_notes')"
          :placeholder="$t('purchases.invoice_notes_placeholder')"
          :rows="2"
        />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <BaseNumberInput
            :model-value="paidAmount"
            @update:model-value="$emit('update:paidAmount', $event)"
            :label="$t('purchases.paid_to_supplier')"
            :decimals="3"
            class="font-mono text-emerald-600 dark:text-emerald-400 font-bold"
          />
        </div>

        <div>
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
    <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2.5 font-mono text-xs self-center">
      <div class="flex justify-between text-slate-600 dark:text-slate-300 font-sans font-tajawal">
        <span>{{ $t('purchases.items_total_value') }}</span>
        <span class="font-mono font-bold">{{ formatMoney(subtotal) }} {{ $t('common.currency') }}</span>
      </div>
      <div v-if="discount > 0" class="flex justify-between text-rose-500 font-sans font-tajawal">
        <span>{{ $t('purchases.discount_earned') }}</span>
        <span class="font-mono font-bold">-{{ formatMoney(discount) }} {{ $t('common.currency') }}</span>
      </div>
      <div class="flex justify-between text-base font-black text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-sans font-tajawal">
        <span>{{ $t('purchases.final_net_total') }}</span>
        <span class="font-mono text-theme-primary">{{ formatMoney(netTotal) }} {{ $t('common.currency') }}</span>
      </div>
      <div v-if="remaining > 0" class="flex justify-between text-rose-500 font-bold font-sans font-tajawal pt-1">
        <span>{{ $t('purchases.remaining_debt') }}</span>
        <span class="font-mono">{{ formatMoney(remaining) }} {{ $t('common.currency') }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

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
