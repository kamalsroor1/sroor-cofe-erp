<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
      <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-2">
        <span>📦</span>
        <span>{{ $t('inventory.transferred_items_section') }}</span>
      </h2>
    </div>

    <!-- Add Item Row -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2">
      <div class="flex-1">
        <BaseSelect
          :model-value="selectedItemId"
          @update:model-value="$emit('update:selectedItemId', $event)"
          :options="itemOptions"
          :placeholder="$t('inventory.select_item_to_transfer')"
        />
      </div>

      <BaseButton
        variant="primary"
        size="md"
        type="button"
        @click="$emit('add-item')"
        :disabled="!selectedItemId"
        class="shrink-0 font-black shadow-lg shadow-theme-primary"
      >
        <Plus class="w-4 h-4" />
        <span>{{ $t('inventory.add_item') }}</span>
      </BaseButton>
    </div>

    <!-- Items Table (Desktop & Tablet) -->
    <div v-if="items.length > 0">
      <div class="hidden md:block border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="p-3 text-start font-bold">{{ $t('inventory.item_name') }}</th>
              <th class="p-3 text-start font-bold">{{ $t('inventory.code') }}</th>
              <th class="p-3 text-center font-bold w-40">{{ $t('inventory.transferred_qty') }}</th>
              <th class="p-3 text-center font-bold w-14"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr v-for="(item, idx) in items" :key="item.item_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
              <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">
                <div>{{ item.name }}</div>
              </td>
              <td class="p-3 font-mono text-slate-500 dark:text-slate-400">
                {{ item.code || '—' }}
              </td>
              <td class="p-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <input
                    v-model.number="item.quantity"
                    type="number"
                    step="0.001"
                    min="0.001"
                    class="w-28 h-9 px-2 text-center bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-black text-theme-primary focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  >
                  <span class="text-slate-500 dark:text-slate-400 text-[10px] font-tajawal">{{ item.unit }}</span>
                </div>
              </td>
              <td class="p-3 text-center">
                <button
                  type="button"
                  @click="$emit('remove-item', idx)"
                  class="p-2 text-slate-400 hover:text-rose-500 rounded-lg transition cursor-pointer active:scale-90"
                  title="حذف"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Tactile Cards (block md:hidden) -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 space-y-2">
        <div
          v-for="(item, idx) in items"
          :key="item.item_id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/60 flex items-center justify-between gap-3"
        >
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-black text-slate-900 dark:text-white truncate">{{ item.name }}</h4>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ item.code || '—' }}</p>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <div class="flex items-center gap-1">
              <input
                v-model.number="item.quantity"
                type="number"
                step="0.001"
                min="0.001"
                class="w-20 h-10 px-2 text-center bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-mono font-black text-theme-primary focus:ring-2 focus:ring-theme-primary focus:outline-none"
              >
              <span class="text-slate-500 dark:text-slate-400 text-[10px]">{{ item.unit }}</span>
            </div>

            <button
              type="button"
              @click="$emit('remove-item', idx)"
              class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-rose-500 rounded-xl bg-slate-100 dark:bg-slate-800 transition active:scale-90"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="p-8 text-center text-slate-500 text-xs font-bold border border-dashed border-slate-300 dark:border-slate-800 rounded-xl font-tajawal">
      {{ $t('inventory.no_items_in_transfer_prompt') }}
    </div>
  </div>
</template>

<script setup>
import { Plus, Trash2 } from 'lucide-vue-next';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  items: { type: Array, default: () => [] },
  selectedItemId: { type: [Number, String], default: null },
  itemOptions: { type: Array, default: () => [] },
});

defineEmits(['update:selectedItemId', 'add-item', 'remove-item']);
</script>
