<script setup>
import { computed, useId } from 'vue';
import { toWesternDigits } from '../../helpers/digits';

const props = defineProps({
  label: { type: String, default: '' },
  error: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['complete']);

const model = defineModel({ type: String, default: '' });

const inputId = useId();
const errorId = computed(() => `${inputId}-error`);
const normalize = (raw) => toWesternDigits(raw).replace(/\D/g, '').slice(0, 6);

const apply = (value) => {
  model.value = value;
  if (value.length === 6) emit('complete', value);
};

const onInput = (event) => {
  const value = normalize(event.target.value);
  event.target.value = value;
  apply(value);
};

const onPaste = (event) => {
  event.preventDefault();
  apply(normalize(event.clipboardData?.getData('text')));
};
</script>

<template>
  <div class="flex flex-col gap-2">
    <label :for="inputId" class="text-sm font-bold text-mk-ink">{{ label }}</label>
    <!-- One real input (not six boxes) so the OS can autofill the emailed code and paste just works. -->
    <input
      :id="inputId"
      :value="model"
      data-testid="signup-code-input"
      type="text"
      inputmode="numeric"
      pattern="[0-9]*"
      maxlength="6"
      autocomplete="one-time-code"
      autocapitalize="off"
      spellcheck="false"
      dir="ltr"
      :disabled="disabled"
      :aria-invalid="error ? 'true' : 'false'"
      :aria-describedby="error ? errorId : undefined"
      class="min-h-16 w-full rounded-2xl border-[1.5px] bg-mk-surface px-4 text-center font-mono text-[32px] font-bold tracking-[0.5em] text-mk-ink outline-hidden transition placeholder:text-mk-faint focus:border-mk-primary focus:ring-4 focus:ring-mk-primary/20 disabled:opacity-60"
      :class="props.error ? 'border-rose-500' : 'border-mk-line'"
      @input="onInput"
      @paste="onPaste"
    />
    <p v-if="error" :id="errorId" role="alert" class="text-sm font-bold text-rose-600 dark:text-rose-400">
      {{ error }}
    </p>
  </div>
</template>
