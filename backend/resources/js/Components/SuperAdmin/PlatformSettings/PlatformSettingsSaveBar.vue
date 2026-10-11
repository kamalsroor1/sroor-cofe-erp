<script setup>
import { CircleCheck, CircleDot, RotateCcw, Save } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';

defineProps({
  dirty: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
});

defineEmits(['discard']);
</script>

<template>
  <div
    class="sticky bottom-0 z-20 px-4 py-3 rounded-2xl border bg-white/95 dark:bg-slate-900/95 backdrop-blur-md shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-colors"
    :class="dirty ? 'border-amber-300 dark:border-amber-500/40' : 'border-slate-200 dark:border-slate-800'"
    data-testid="platform-settings-save-bar"
  >
    <p
      class="flex items-center gap-2 text-xs font-bold"
      :class="dirty ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-400'"
      aria-live="polite"
    >
      <CircleDot v-if="dirty" class="w-4 h-4 shrink-0" />
      <CircleCheck v-else class="w-4 h-4 shrink-0" />
      <span v-if="dirty">{{ $t('super.platform_settings.unsaved_changes') }}</span>
      <span v-else>{{ $t('super.platform_settings.all_saved') }}</span>
    </p>

    <div class="flex items-center gap-2">
      <BaseButton
        variant="secondary"
        :icon="RotateCcw"
        :disabled="!dirty || saving"
        class="flex-1 sm:flex-none"
        @click="$emit('discard')"
      >
        {{ $t('super.platform_settings.discard') }}
      </BaseButton>
      <BaseButton
        type="submit"
        variant="primary"
        :icon="Save"
        :loading="saving"
        :disabled="!dirty"
        class="flex-1 sm:flex-none"
        data-testid="platform-settings-save"
      >
        {{ saving ? $t('common.saving') : $t('common.save') }}
      </BaseButton>
    </div>
  </div>
</template>
