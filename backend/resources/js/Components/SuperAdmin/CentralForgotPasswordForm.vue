<script setup>
import { ref } from 'vue';
import { Mail, Send, CheckCircle2 } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  initialEmail: { type: String, default: '' },
  isSubmitting: { type: Boolean, default: false },
  sentMessage: { type: String, default: '' },
  error: { type: String, default: '' },
});

const emit = defineEmits(['submit']);

const email = ref(props.initialEmail);

const submit = () => {
  if (props.isSubmitting || !email.value.trim()) return;
  emit('submit', email.value.trim());
};
</script>

<template>
  <div
    v-if="sentMessage"
    role="status"
    class="flex items-start gap-2 rounded-2xl border border-emerald-300 bg-emerald-50 p-3 text-xs font-bold text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
  >
    <CheckCircle2 class="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
    <span>{{ sentMessage }}</span>
  </div>
  <form v-else class="space-y-4" novalidate @submit.prevent="submit">
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
    <p v-if="error" role="alert" class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ error }}</p>
    <BaseButton
      type="submit"
      variant="primary"
      size="lg"
      full-width
      :icon="Send"
      :loading="isSubmitting"
      :disabled="!email"
      :label="$t('super.central_auth.send_reset_link')"
    />
  </form>
</template>
