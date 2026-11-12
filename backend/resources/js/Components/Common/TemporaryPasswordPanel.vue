<script setup>
import { Copy, MessageCircle, TriangleAlert } from 'lucide-vue-next';
import BaseButton from './BaseButton.vue';
import { notifySuccess, notifyWarning } from '../../helpers/alert';
import { useTrans } from '../../Composables/useTrans';

/**
 * A server-generated temporary password, shown ONCE. The value only lives in the props of whoever
 * opened it; this panel never stores it. WhatsApp text is built by the parent when asked to.
 */
const props = defineProps({
  password: { type: String, required: true },
  login: { type: String, default: '' },
  canWhatsapp: { type: Boolean, default: false },
});

defineEmits(['whatsapp']);

const { t } = useTrans();

const copy = async () => {
  try {
    await navigator.clipboard.writeText(props.password);
    notifySuccess(t('common.temporary_password.copied'));
  } catch {
    notifyWarning(t('common.temporary_password.copy_failed'));
  }
};
</script>

<template>
  <div class="space-y-4 font-tajawal">
    <slot />

    <dl
      class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50"
    >
      <div v-if="login" class="space-y-1">
        <dt class="text-[11px] font-black text-slate-500 dark:text-slate-400">
          {{ $t('common.temporary_password.login_label') }}
        </dt>
        <dd class="break-all font-mono text-sm font-bold text-slate-900 dark:text-white" dir="ltr">
          {{ login }}
        </dd>
      </div>
      <div class="space-y-1">
        <dt class="text-[11px] font-black text-slate-500 dark:text-slate-400">
          {{ $t('common.temporary_password.label') }}
        </dt>
        <dd
          class="select-all break-all rounded-xl border border-theme-border bg-theme-light px-3 py-2.5 text-center font-mono text-lg font-black tracking-wider text-slate-900 dark:text-white"
          dir="ltr"
          data-testid="temporary-password"
        >
          {{ password }}
        </dd>
      </div>
    </dl>

    <div class="flex flex-wrap gap-2">
      <BaseButton variant="primary" size="md" :icon="Copy" data-testid="copy-temporary-password" @click="copy">
        {{ $t('common.temporary_password.copy') }}
      </BaseButton>
      <BaseButton
        v-if="canWhatsapp"
        variant="secondary"
        size="md"
        :icon="MessageCircle"
        data-testid="whatsapp-temporary-password"
        @click="$emit('whatsapp')"
      >
        {{ $t('common.temporary_password.whatsapp') }}
      </BaseButton>
    </div>

    <p
      role="note"
      class="flex items-start gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs font-bold leading-relaxed text-amber-700 dark:text-amber-300"
    >
      <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
      <span>{{ $t('common.temporary_password.shown_once') }}</span>
    </p>
  </div>
</template>
