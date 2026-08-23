<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
      <h2 class="text-xs font-bold text-slate-700 dark:text-slate-400 flex items-center gap-2">
        <span>🫘</span>
        <span>{{ $t('inventory.raw_beans_components') }}</span>
      </h2>
      <span
        class="px-2.5 py-0.5 rounded-full text-xs font-mono font-black border"
        :class="totalPercentage === 100 ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-500'"
      >
        {{ $t('inventory.total_ratio_badge', { pct: totalPercentage }) }}
      </span>
    </div>

    <!-- Add Component Row -->
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <BaseSelect
          :model-value="selectedItemIdToAdd"
          @update:model-value="$emit('update:selectedItemIdToAdd', $event)"
          :placeholder="$t('inventory.select_blend_item_prompt')"
          :options="itemOptions"
        />
      </div>

      <BaseButton
        variant="primary"
        size="md"
        @click="$emit('add-component')"
        :disabled="!selectedItemIdToAdd"
        class="h-10 shrink-0 shadow-sm"
      >
        + {{ $t('common.add') }}
      </BaseButton>
    </div>

    <!-- Components List -->
    <div class="space-y-2.5">
      <div
        v-for="(comp, idx) in calculatedComponents"
        :key="comp.item_id"
        class="p-3.5 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs transition hover:border-slate-300 dark:hover:border-slate-700"
      >
        <div class="sm:w-1/3 min-w-0">
          <div class="font-bold text-slate-900 dark:text-white truncate">{{ comp.name }}</div>
          <div class="text-[10px] text-slate-400 font-mono mt-0.5">
            {{ formatMoney(comp.selling_price) }} {{ $t('common.currency') }} / {{ $t('inventory.unit_weight_short') }}
            ({{ $t('inventory.current_stock') }}: {{ comp.current_stock }} {{ $t('inventory.unit_weight_short') }})
          </div>
        </div>

        <!-- Percentage Slider -->
        <div class="sm:w-1/3 flex items-center gap-2.5">
          <input
            :value="components[idx].percentage"
            @input="$emit('update-percentage', { index: idx, value: Number($event.target.value) })"
            type="range"
            min="0"
            max="100"
            step="5"
            class="flex-1 h-2 bg-slate-200 dark:bg-slate-800 accent-theme-primary cursor-pointer rounded-lg"
          >
          <div class="flex items-center gap-1 shrink-0">
            <input
              :value="components[idx].percentage"
              @input="$emit('update-percentage', { index: idx, value: Number($event.target.value) })"
              type="number"
              min="0"
              max="100"
              class="w-12 h-7 text-center bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold text-theme-primary focus:outline-none"
            >
            <span class="text-slate-500 dark:text-slate-400 text-[10px]">%</span>
          </div>
        </div>

        <!-- Calculated Grams & Price -->
        <div class="sm:w-1/3 flex items-center justify-between sm:justify-end gap-3 font-mono">
          <div class="text-end">
            <div class="font-black text-theme-primary text-xs">{{ comp.grams }} {{ $t('inventory.unit_gram') }}</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ formatMoney(comp.price) }} {{ $t('common.currency') }}</div>
          </div>

          <button
            type="button"
            @click="$emit('remove-component', idx)"
            class="min-h-[36px] min-w-[36px] flex items-center justify-center p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-lg transition cursor-pointer"
            :title="$t('common.delete')"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>

      <div v-if="components.length === 0" class="py-8 text-center text-xs text-slate-400 font-bold">
        {{ $t('inventory.blend_components_empty') }}
      </div>
    </div>

    <!-- Extra Spices (Cardamom) & Notes -->
    <div class="pt-3 border-t border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-3">
      <BaseNumberInput
        :model-value="cardamomGrams"
        @update:model-value="$emit('update:cardamomGrams', Number($event))"
        :label="$t('inventory.cardamom_spices')"
        :min="0"
        :step="1"
        placeholder="0"
      />

      <BaseInput
        :model-value="notes"
        @update:model-value="$emit('update:notes', $event)"
        :label="$t('inventory.blend_notes')"
        :placeholder="$t('inventory.notes_placeholder')"
      />
    </div>
  </div>
</template>

<script setup>
import { Trash2 } from 'lucide-vue-next';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseButton from '../Common/BaseButton.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  components: { type: Array, default: () => [] },
  calculatedComponents: { type: Array, default: () => [] },
  totalPercentage: { type: Number, default: 0 },
  selectedItemIdToAdd: { type: [Object, Number, String], default: null },
  itemOptions: { type: Array, default: () => [] },
  cardamomGrams: { type: Number, default: 0 },
  notes: { type: String, default: '' },
});

defineEmits([
  'update:selectedItemIdToAdd',
  'add-component',
  'remove-component',
  'update-percentage',
  'update:cardamomGrams',
  'update:notes',
]);
</script>
