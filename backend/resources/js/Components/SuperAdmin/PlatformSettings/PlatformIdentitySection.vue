<script setup>
import { BadgeCheck } from 'lucide-vue-next';
import DashboardSectionCard from '../../Common/DashboardSectionCard.vue';
import BaseInput from '../../Form/BaseInput.vue';

defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  readonly: { type: Boolean, default: false },
});

defineEmits(['update:field']);
</script>

<template>
  <DashboardSectionCard
    :title="$t('super.platform_settings.sections.identity_title')"
    :subtitle="$t('super.platform_settings.sections.identity_subtitle')"
    :icon="BadgeCheck"
    icon-bg="bg-theme-light"
    icon-color="text-theme-primary"
    wrapper-class="shadow-sm dark:shadow-xl"
  >
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <BaseInput
        :model-value="form.name"
        :label="$t('super.platform_settings.fields.name')"
        :placeholder="$t('super.platform_settings.fields.name_placeholder')"
        :error="errors.name"
        :readonly="readonly"
        maxlength="100"
        required
        data-testid="platform-name"
        @update:model-value="$emit('update:field', 'name', $event)"
      />
      <BaseInput
        :model-value="form.short_name"
        :label="$t('super.platform_settings.fields.short_name')"
        :hint="$t('super.platform_settings.fields.short_name_hint')"
        :error="errors.short_name"
        :readonly="readonly"
        maxlength="30"
        required
        data-testid="platform-short-name"
        @update:model-value="$emit('update:field', 'short_name', $event)"
      />
      <BaseInput
        :model-value="form.subtitle"
        :label="$t('super.platform_settings.fields.subtitle')"
        :placeholder="$t('super.platform_settings.fields.subtitle_placeholder')"
        :hint="$t('super.platform_settings.default_hint')"
        :error="errors.subtitle"
        :readonly="readonly"
        maxlength="255"
        @update:model-value="$emit('update:field', 'subtitle', $event)"
      />
      <BaseInput
        :model-value="form.legal_name"
        :label="$t('super.platform_settings.fields.legal_name')"
        :placeholder="$t('super.platform_settings.fields.legal_name_placeholder')"
        :hint="$t('super.platform_settings.default_hint')"
        :error="errors.legal_name"
        :readonly="readonly"
        maxlength="150"
        @update:model-value="$emit('update:field', 'legal_name', $event)"
      />
    </div>
  </DashboardSectionCard>
</template>
