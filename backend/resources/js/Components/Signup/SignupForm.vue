<script setup>
import { nextTick } from 'vue';
import { AlertTriangle, ArrowLeft } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import MarketingButton from '../Landing/MarketingButton.vue';

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

const PLAN_FIELD_ID = 'signup-plan';
const LEGAL_LINK = 'font-bold text-mk-primary underline underline-offset-4';

/** Called from the page aside's "change" link: bring the plan picker into view and focus it. */
const focusPlan = async () => {
  await nextTick();
  const field = document.getElementById(PLAN_FIELD_ID);
  if (!field) return;
  field.scrollIntoView({ block: 'center' });
  field.focus({ preventScroll: true });
};

defineExpose({ focusPlan });
</script>

<template>
  <form
    class="relative flex flex-col gap-5 rounded-3xl border border-mk-line bg-mk-surface p-5 mk-shadow-card sm:p-9"
    novalidate
    @submit.prevent="$emit('submit')"
  >
    <header class="flex flex-col gap-1.5">
      <h1 class="text-2xl font-bold text-mk-ink sm:text-[30px]">{{ $t('signup.form.title') }}</h1>
      <p class="text-base leading-[1.6] text-mk-muted">{{ $t('signup.form.subtitle') }}</p>
    </header>

    <div
      v-if="formError"
      role="alert"
      class="flex items-center gap-2 rounded-2xl border border-rose-500/25 bg-rose-500/10 p-3.5 text-sm font-bold text-rose-600 dark:text-rose-400"
    >
      <AlertTriangle class="size-4 shrink-0" aria-hidden="true" />
      <span>{{ formError }}</span>
    </div>

    <!-- The Base* fields are re-skinned to the marketing tokens by the scoped styles below. -->
    <div class="signup-fields grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">
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
        :placeholder="$t('signup.form.contact_name_placeholder')"
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
        :placeholder="$t('signup.form.email_placeholder')"
        :error="errors.email"
        autocomplete="email"
        dir="ltr"
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
      <BaseInput
        v-model="form.city"
        :label="$t('signup.form.city')"
        :placeholder="$t('signup.form.city_placeholder')"
        :error="errors.city"
        autocomplete="address-level2"
      />
      <BaseSelect
        :id="PLAN_FIELD_ID"
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
        autocapitalize="off"
        spellcheck="false"
        dir="ltr"
      />
      <BaseTextarea
        v-model="form.notes"
        wrapper-class="sm:col-span-2"
        :label="$t('signup.form.notes')"
        :placeholder="$t('signup.form.notes_placeholder')"
        :error="errors.notes"
        :rows="3"
        :maxlength="500"
      />
    </div>

    <!-- PRD-08: Explicit consent to Terms of Service, Privacy Policy and DPA -->
    <div class="flex flex-col gap-1.5">
      <label
        for="signup-terms-accepted"
        class="flex min-h-11 cursor-pointer items-start gap-3 py-1 text-sm leading-[1.6] font-medium text-mk-muted"
      >
        <input
          id="signup-terms-accepted"
          v-model="form.terms_accepted"
          data-testid="signup-terms-checkbox"
          type="checkbox"
          required
          :aria-invalid="errors.terms_accepted ? 'true' : 'false'"
          class="mt-0.5 size-[22px] shrink-0 cursor-pointer rounded-md accent-mk-primary scheme-light dark:scheme-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-primary"
        />
        <span>
          {{ $t('signup.form.terms_prefix') }}
          <RouterLink :to="{ name: 'landing.terms' }" target="_blank" :class="LEGAL_LINK">
            {{ $t('signup.form.terms_link') }}
          </RouterLink>
          {{ $t('signup.form.terms_and') }}
          <RouterLink :to="{ name: 'landing.privacy' }" target="_blank" :class="LEGAL_LINK">
            {{ $t('signup.form.privacy_link') }}
          </RouterLink>
          {{ $t('signup.form.terms_and') }}
          <RouterLink :to="{ name: 'landing.dpa' }" target="_blank" :class="LEGAL_LINK">
            {{ $t('signup.form.dpa_link') }}
          </RouterLink>
        </span>
      </label>
      <p
        v-if="errors.terms_accepted"
        data-testid="signup-terms-error"
        class="text-xs font-bold text-rose-600 dark:text-rose-400"
      >
        {{ Array.isArray(errors.terms_accepted) ? errors.terms_accepted[0] : errors.terms_accepted }}
      </p>
    </div>

    <!-- Honeypot: invisible to people and assistive tech; a bot that fills it is dropped by the server. -->
    <div class="absolute h-0 w-0 overflow-hidden opacity-0" aria-hidden="true">
      <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" />
    </div>

    <MarketingButton
      type="submit"
      size="lg"
      block
      :icon="isSubmitting ? null : ArrowLeft"
      :disabled="isSubmitting"
      :aria-busy="isSubmitting"
      class="disabled:cursor-wait disabled:opacity-70"
    >
      {{ isSubmitting ? $t('signup.form.submitting') : $t('signup.form.submit') }}
    </MarketingButton>
  </form>
</template>

<style scoped>
/*
 * Re-skin the shared Base* fields to the marketing tokens (--mk-* on .marketing) without forking them.
 * Error states keep the fields' own rose colours: only aria-invalid="false" controls are recoloured.
 */
.signup-fields :deep(label) {
  font-size: 14px;
  font-weight: 700;
  color: var(--mk-ink);
}
.signup-fields :deep(:is(input, textarea, button[aria-haspopup='listbox'])) {
  min-height: 50px;
  border-radius: 12px;
  background-color: var(--mk-surface);
  color: var(--mk-ink);
  font-size: 15px;
  font-weight: 500;
}
.signup-fields :deep(textarea) {
  min-height: 96px;
}
.signup-fields :deep(:is(input, textarea)::placeholder) {
  color: var(--mk-faint);
  font-weight: 400;
}
.signup-fields :deep(:is(input, textarea, button)[aria-invalid='false']) {
  border-color: var(--mk-line);
}
.signup-fields :deep(:is(input, textarea, button)[aria-invalid='false']:focus) {
  border-color: var(--mk-primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--mk-primary) 22%, transparent);
}
.signup-fields :deep(p[id$='-hint']) {
  font-size: 12px;
  color: var(--mk-faint);
}
</style>
