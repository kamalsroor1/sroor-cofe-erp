<script setup>
import { ref } from 'vue';
import { Lock, Save } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  email: { type: String, required: true },
  minLength: { type: Number, default: 12 },
  isSubmitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['submit']);

const password = ref('');
const passwordConfirmation = ref('');

const submit = () => {
  if (props.isSubmitting) return;
  emit('submit', { password: password.value, passwordConfirmation: passwordConfirmation.value });
};
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <p dir="ltr" class="text-center font-mono text-xs font-bold text-slate-700 dark:text-slate-200">{{ email }}</p>
    <BaseInput
      v-model="password"
      type="password"
      name="password"
      :label="$t('super.central_auth.new_password_label')"
      :leading-icon="Lock"
      :minlength="minLength"
      autocomplete="new-password"
      dir="ltr"
      required
    />
    <BaseInput
      v-model="passwordConfirmation"
      type="password"
      name="password_confirmation"
      :label="$t('super.central_auth.confirm_password_label')"
      :leading-icon="Lock"
      :minlength="minLength"
      autocomplete="new-password"
      dir="ltr"
      required
    />
    <p v-if="error" role="alert" class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ error }}</p>
    <BaseButton
      type="submit"
      variant="primary"
      size="lg"
      full-width
      :icon="Save"
      :loading="isSubmitting"
      :disabled="password.length < minLength || !passwordConfirmation"
      :label="$t('super.central_auth.reset_submit')"
    />
  </form>
</template>
