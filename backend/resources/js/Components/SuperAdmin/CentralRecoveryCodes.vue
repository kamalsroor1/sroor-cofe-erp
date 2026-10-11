<script setup>
import { ref } from 'vue';
import { Copy, Check, Download, ArrowLeft } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import BaseCheckbox from '../Form/BaseCheckbox.vue';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  codes: { type: Array, required: true },
});

const emit = defineEmits(['done']);

const { t } = useTrans();
const acknowledged = ref(false);
const copied = ref(false);

const asText = () => [t('super.central_auth.recovery_file_header'), '', ...props.codes].join('\n');

const copyAll = async () => {
  try {
    await navigator.clipboard.writeText(props.codes.join('\n'));
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
  } catch {
    copied.value = false;
  }
};

const download = () => {
  const url = URL.createObjectURL(new Blob([asText()], { type: 'text/plain;charset=utf-8' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = `${t('super.central_auth.recovery_filename')}.txt`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
};
</script>

<template>
  <div class="space-y-4">
    <ul
      dir="ltr"
      class="grid grid-cols-2 gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800"
      data-testid="recovery-codes"
    >
      <li
        v-for="code in codes"
        :key="code"
        class="rounded-lg bg-white px-2 py-1.5 text-center font-mono text-xs font-bold text-slate-900 dark:bg-slate-900 dark:text-slate-100"
      >
        {{ code }}
      </li>
    </ul>

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
      <BaseButton
        variant="secondary"
        :icon="copied ? Check : Copy"
        :label="copied ? $t('super.central_auth.copied') : $t('super.central_auth.recovery_copy_all')"
        full-width
        @click="copyAll"
      />
      <BaseButton
        variant="secondary"
        :icon="Download"
        :label="$t('super.central_auth.recovery_download')"
        full-width
        @click="download"
      />
    </div>

    <BaseCheckbox v-model="acknowledged" :label="$t('super.central_auth.recovery_ack')" />

    <BaseButton
      variant="primary"
      size="lg"
      full-width
      :trailing-icon="ArrowLeft"
      trailing-icon-class="ltr:rotate-180"
      :disabled="!acknowledged"
      :label="$t('super.central_auth.continue_to_console')"
      @click="emit('done')"
    />
  </div>
</template>
