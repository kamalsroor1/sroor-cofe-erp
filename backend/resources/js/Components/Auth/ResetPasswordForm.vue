<script setup>
// SIGN-15: new-password form of the shop's reset page (opened from the emailed link).
import { AlertTriangle, Save } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import BasePasswordInput from '../Form/BasePasswordInput.vue';

defineProps({
  email: { type: String, required: true },
  minLength: { type: Number, required: true },
  errors: { type: Object, required: true },
  formError: { type: String, default: '' },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['submit']);
const form = defineModel('form', { type: Object, required: true });
</script>

<template>
  <form class="space-y-4" novalidate data-testid="reset-password-form" @submit.prevent="$emit('submit')">
    <p dir="ltr" class="text-center font-mono text-xs font-bold text-slate-700 dark:text-slate-200">{{ email }}</p>
    <div
      v-if="formError"
      role="alert"
      data-testid="reset-password-error"
      class="flex items-start gap-2 rounded-2xl border border-rose-500/20 bg-rose-500/10 p-3.5 text-xs font-bold text-rose-600 dark:text-rose-400"
    >
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
      <span>{{ formError }}</span>
    </div>
    <BasePasswordInput
      v-model="form.password"
      :label="$t('auth.password_reset.new_label')"
      :hint="$t('auth.password_reset.policy_hint', { min: minLength })"
      :error="errors.password"
      autocomplete="new-password"
      name="password"
      data-testid="new-password"
      required
      :disabled="isSubmitting"
    />
    <BasePasswordInput
      v-model="form.password_confirmation"
      :label="$t('auth.password_reset.confirm_label')"
      :error="errors.password_confirmation"
      autocomplete="new-password"
      name="password_confirmation"
      data-testid="confirm-password"
      required
      :disabled="isSubmitting"
    />
    <BaseButton
      type="submit"
      variant="primary"
      size="lg"
      full-width
      :icon="Save"
      :loading="isSubmitting"
      :label="$t('auth.password_reset.submit')"
    />
  </form>
</template>
