<template>
  <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-3 font-tajawal">
    <!-- Preset Periods & Store Selector -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-1.5">
        <button
          v-for="p in presets"
          :key="p.key"
          type="button"
          @click="$emit('set-period', p.key)"
          class="min-h-[36px] px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer active:scale-95"
          :class="period === p.key ? 'bg-theme-primary text-white font-black shadow-sm' : 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-800'"
        >
          {{ p.label }}
        </button>
      </div>

      <!-- Store Selector Filter -->
      <div class="w-full sm:w-56">
        <BaseSelect
          :model-value="storeId"
          @update:model-value="$emit('update:storeId', $event)"
          :options="storeOptions"
          :searchable="false"
        />
      </div>
    </div>

    <!-- Custom Dates & Stock Filter Row -->
    <div class="flex flex-wrap items-center justify-between gap-3 pt-2.5 border-t border-slate-200 dark:border-slate-800/80">
      <div class="flex flex-wrap items-center gap-2">
        <div class="w-36">
          <BaseDatePicker
            :model-value="from"
            @update:model-value="$emit('update:from', $event); $emit('date-change')"
            :placeholder="$t('common.from')"
          />
        </div>
        <span class="text-xs text-slate-400 font-bold">—</span>
        <div class="w-36">
          <BaseDatePicker
            :model-value="to"
            @update:model-value="$emit('update:to', $event); $emit('date-change')"
            :placeholder="$t('common.to')"
          />
        </div>
      </div>

      <div v-if="activeTab === 'inventory'" class="flex items-center gap-2">
        <BaseSelect
          :model-value="stockFilter"
          @update:model-value="$emit('update:stockFilter', $event)"
          :options="stockOptions"
          wrapper-class="w-44"
          :searchable="false"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseDatePicker from '../Form/BaseDatePicker.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

const props = defineProps({
  presets: { type: Array, default: () => [] },
  period: { type: String, default: 'today' },
  from: { type: String, default: '' },
  to: { type: String, default: '' },
  storeId: { type: [String, Number], default: 'all' },
  stores: { type: Array, default: () => [] },
  stockFilter: { type: String, default: 'all' },
  activeTab: { type: String, default: 'sales' },
});

defineEmits(['set-period', 'update:from', 'update:to', 'update:storeId', 'update:stockFilter', 'date-change']);

const storeOptions = computed(() => [
  { value: 'all', label: t('reports.all_stores_branches') },
  ...props.stores.map(s => ({ value: s.id, label: s.name }))
]);

const stockOptions = computed(() => [
  { value: 'all', label: t('inventory.all_stock') },
  { value: 'in_stock', label: t('reports.in_stock_only') },
  { value: 'low_stock', label: t('reports.low_stock_only') },
  { value: 'out_of_stock', label: t('reports.out_of_stock_only') },
]);
</script>
