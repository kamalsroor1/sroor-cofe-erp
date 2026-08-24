<template>
  <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-3 font-tajawal">
    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
      {{ $t('super.add_custom_unit_title') }}
    </label>
    <div class="flex items-center gap-2">
      <BaseInput
        v-model="inputValue"
        @keyup.enter="$emit('add')"
        :placeholder="$t('super.add_custom_unit_placeholder')"
        class="flex-1"
      />
      <BaseButton
        type="button"
        variant="primary"
        size="md"
        @click="$emit('add')"
        class="bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-black shadow-md shrink-0"
      >
        {{ $t('super.add_to_system_btn') }}
      </BaseButton>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'update:model-value', 'add']);

const inputValue = computed({
  get: () => props.modelValue,
  set: (val) => {
    emit('update:modelValue', val);
    emit('update:model-value', val);
  },
});
</script>
