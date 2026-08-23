<template>
  <AppModal
    :show="show"
    :title="$t('purchases.purchase_details_title', { number: purchase?.purchase_number })"
    @close="$emit('close')"
  >
    <div v-if="purchase" class="space-y-4 font-tajawal text-xs">
      <!-- Summary Header -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl">
        <div>
          <span class="text-slate-500 dark:text-slate-400 block font-bold">{{ $t('purchases.supplier') }}:</span>
          <span class="text-slate-900 dark:text-white font-bold">{{ purchase.supplier_name }}</span>
        </div>
        <div>
          <span class="text-slate-500 dark:text-slate-400 block font-bold">{{ $t('purchases.purchase_date') }}:</span>
          <span class="text-slate-700 dark:text-slate-300 font-mono">{{ purchase.purchase_date }}</span>
        </div>
        <div>
          <span class="text-slate-500 dark:text-slate-400 block font-bold">{{ $t('purchases.received_branch') }}:</span>
          <span class="text-slate-700 dark:text-slate-300">{{ purchase.store_name || $t('common.main_branch') }}</span>
        </div>
        <div>
          <span class="text-slate-500 dark:text-slate-400 block font-bold">{{ $t('common.status') }}:</span>
          <span class="font-bold" :class="purchase.status === 'confirmed' ? 'text-emerald-500' : 'text-rose-500'">
            {{ purchase.status === 'confirmed' ? $t('purchases.status_confirmed_badge') : $t('purchases.status_cancelled_badge') }}
          </span>
        </div>
      </div>

      <!-- Items Table -->
      <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="p-2.5 text-start font-bold">{{ $t('purchases.item_material') }}</th>
              <th class="p-2.5 text-end font-bold">{{ $t('common.quantity') }}</th>
              <th class="p-2.5 text-end font-bold">{{ $t('inventory.purchase_price') }}</th>
              <th class="p-2.5 text-end font-bold">{{ $t('common.total') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50">
            <tr v-for="item in purchase.items" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
              <td class="p-2.5 font-bold text-slate-900 dark:text-white">{{ item.item_name }}</td>
              <td class="p-2.5 text-end font-mono text-theme-primary font-bold">{{ item.quantity }} {{ item.unit }}</td>
              <td class="p-2.5 text-end font-mono text-slate-700 dark:text-slate-300">{{ formatMoney(item.cost_price) }} {{ $t('common.currency') }}</td>
              <td class="p-2.5 text-end font-mono font-bold text-emerald-500">{{ formatMoney(item.total_price) }} {{ $t('common.currency') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Financial Breakdown -->
      <div class="p-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1.5 font-mono text-xs">
        <div class="flex justify-between text-slate-600 dark:text-slate-300 font-tajawal">
          <span>{{ $t('purchases.items_subtotal') }}</span>
          <span class="font-mono font-bold">{{ formatMoney(purchase.subtotal) }} {{ $t('common.currency') }}</span>
        </div>
        <div v-if="purchase.discount_amount > 0" class="flex justify-between text-rose-500 font-tajawal">
          <span>{{ $t('purchases.discount_earned') }}</span>
          <span class="font-mono font-bold">-{{ formatMoney(purchase.discount_amount) }} {{ $t('common.currency') }}</span>
        </div>
        <div v-if="purchase.additional_expenses_total > 0" class="flex justify-between text-theme-primary font-tajawal">
          <span>{{ $t('purchases.additional_expenses_loaded') }}</span>
          <span class="font-mono font-bold">+{{ formatMoney(purchase.additional_expenses_total) }} {{ $t('common.currency') }}</span>
        </div>
        <div class="flex justify-between text-base font-black text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-tajawal">
          <span>{{ $t('invoices.net_invoice') }}</span>
          <span class="text-emerald-500 font-mono">{{ formatMoney(purchase.net_total) }} {{ $t('common.currency') }}</span>
        </div>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  show: { type: Boolean, default: false },
  purchase: { type: Object, default: null },
});

defineEmits(['close']);
</script>
