<template>
  <AppModal
    :show="show"
    :title="$t('returns.return_details_title', { number: returnDetails?.return_number || '' })"
    @close="$emit('close')"
  >
    <div v-if="returnDetails" class="space-y-4 font-tajawal text-xs">
      <!-- Header info -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl">
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('returns.return_type') }}:</span>
          <span class="font-bold text-slate-900 dark:text-white">
            {{ returnDetails.return_type === 'sales_return' ? $t('returns.sales_return_option') : $t('returns.purchase_return_option') }}
          </span>
        </div>
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('returns.party_name') }}:</span>
          <span class="text-theme-primary font-bold">{{ returnDetails.party_name }}</span>
        </div>
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('common.date') }}:</span>
          <span class="text-slate-900 dark:text-slate-200 font-mono">{{ returnDetails.return_date }}</span>
        </div>
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('returns.responsible_branch') }}</span>
          <span class="text-slate-900 dark:text-slate-200">{{ returnDetails.user_name }} ({{ returnDetails.store_name || $t('common.main_branch') }})</span>
        </div>
      </div>

      <!-- Items Table -->
      <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="p-2.5 text-start font-bold">{{ $t('inventory.item_name') }}</th>
              <th class="p-2.5 text-end font-bold">{{ $t('returns.returned_quantity') }}</th>
              <th class="p-2.5 text-end font-bold">{{ $t('inventory.selling_price') }}</th>
              <th class="p-2.5 text-end font-bold">{{ $t('common.total') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50">
            <tr v-for="it in returnDetails.items" :key="it.id">
              <td class="p-2.5 font-bold text-slate-900 dark:text-white">{{ it.item_name }}</td>
              <td class="p-2.5 text-end font-mono text-theme-primary">{{ it.quantity }} {{ it.unit }}</td>
              <td class="p-2.5 text-end font-mono text-slate-600 dark:text-slate-300">{{ formatMoney(it.unit_price) }} {{ $t('common.currency') }}</td>
              <td class="p-2.5 text-end font-mono font-bold text-rose-600 dark:text-rose-400">{{ formatMoney(it.total_price) }} {{ $t('common.currency') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Total Footer -->
      <div class="p-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between text-xs font-tajawal">
        <span class="font-bold text-slate-700 dark:text-slate-300">{{ $t('returns.total_returns_val') }}:</span>
        <span class="text-base font-black text-rose-600 dark:text-rose-400 font-mono">{{ formatMoney(returnDetails.total_amount) }} {{ $t('common.currency') }}</span>
      </div>

      <div v-if="returnDetails.reason" class="p-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800/80 rounded-xl text-slate-600 dark:text-slate-400 font-tajawal">
        <span class="font-bold text-slate-700 dark:text-slate-300">{{ $t('returns.return_reason_label') }}</span>
        <span>{{ returnDetails.reason }}</span>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';

defineProps({
  show: { type: Boolean, default: false },
  returnDetails: { type: Object, default: null },
});

defineEmits(['close']);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
