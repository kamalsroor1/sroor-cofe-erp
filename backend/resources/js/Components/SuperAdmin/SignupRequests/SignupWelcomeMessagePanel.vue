<script setup>
import { Copy, MessageCircle } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import { notifySuccess, notifyWarning } from '../../../helpers/alert';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  message: { type: String, required: true },
  phone: { type: String, default: '' },
  canWhatsapp: { type: Boolean, default: false },
});

defineEmits(['whatsapp']);

const { t } = useTrans();

const copyMessage = async () => {
  try {
    await navigator.clipboard.writeText(props.message);
    notifySuccess(t('signup.console.welcome_copied'));
  } catch {
    notifyWarning(t('signup.console.action_failed'));
  }
};
</script>

<template>
  <div
    class="space-y-3 border-t border-slate-200 pt-4 text-start dark:border-slate-800"
    data-testid="signup-welcome-message"
  >
    <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('signup.console.welcome_title') }}</h4>

    <pre
      class="max-h-56 overflow-y-auto whitespace-pre-wrap break-words rounded-2xl border border-slate-200 bg-slate-50 p-3.5 text-xs font-medium leading-relaxed text-slate-800 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-100"
      data-testid="welcome-message-text"
      >{{ message }}</pre>

    <div class="flex flex-wrap gap-2">
      <BaseButton variant="primary" size="md" :icon="Copy" data-testid="copy-welcome-message" @click="copyMessage">
        {{ $t('signup.console.welcome_copy') }}
      </BaseButton>
      <BaseButton
        v-if="canWhatsapp && phone"
        variant="secondary"
        size="md"
        :icon="MessageCircle"
        data-testid="whatsapp-welcome-message"
        @click="$emit('whatsapp')"
      >
        {{ $t('signup.console.welcome_whatsapp') }}
      </BaseButton>
    </div>
  </div>
</template>
