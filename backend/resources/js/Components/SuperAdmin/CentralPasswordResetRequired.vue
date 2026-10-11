<script setup>
import { KeyRound, Send, CheckCircle2 } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  email: { type: String, default: '' },
  isSubmitting: { type: Boolean, default: false },
  sentMessage: { type: String, default: '' },
  error: { type: String, default: '' },
});

const emit = defineEmits(['send']);
</script>

<template>
  <div class="space-y-4 text-center">
    <KeyRound class="w-10 h-10 mx-auto text-amber-500" aria-hidden="true" />
    <h2 class="text-base font-black text-slate-900 dark:text-white">
      {{ $t('super.central_auth.reset_required_title') }}
    </h2>
    <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">
      {{ $t('super.central_auth.reset_required_desc') }}
    </p>
    <p v-if="email" dir="ltr" class="font-mono text-xs font-bold text-slate-800 dark:text-slate-100">{{ email }}</p>

    <div
      v-if="sentMessage"
      role="status"
      class="flex items-start gap-2 rounded-2xl border border-emerald-300 bg-emerald-50 p-3 text-start text-xs font-bold text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
    >
      <CheckCircle2 class="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
      <span>{{ sentMessage }}</span>
    </div>
    <template v-else>
      <p v-if="error" role="alert" class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ error }}</p>
      <BaseButton
        variant="primary"
        size="lg"
        full-width
        :icon="Send"
        :loading="isSubmitting"
        :disabled="!email"
        :label="$t('super.central_auth.send_reset_link')"
        @click="emit('send')"
      />
    </template>
  </div>
</template>
