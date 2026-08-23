<template>
  <AppModal
    :show="show"
    :title="$t('inventory.transfer_details_modal_title', { number: transfer?.transfer_number || '' })"
    @close="$emit('close')"
  >
    <div v-if="transfer" class="space-y-4 font-tajawal text-xs">
      <!-- Top Info Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('inventory.from_store') }}:</span>
          <span class="font-bold text-theme-primary text-sm">{{ transfer.from_store_name }}</span>
        </div>
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('inventory.to_store') }}:</span>
          <span class="font-bold text-emerald-500 text-sm">{{ transfer.to_store_name }}</span>
        </div>
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('common.date') }}:</span>
          <span class="text-slate-900 dark:text-slate-200 font-mono">{{ transfer.transfer_date }}</span>
        </div>
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('inventory.store_user') }}:</span>
          <span class="text-slate-900 dark:text-slate-200">{{ transfer.user_name || '—' }}</span>
        </div>
      </div>

      <!-- Items Table -->
      <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100 dark:bg-slate-950 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="p-3 text-start font-bold">{{ $t('inventory.item_name') }}</th>
              <th class="p-3 text-start font-bold">{{ $t('inventory.code') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('inventory.transferred_qty_col') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
            <tr v-for="it in transfer.items" :key="it.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
              <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ it.item_name }}</td>
              <td class="p-3 font-mono text-slate-500 dark:text-slate-400">{{ it.item_code || '—' }}</td>
              <td class="p-3 text-end font-mono font-black text-cyan-600 dark:text-cyan-400 text-sm">
                {{ formatQty(it.quantity) }} <span class="text-[10px] font-normal font-tajawal text-slate-400">{{ it.unit }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Notes -->
      <div v-if="transfer.notes" class="p-3.5 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-slate-600 dark:text-slate-300">
        <span class="font-bold text-slate-900 dark:text-white block mb-1">{{ $t('inventory.transfer_notes_label') }}</span>
        <span>{{ transfer.notes }}</span>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatQty } = useFormatters();

defineProps({
  show: { type: Boolean, default: false },
  transfer: { type: Object, default: null },
});

defineEmits(['close']);
</script>
