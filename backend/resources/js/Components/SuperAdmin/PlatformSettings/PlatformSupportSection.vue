<script setup>
import { LifeBuoy, Mail, Phone, Globe } from 'lucide-vue-next';
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
    :title="$t('super.platform_settings.sections.support_title')"
    :subtitle="$t('super.platform_settings.sections.support_subtitle')"
    :icon="LifeBuoy"
    icon-bg="bg-sky-100 dark:bg-sky-500/15"
    icon-color="text-sky-600 dark:text-sky-400"
    wrapper-class="shadow-sm dark:shadow-xl"
  >
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <BaseInput
        :model-value="form.support_email"
        type="email"
        dir="ltr"
        autocomplete="email"
        :leading-icon="Mail"
        :label="$t('super.platform_settings.fields.support_email')"
        :placeholder="$t('super.platform_settings.fields.support_email_placeholder')"
        :hint="$t('super.platform_settings.default_hint')"
        :error="errors.support_email"
        :readonly="readonly"
        maxlength="100"
        @update:model-value="$emit('update:field', 'support_email', $event)"
      />
      <BaseInput
        :model-value="form.support_phone"
        type="tel"
        dir="ltr"
        inputmode="tel"
        :leading-icon="Phone"
        :label="$t('super.platform_settings.fields.support_phone')"
        :placeholder="$t('super.platform_settings.fields.support_phone_placeholder')"
        :hint="$t('super.platform_settings.default_hint')"
        :error="errors.support_phone"
        :readonly="readonly"
        maxlength="30"
        @update:model-value="$emit('update:field', 'support_phone', $event)"
      />
      <BaseInput
        :model-value="form.website_url"
        type="url"
        dir="ltr"
        inputmode="url"
        :leading-icon="Globe"
        wrapper-class="sm:col-span-2"
        :label="$t('super.platform_settings.fields.website_url')"
        :placeholder="$t('super.platform_settings.fields.website_url_placeholder')"
        :hint="$t('super.platform_settings.default_hint')"
        :error="errors.website_url"
        :readonly="readonly"
        maxlength="255"
        @update:model-value="$emit('update:field', 'website_url', $event)"
      />
    </div>
  </DashboardSectionCard>
</template>
