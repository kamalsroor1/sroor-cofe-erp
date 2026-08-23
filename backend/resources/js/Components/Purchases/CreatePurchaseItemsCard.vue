<template>
  <div class="p-5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-lg space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xs font-black text-theme-primary flex items-center gap-2">
        <Package class="w-4 h-4" />
        <span>{{ $t('purchases.supply_items_section') }}</span>
      </h2>

      <BaseButton
        type="button"
        variant="secondary"
        size="sm"
        @click="$emit('add-line')"
        class="font-bold flex items-center gap-1.5"
      >
        <Plus class="w-3.5 h-3.5" />
        <span>{{ $t('purchases.add_item_line') }}</span>
      </BaseButton>
    </div>

    <!-- 1. Desktop & Tablet Table (hidden md:block) -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-start text-xs border-collapse">
        <thead>
          <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
            <th class="p-3 text-start font-bold">{{ $t('purchases.item_material') }}</th>
            <th class="p-3 text-center font-bold w-32">{{ $t('common.quantity') }}</th>
            <th class="p-3 text-center font-bold w-36">{{ $t('inventory.purchase_price') }}</th>
            <th class="p-3 text-end font-bold w-36">{{ $t('common.total') }}</th>
            <th class="p-3 text-center font-bold w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50">
          <tr v-for="(line, idx) in items" :key="idx" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
            <td class="p-2.5">
              <select
                v-model="line.item_id"
                @change="$emit('item-select', line)"
                required
                class="w-full h-10 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
              >
                <option value="">{{ $t('purchases.select_item_from_list') }}</option>
                <option v-for="it in availableItems" :key="it.id" :value="it.id">
                  {{ it.name }} ({{ it.code || $t('purchases.no_code') }}) - {{ it.unit }}
                </option>
              </select>
            </td>

            <td class="p-2.5">
              <input
                v-model="line.quantity"
                type="number"
                step="0.001"
                min="0.001"
                required
                class="w-full h-10 px-2 text-center bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-theme-primary font-mono font-bold focus:ring-2 focus:ring-theme-primary focus:outline-none"
                placeholder="1.000"
              >
            </td>

            <td class="p-2.5">
              <input
                v-model="line.cost_price"
                type="number"
                step="0.001"
                min="0"
                required
                class="w-full h-10 px-2 text-center bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-emerald-500 dark:text-emerald-400 font-mono font-bold focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                placeholder="0.00"
              >
            </td>

            <td class="p-2.5 text-end font-mono font-black text-slate-900 dark:text-white text-sm">
              {{ formatMoney((parseFloat(line.quantity) || 0) * (parseFloat(line.cost_price) || 0)) }} {{ $t('common.currency') }}
            </td>

            <td class="p-2.5 text-center">
              <button
                type="button"
                @click="$emit('remove-line', idx)"
                :disabled="items.length <= 1"
                class="p-2 text-slate-400 hover:text-rose-500 rounded-lg transition-colors disabled:opacity-20 cursor-pointer active:scale-90"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 2. Mobile Responsive Tactile Cards (block md:hidden) -->
    <div class="block md:hidden space-y-3">
      <div
        v-for="(line, idx) in items"
        :key="idx"
        class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/60 space-y-3"
      >
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs font-bold text-slate-500 font-mono">#{{ idx + 1 }}</span>
          <button
            type="button"
            @click="$emit('remove-line', idx)"
            :disabled="items.length <= 1"
            class="p-1.5 text-rose-500 bg-rose-500/10 rounded-lg transition disabled:opacity-20 active:scale-90"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-slate-500 mb-1">{{ $t('purchases.item_material') }}</label>
          <select
            v-model="line.item_id"
            @change="$emit('item-select', line)"
            required
            class="w-full h-11 px-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          >
            <option value="">{{ $t('purchases.select_item_from_list') }}</option>
            <option v-for="it in availableItems" :key="it.id" :value="it.id">
              {{ it.name }} ({{ it.code || $t('purchases.no_code') }})
            </option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[11px] font-bold text-slate-500 mb-1">{{ $t('common.quantity') }}</label>
            <input
              v-model="line.quantity"
              type="number"
              step="0.001"
              min="0.001"
              required
              class="w-full h-10 px-2 text-center bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-theme-primary font-mono font-bold focus:ring-2 focus:ring-theme-primary focus:outline-none"
            >
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-500 mb-1">{{ $t('inventory.purchase_price') }}</label>
            <input
              v-model="line.cost_price"
              type="number"
              step="0.001"
              min="0"
              required
              class="w-full h-10 px-2 text-center bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-emerald-500 font-mono font-bold focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            >
          </div>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-800 text-xs font-mono">
          <span class="font-sans font-bold text-slate-500">{{ $t('common.total') }}:</span>
          <span class="font-black text-slate-900 dark:text-white">
            {{ formatMoney((parseFloat(line.quantity) || 0) * (parseFloat(line.cost_price) || 0)) }} {{ $t('common.currency') }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Package, Plus, Trash2 } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  items: { type: Array, default: () => [] },
  availableItems: { type: Array, default: () => [] },
});

defineEmits(['add-line', 'remove-line', 'item-select']);
</script>
