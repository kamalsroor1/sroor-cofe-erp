<script setup>
// SIGN-15: email form of the shop's "forgot password" page. After sending it shows the generic
// confirmation from the server (it never says whether the account exists).
import { AlertTriangle, CheckCircle2, Mail, Send } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  isSubmitting: { type: Boolean, default: false },
  sentMessage: { type: String, default: '' },
  error: { type: String, default: '' },
});

const emit = defineEmits(['submit']);
const email = defineModel('email', { type: String, default: '' });

const submit = () => {
  if (props.isSubmitting || !email.value.trim()) return;
  emit('submit');
};
</script>

<template>
  <div
    v-if="sentMessage"
    role="status"
    data-testid="forgot-password-sent"
    class="flex items-start gap-2 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-xs font-bold text-emerald-700 dark:text-emerald-300"
  >
    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
    <span>{{ sentMessage }}</span>
  </div>
  <form v-else class="space-y-4" novalidate data-testid="forgot-password-form" @submit.prevent="submit">
    <div
      v-if="error"
      role="alert"
      class="flex items-start gap-2 rounded-2xl border border-rose-500/20 bg-rose-500/10 p-3.5 text-xs font-bold text-rose-600 dark:text-rose-400"
    >
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
      <span>{{ error }}</span>
    </div>
    <BaseInput
      v-model="email"
      type="email"
      name="email"
      :label="$t('auth.password_reset.email_label')"
      :placeholder="$t('auth.password_reset.email_placeholder')"
      :leading-icon="Mail"
      autocomplete="username"
      inputmode="email"
      dir="ltr"
      required
      :disabled="isSubmitting"
    />
    <BaseButton
      type="submit"
      variant="primary"
      size="lg"
      full-width
      :icon="Send"
      :loading="isSubmitting"
      :disabled="!email.trim()"
      :label="$t('auth.password_reset.send_link')"
    />
  </form>
</template>
