<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg space-y-4 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
      <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-2">
        <span>📦</span>
        <span>{{ $t('returns.return_items_section') }}</span>
      </h2>
    </div>

    <!-- Add Item Row Selector -->
    <div class="flex items-center gap-2">
      <select
        :value="selectedItemToAdd"
        @change="$emit('update:selected-item', items.find(i => i.id === Number($event.target.value)) || null)"
        class="flex-1 h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
      >
        <option :value="null">{{ $t('returns.select_item_to_return') }}</option>
        <option v-for="it in items" :key="it.id" :value="it.id">
          {{ it.name }} ({{ it.code || '—' }}) — {{ $t('inventory.current_stock') }}: {{ it.current_stock }} {{ it.unit }}
        </option>
      </select>

      <button
        type="button"
        @click="$emit('add-item')"
        :disabled="!selectedItemToAdd"
        class="min-h-[40px] px-4 bg-theme-gradient text-white shadow-theme-primary font-black rounded-xl text-xs transition disabled:opacity-30 cursor-pointer shrink-0 active:scale-95"
      >
        {{ $t('returns.add_item_btn') }}
      </button>
    </div>

    <!-- Items Table -->
    <div v-if="itemsList.length > 0" class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
      <table class="w-full text-start text-xs border-collapse">
        <thead>
          <tr class="bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
            <th class="p-3 text-start font-bold">{{ $t('inventory.item_name') }}</th>
            <th class="p-3 text-center font-bold w-28">{{ $t('common.quantity') }}</th>
            <th class="p-3 text-end font-bold w-32">{{ $t('pos.item_price') }}</th>
            <th class="p-3 text-end font-bold w-32">{{ $t('common.total') }}</th>
            <th class="p-3 text-center font-bold w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 font-sans">
          <tr v-for="(item, idx) in itemsList" :key="item.item_id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
            <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">
              <div>{{ item.name }}</div>
              <span class="text-[10px] text-slate-500 font-mono">({{ item.unit }})</span>
            </td>
            <td class="p-3 text-center">
              <input
                v-model.number="item.quantity"
                type="number"
                step="0.001"
                min="0.001"
                class="w-20 h-8 px-2 text-center bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none"
              >
            </td>
            <td class="p-3 text-end">
              <input
                v-model.number="item.unit_price"
                type="number"
                step="0.001"
                min="0"
                class="w-24 h-8 px-2 text-end bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold text-theme-primary focus:outline-none"
              >
            </td>
            <td class="p-3 text-end font-mono font-bold text-rose-600 dark:text-rose-400">
              {{ formatMoney(item.quantity * item.unit_price) }} {{ $t('common.currency') }}
            </td>
            <td class="p-3 text-center">
              <button
                type="button"
                @click="$emit('remove-item', idx)"
                class="p-1.5 text-slate-400 hover:text-rose-500 rounded-lg transition cursor-pointer active:scale-95"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="p-8 text-center text-slate-500 dark:text-slate-400 text-xs font-bold border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
      {{ $t('returns.no_items_in_return_prompt') }}
    </div>
  </div>
</template>

<script setup>
import { Trash2 } from 'lucide-vue-next';

defineProps({
  itemsList: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  selectedItemToAdd: { type: Object, default: null },
});

defineEmits(['update:selected-item', 'add-item', 'remove-item']);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
