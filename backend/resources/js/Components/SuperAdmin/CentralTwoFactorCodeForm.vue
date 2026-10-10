<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import { KeyRound, ShieldCheck, LifeBuoy } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  isSubmitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
  allowRecoveryCode: { type: Boolean, default: true },
  submitLabel: { type: String, default: '' },
});

const emit = defineEmits(['submit', 'mode-change']);

const useRecovery = ref(false);
const code = ref('');
const recoveryCode = ref('');
const inputRef = ref(null);

const normalizedCode = computed(() => code.value.replace(/\D/g, '').slice(0, 6));
const canSubmit = computed(() =>
  useRecovery.value ? recoveryCode.value.trim().length > 0 : normalizedCode.value.length === 6
);

const focusInput = () => nextTick(() => inputRef.value?.focus());

const toggleMode = () => {
  useRecovery.value = !useRecovery.value;
  code.value = '';
  recoveryCode.value = '';
  emit('mode-change', useRecovery.value ? 'recovery' : 'code');
  focusInput();
};

const submit = () => {
  if (props.isSubmitting || !canSubmit.value) return;
  emit('submit', useRecovery.value ? { recoveryCode: recoveryCode.value.trim() } : { code: normalizedCode.value });
};

onMounted(focusInput);
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <BaseInput
      v-if="!useRecovery"
      ref="inputRef"
      v-model="code"
      name="code"
      :label="$t('super.central_auth.code_label')"
      :leading-icon="KeyRound"
      autocomplete="one-time-code"
      inputmode="numeric"
      maxlength="6"
      dir="ltr"
      input-class="text-center tracking-[0.5em] font-mono text-lg"
      required
    />
    <BaseInput
      v-else
      ref="inputRef"
      v-model="recoveryCode"
      name="recovery_code"
      :label="$t('super.central_auth.recovery_code_label')"
      :leading-icon="LifeBuoy"
      autocomplete="off"
      maxlength="64"
      dir="ltr"
      input-class="text-center font-mono"
      required
    />

    <p v-if="error" role="alert" class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ error }}</p>

    <BaseButton
      type="submit"
      variant="primary"
      size="lg"
      full-width
      :icon="ShieldCheck"
      :loading="isSubmitting"
      :disabled="!canSubmit"
      :label="submitLabel || $t('super.central_auth.verify')"
    />

    <button
      v-if="allowRecoveryCode"
      type="button"
      class="w-full min-h-11 rounded-xl text-xs font-bold text-slate-600 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary)] dark:text-slate-300"
      @click="toggleMode"
    >
      {{ useRecovery ? $t('super.central_auth.use_authenticator_code') : $t('super.central_auth.use_recovery_code') }}
    </button>
  </form>
</template>
