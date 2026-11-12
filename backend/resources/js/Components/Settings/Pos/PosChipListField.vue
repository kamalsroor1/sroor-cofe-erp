<script setup>
import { ref, computed } from 'vue';
import { X, Plus } from 'lucide-vue-next';
import { cleanNumber } from '../../../helpers/posSaleSettingsForm';
import { toWesternDigits } from '../../../helpers/digits';

const props = defineProps({
  id: { type: String, required: true },
  label: { type: String, required: true },
  hint: { type: String, default: '' },
  modelValue: { type: Array, default: () => [] },
  max: { type: Number, required: true },
  min: { type: Number, default: 0 },
  maxValue: { type: Number, default: undefined },
  suffix: { type: String, default: '' },
  error: { type: String, default: null },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const draft = ref('');
const invalid = ref(false);
const full = computed(() => props.modelValue.length >= props.max);

const sorted = (list) => [...list].sort((a, b) => Number(a) - Number(b));

const add = () => {
  const clean = cleanNumber(toWesternDigits(draft.value), { min: props.min, max: props.maxValue });
  invalid.value = clean === null;
  if (clean === null || full.value || props.modelValue.includes(clean)) return;
  emit('update:modelValue', sorted([...props.modelValue, clean]));
  draft.value = '';
};

const remove = (value) =>
  emit(
    'update:modelValue',
    props.modelValue.filter((v) => v !== value)
  );
</script>

<template>
  <div class="space-y-2" :data-testid="`setting-${id}`">
    <label :for="`${id}-input`" class="block text-sm font-black text-slate-800 dark:text-slate-200">{{ label }}</label>
    <p v-if="hint" class="text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>

    <ul class="flex flex-wrap gap-2">
      <li
        v-for="value in modelValue"
        :key="value"
        class="inline-flex items-center gap-1.5 ps-3 pe-1 min-h-[44px] rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-mono font-black text-slate-800 dark:text-slate-200"
      >
        <span dir="ltr">{{ value }}{{ suffix }}</span>
        <button
          v-if="!disabled"
          type="button"
          class="w-11 h-11 rounded-lg flex items-center justify-center text-slate-400 hover:text-rose-500 cursor-pointer"
          :aria-label="$t('settings.pos_remove_value')"
          :data-testid="`${id}-remove-${value}`"
          @click="remove(value)"
        >
          <X class="w-4 h-4" aria-hidden="true" />
        </button>
      </li>
    </ul>

    <div v-if="!disabled && !full" class="flex items-center gap-2 max-w-xs">
      <input
        :id="`${id}-input`"
        v-model="draft"
        type="text"
        inputmode="decimal"
        dir="ltr"
        autocomplete="off"
        :placeholder="$t('settings.pos_value_placeholder')"
        :data-testid="`${id}-input`"
        class="flex-1 min-h-[44px] px-3 rounded-xl border bg-white dark:bg-slate-900 text-center font-mono text-slate-900 dark:text-white"
        :class="invalid ? 'border-rose-500' : 'border-slate-200 dark:border-slate-700'"
        @keydown.enter.prevent="add"
      />
      <button
        type="button"
        class="min-h-[44px] px-4 rounded-xl bg-theme-primary text-white font-black flex items-center gap-1.5 cursor-pointer"
        :data-testid="`${id}-add`"
        @click="add"
      >
        <Plus class="w-4 h-4" aria-hidden="true" />
        <span>{{ $t('settings.pos_add_value') }}</span>
      </button>
    </div>
    <p v-if="error" class="text-xs font-bold text-rose-600" role="alert">{{ error }}</p>
  </div>
</template>
