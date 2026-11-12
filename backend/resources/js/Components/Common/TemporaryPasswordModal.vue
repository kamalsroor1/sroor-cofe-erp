<script setup>
import { KeyRound } from 'lucide-vue-next';
import AppModal from './AppModal.vue';
import BaseButton from './BaseButton.vue';
import TemporaryPasswordPanel from './TemporaryPasswordPanel.vue';

/**
 * One-time reveal of a temporary password. Only the explicit button closes it (no backdrop / Esc
 * dismissal), so the value cannot be lost by a stray tap. The parent clears the password on `close`.
 */
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  password: { type: String, default: '' },
  login: { type: String, default: '' },
  canWhatsapp: { type: Boolean, default: false },
});

defineEmits(['close', 'whatsapp']);
</script>

<template>
  <AppModal :show="show" :title="title" :subtitle="subtitle" :icon="KeyRound" :closeable="false">
    <TemporaryPasswordPanel
      v-if="password"
      :password="password"
      :login="login"
      :can-whatsapp="canWhatsapp"
      @whatsapp="$emit('whatsapp')"
    >
      <slot />
    </TemporaryPasswordPanel>
    <div class="mt-4 flex justify-end border-t border-slate-200 pt-3 dark:border-slate-800">
      <BaseButton variant="secondary" size="md" data-testid="temporary-password-done" @click="$emit('close')">
        {{ $t('common.temporary_password.done') }}
      </BaseButton>
    </div>
  </AppModal>
</template>
