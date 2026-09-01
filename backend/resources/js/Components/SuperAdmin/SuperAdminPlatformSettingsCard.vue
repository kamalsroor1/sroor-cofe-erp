<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 sm:p-6 shadow-sm dark:shadow-lg space-y-4 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-theme-light text-theme-primary flex items-center justify-center">
          <Sliders class="w-4 h-4" />
        </div>
        <div>
          <h2 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('super.platform_settings_title') }}</h2>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">{{ $t('super.platform_settings_subtitle') }}</p>
        </div>
      </div>
    </div>

    <!-- Settings Form -->
    <form @submit.prevent="$emit('save-settings')" class="space-y-4 pt-2">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Platform Name -->
        <div class="space-y-1.5">
          <BaseInput
            :model-value="platformSettings.platform_name"
            @update:model-value="$emit('update:field', 'platform_name', $event)"
            :label="$t('super.platform_name_label')"
            :placeholder="$t('super.platform_name_placeholder')"
            required
          />
        </div>

        <!-- Platform Subtitle -->
        <div class="space-y-1.5">
          <BaseInput
            :model-value="platformSettings.platform_subtitle"
            @update:model-value="$emit('update:field', 'platform_subtitle', $event)"
            :label="$t('super.platform_subtitle_label')"
            :placeholder="$t('super.platform_subtitle_placeholder')"
          />
        </div>

        <!-- Support Email -->
        <div class="space-y-1.5">
          <BaseInput
            :model-value="platformSettings.support_email"
            @update:model-value="$emit('update:field', 'support_email', $event)"
            :label="$t('super.support_email_label')"
            type="email"
            placeholder="support@domain.com"
            class="font-mono"
          />
        </div>

        <!-- Support Phone -->
        <div class="space-y-1.5">
          <BaseInput
            :model-value="platformSettings.support_phone"
            @update:model-value="$emit('update:field', 'support_phone', $event)"
            :label="$t('super.support_phone_label')"
            placeholder="010XXXXXXXX"
            class="font-mono"
          />
        </div>
      </div>

      <!-- Submit Button -->
      <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-800/80">
        <span v-if="saveSuccessMessage" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
          <span>✓</span> {{ saveSuccessMessage }}
        </span>
        <span v-else></span>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="isSavingSettings"
          class="shadow-lg shadow-theme-primary font-black"
        >
          {{ isSavingSettings ? $t('super.saving_platform_settings') : $t('super.save_platform_settings_btn') }}
        </BaseButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import { Sliders } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  platformSettings: { type: Object, default: () => ({}) },
  isSavingSettings: { type: Boolean, default: false },
  saveSuccessMessage: { type: String, default: '' },
});

defineEmits(['save-settings', 'update:field']);
</script>
