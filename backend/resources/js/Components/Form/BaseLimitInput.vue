<script setup>
import { ref, computed, watch } from 'vue';
import BaseInput from './BaseInput.vue';
import BaseCheckbox from './BaseCheckbox.vue';
import { isUnlimited } from '../../helpers/planLimits';

// A numeric limit where null means "unlimited". Emits null (never 0) when unlimited.
defineProps({
  id: { type: String, default: null },
  label: { type: String, default: '' },
  min: { type: Number, default: 1 },
  error: { type: [String, Array], default: null },
  disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: [Number, String], default: null });

const unlimited = ref(isUnlimited(model.value));
const lastFinite = ref(isUnlimited(model.value) ? null : model.value);
const isFocused = ref(false);

watch(model, (value) => {
  if (isUnlimited(value)) {
    // While the user is clearing the field to retype, keep the input editable.
    if (!isFocused.value) unlimited.value = true;
    return;
  }
  unlimited.value = false;
  lastFinite.value = value;
});

const inputValue = computed({
  get: () => (unlimited.value || isUnlimited(model.value) ? '' : model.value),
  set: (value) => {
    model.value = isUnlimited(value) ? null : value;
  },
});

const setUnlimited = (checked) => {
  unlimited.value = checked;
  model.value = checked ? null : lastFinite.value;
};

const onBlur = () => {
  isFocused.value = false;
  if (isUnlimited(model.value)) unlimited.value = true;
};
</script>

<template>
  <div class="flex flex-col gap-1">
    <BaseInput
      :id="id"
      v-model="inputValue"
      :label="label"
      type="number"
      inputmode="numeric"
      :min="min"
      step="1"
      :placeholder="unlimited ? $t('common.unlimited') : ''"
      :disabled="disabled || unlimited"
      :error="error"
      input-class="font-mono text-start"
      @focus="isFocused = true"
      @blur="onBlur"
    />
    <BaseCheckbox
      :model-value="unlimited"
      :label="$t('common.unlimited')"
      :disabled="disabled"
      @update:model-value="setUnlimited"
    />
  </div>
</template>
