<script setup>
import { AlertTriangle } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  errors: { type: Object, default: () => ({}) },
  formError: { type: String, default: '' },
  businessTypeOptions: { type: Array, default: () => [] },
  planOptions: { type: Array, default: () => [] },
  plansFailed: { type: Boolean, default: false },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['submit']);

const form = defineModel('form', { type: Object, required: true });
</script>

<template>
  <form
    class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-7"
    novalidate
    @submit.prevent="$emit('submit')"
  >
    <div
      v-if="formError"
      role="alert"
      class="flex items-center gap-2 rounded-2xl border border-rose-500/20 bg-rose-500/10 p-3.5 text-xs font-bold text-rose-500"
    >
      <AlertTriangle class="h-4 w-4 shrink-0" />
      <span>{{ formError }}</span>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <BaseInput
        v-model="form.business_name"
        :label="$t('signup.form.business_name')"
        :error="errors.business_name"
        autocomplete="organization"
        required
      />
      <BaseInput
        v-model="form.contact_name"
        :label="$t('signup.form.contact_name')"
        :error="errors.contact_name"
        autocomplete="name"
        required
      />
      <BaseInput
        v-model="form.phone"
        type="tel"
        inputmode="tel"
        :label="$t('signup.form.phone')"
        :placeholder="$t('signup.form.phone_placeholder')"
        :error="errors.phone"
        autocomplete="tel"
        input-class="text-start"
        dir="ltr"
        required
      />
      <BaseInput
        v-model="form.email"
        type="email"
        inputmode="email"
        :label="$t('signup.form.email')"
        :error="errors.email"
        autocomplete="email"
        dir="ltr"
      />
      <BaseInput
        v-model="form.city"
        :label="$t('signup.form.city')"
        :error="errors.city"
        autocomplete="address-level2"
      />
      <BaseSelect
        v-model="form.business_type"
        :options="businessTypeOptions"
        :label="$t('signup.form.business_type')"
        :placeholder="$t('signup.form.business_type')"
        :error="errors.business_type"
        :searchable="false"
        required
      />
      <BaseSelect
        v-model="form.plan_code"
        :options="planOptions"
        :label="$t('signup.form.plan')"
        :placeholder="$t('signup.form.plan_none')"
        :hint="plansFailed ? $t('signup.form.plans_failed') : ''"
        :error="errors.plan_code"
        :searchable="false"
      />
      <BaseInput
        v-model="form.preferred_subdomain"
        :label="$t('signup.form.subdomain')"
        :hint="$t('signup.form.subdomain_hint')"
        :error="errors.preferred_subdomain"
        dir="ltr"
      />
    </div>

    <BaseTextarea
      v-model="form.notes"
      :label="$t('signup.form.notes')"
      :error="errors.notes"
      :rows="3"
      :maxlength="500"
    />

    <!-- Honeypot: invisible to people and assistive tech; a bot that fills it is dropped by the server. -->
    <div class="absolute h-0 w-0 overflow-hidden opacity-0" aria-hidden="true">
      <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" />
    </div>

    <BaseButton type="submit" variant="primary" size="lg" full-width :loading="isSubmitting">
      {{ isSubmitting ? $t('signup.form.submitting') : $t('signup.form.submit') }}
    </BaseButton>
  </form>
</template>
