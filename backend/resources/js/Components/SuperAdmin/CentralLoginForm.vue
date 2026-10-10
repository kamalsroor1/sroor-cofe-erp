<script setup>
import { ref } from 'vue';
import { Mail, Lock, LogIn } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  initialEmail: { type: String, default: '' },
  isSubmitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['submit']);

const email = ref(props.initialEmail);
const password = ref('');

const submit = () => {
  if (props.isSubmitting) return;
  emit('submit', { email: email.value.trim(), password: password.value });
};
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <BaseInput
      v-model="email"
      type="email"
      name="email"
      :label="$t('super.central_auth.email_label')"
      :leading-icon="Mail"
      autocomplete="username"
      inputmode="email"
      dir="ltr"
      required
    />
    <BaseInput
      v-model="password"
      type="password"
      name="password"
      :label="$t('super.central_auth.password_label')"
      :leading-icon="Lock"
      autocomplete="current-password"
      dir="ltr"
      required
    />

    <p v-if="error" role="alert" class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ error }}</p>

    <BaseButton
      type="submit"
      variant="primary"
      size="lg"
      full-width
      :icon="LogIn"
      :loading="isSubmitting"
      :disabled="!email || !password"
      :label="$t('super.central_auth.sign_in')"
    />
  </form>
</template>
