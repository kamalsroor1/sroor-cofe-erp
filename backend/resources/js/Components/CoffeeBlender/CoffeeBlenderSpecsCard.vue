<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
    <h2 class="text-xs font-bold text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
      <span>⚙️</span>
      <span>{{ $t('inventory.blend_specs_title') }}</span>
    </h2>

    <div class="space-y-3">
      <!-- Blend Name -->
      <BaseInput
        :model-value="blendName"
        @update:model-value="$emit('update:blendName', $event)"
        :label="$t('inventory.blend_name')"
        :placeholder="$t('inventory.blend_name_placeholder')"
      />

      <!-- Target Weight Presets -->
      <div class="space-y-1.5">
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.target_weight') }}</label>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <button
            v-for="w in presetWeights"
            :key="w.value"
            type="button"
            @click="$emit('set-target-weight', w.value)"
            class="min-h-[44px] py-2.5 px-3 rounded-xl border text-xs font-bold transition cursor-pointer text-center active:scale-95"
            :class="targetWeightGrams === w.value ? 'bg-theme-primary text-white font-black border-theme-primary shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700'"
          >
            {{ w.label }}
          </button>
        </div>
      </div>

      <!-- Custom Weight, Roast, Grind -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
        <BaseNumberInput
          :model-value="targetWeightGrams"
          @update:model-value="$emit('update:targetWeightGrams', Number($event))"
          :label="$t('inventory.custom_weight')"
          :min="1"
          :step="1"
        />

        <BaseSelect
          :model-value="roastType"
          @update:model-value="$emit('update:roastType', $event)"
          :label="$t('inventory.roast_type')"
          :options="roastOptions"
        />

        <BaseSelect
          :model-value="grindLevel"
          @update:model-value="$emit('update:grindLevel', $event)"
          :label="$t('inventory.grind_level')"
          :options="grindOptions"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';

defineProps({
  blendName: { type: String, default: '' },
  targetWeightGrams: { type: Number, default: 250 },
  presetWeights: { type: Array, default: () => [] },
  roastType: { type: String, default: 'وسط' },
  grindLevel: { type: String, default: 'تركي ناعم' },
  roastOptions: { type: Array, default: () => [] },
  grindOptions: { type: Array, default: () => [] },
});

defineEmits([
  'update:blendName',
  'update:targetWeightGrams',
  'set-target-weight',
  'update:roastType',
  'update:grindLevel',
]);
</script>
