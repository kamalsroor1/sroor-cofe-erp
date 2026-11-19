<script setup>
import { AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
import BaseInput from '../../Form/BaseInput.vue';
import BaseTextarea from '../../Form/BaseTextarea.vue';
import MarketingButton from '../MarketingButton.vue';

defineProps({
  errors: { type: Object, default: () => ({}) },
  formError: { type: String, default: '' },
  successMessage: { type: String, default: '' },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['submit']);

const form = defineModel('form', { type: Object, required: true });

const firstError = (value) => (Array.isArray(value) ? value[0] : value);
</script>

<template>
  <section
    id="account-deletion-request"
    data-testid="account-deletion-form"
    class="flex flex-col gap-5 rounded-3xl border border-mk-line bg-mk-surface p-5 mk-shadow-card sm:p-8"
  >
    <header class="flex flex-col gap-1.5">
      <h2 class="text-xl font-bold text-mk-ink sm:text-2xl">{{ $t('account_deletion.form.title') }}</h2>
      <p class="text-sm leading-[1.6] text-mk-muted">{{ $t('account_deletion.form.subtitle') }}</p>
    </header>

    <div
      v-if="successMessage"
      role="status"
      data-testid="account-deletion-success"
      class="flex items-start gap-3 rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4 text-sm font-bold text-emerald-700 dark:text-emerald-300"
    >
      <CheckCircle2 class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
      <div class="flex flex-col gap-1">
        <span>{{ $t('account_deletion.form.success_title') }}</span>
        <span class="font-medium">{{ successMessage }}</span>
      </div>
    </div>

    <form v-else class="flex flex-col gap-5" novalidate @submit.prevent="$emit('submit')">
      <div
        v-if="formError"
        role="alert"
        class="flex items-center gap-2 rounded-2xl border border-rose-500/25 bg-rose-500/10 p-3.5 text-sm font-bold text-rose-600 dark:text-rose-400"
      >
        <AlertTriangle class="size-4 shrink-0" aria-hidden="true" />
        <span>{{ formError }}</span>
      </div>

      <div class="deletion-fields grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">
        <BaseInput
          v-model="form.contact_name"
          :label="$t('account_deletion.form.contact_name')"
          :error="errors.contact_name"
          autocomplete="name"
          maxlength="120"
          required
        />
        <BaseInput
          v-model="form.phone"
          type="tel"
          inputmode="tel"
          dir="ltr"
          :label="$t('account_deletion.form.phone')"
          :hint="$t('account_deletion.form.phone_hint')"
          :error="errors.phone"
          autocomplete="tel"
          required
        />
        <BaseInput
          v-model="form.email"
          type="email"
          inputmode="email"
          dir="ltr"
          :label="$t('account_deletion.form.email')"
          :error="errors.email"
          autocomplete="email"
        />
        <BaseInput
          v-model="form.shop_code"
          dir="ltr"
          :label="$t('account_deletion.form.shop_code')"
          :hint="$t('account_deletion.form.shop_code_hint')"
          :error="errors.shop_code"
          maxlength="63"
        />
        <BaseTextarea
          v-model="form.reason"
          class="sm:col-span-2"
          :label="$t('account_deletion.form.reason')"
          :error="errors.reason"
          :rows="3"
          maxlength="1000"
        />
      </div>

      <!-- Honeypot: off-screen for people, filled by bots. -->
      <div class="absolute -start-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
        <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" />
      </div>

      <div class="flex flex-col gap-1.5">
        <label
          for="account-deletion-confirm"
          class="flex min-h-11 cursor-pointer items-start gap-3 py-1 text-sm leading-[1.6] font-medium text-mk-muted"
        >
          <input
            id="account-deletion-confirm"
            v-model="form.confirm"
            data-testid="account-deletion-confirm"
            type="checkbox"
            required
            :aria-invalid="errors.confirm ? 'true' : 'false'"
            class="mt-0.5 size-[22px] shrink-0 cursor-pointer rounded-md accent-mk-primary scheme-light dark:scheme-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-primary"
          />
          <span>{{ $t('account_deletion.form.confirm') }}</span>
        </label>
        <p v-if="errors.confirm" role="alert" class="text-xs font-bold text-rose-600 dark:text-rose-400">
          {{ firstError(errors.confirm) }}
        </p>
      </div>

      <MarketingButton
        type="submit"
        variant="primary"
        block
        :disabled="isSubmitting"
        data-testid="account-deletion-submit"
      >
        {{ isSubmitting ? $t('account_deletion.form.submitting') : $t('account_deletion.form.submit') }}
      </MarketingButton>
    </form>
  </section>
</template>

<style scoped>
/* Same marketing re-skin of the shared Base* fields as the signup form. */
.deletion-fields :deep(label) {
  font-size: 14px;
  font-weight: 700;
  color: var(--mk-ink);
}
.deletion-fields :deep(:is(input, textarea)) {
  min-height: 50px;
  border-radius: 12px;
  background-color: var(--mk-surface);
  color: var(--mk-ink);
  font-size: 15px;
}
.deletion-fields :deep(:is(input, textarea)[aria-invalid='false']) {
  border-color: var(--mk-line);
}
.deletion-fields :deep(:is(input, textarea)[aria-invalid='false']:focus) {
  border-color: var(--mk-primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--mk-primary) 22%, transparent);
}
.deletion-fields :deep(p[id$='-hint']) {
  font-size: 12px;
  color: var(--mk-faint);
}
</style>
