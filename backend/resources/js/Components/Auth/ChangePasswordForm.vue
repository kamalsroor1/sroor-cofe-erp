<script setup>
import { AlertTriangle, LogOut, ShieldCheck } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import BasePasswordInput from '../Form/BasePasswordInput.vue';

defineProps({
  errors: { type: Object, required: true },
  formError: { type: String, default: '' },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['submit', 'logout']);

const form = defineModel('form', { type: Object, required: true });
</script>

<template>
  <form class="space-y-4" novalidate data-testid="change-password-form" @submit.prevent="$emit('submit')">
    <div
      v-if="formError"
      role="alert"
      data-testid="change-password-error"
      class="flex items-start gap-2 rounded-2xl border border-rose-500/20 bg-rose-500/10 p-3.5 text-xs font-bold text-rose-600 dark:text-rose-400"
    >
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
      <span>{{ formError }}</span>
    </div>

    <BasePasswordInput
      v-model="form.current_password"
      :label="$t('auth.change_password.current_label')"
      :error="errors.current_password"
      autocomplete="current-password"
      name="current_password"
      data-testid="current-password"
      required
      :disabled="isSubmitting"
    />

    <BasePasswordInput
      v-model="form.password"
      :label="$t('auth.change_password.new_label')"
      :hint="$t('auth.change_password.policy_hint')"
      :error="errors.password"
      autocomplete="new-password"
      name="password"
      data-testid="new-password"
      required
      :disabled="isSubmitting"
    />

    <BasePasswordInput
      v-model="form.password_confirmation"
      :label="$t('auth.change_password.confirm_label')"
      :error="errors.password_confirmation"
      autocomplete="new-password"
      name="password_confirmation"
      data-testid="confirm-password"
      required
      :disabled="isSubmitting"
    />

    <BaseButton
      type="submit"
      variant="primary"
      size="lg"
      full-width
      :icon="ShieldCheck"
      :loading="isSubmitting"
      :disabled="isSubmitting"
      data-testid="change-password-submit"
    >
      {{ $t('auth.change_password.submit') }}
    </BaseButton>

    <button
      type="button"
      class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl text-xs font-bold text-slate-500 transition hover:text-slate-800 disabled:opacity-50 dark:text-slate-400 dark:hover:text-slate-200"
      :disabled="isSubmitting"
      data-testid="change-password-logout"
      @click="$emit('logout')"
    >
      <LogOut class="h-4 w-4 rtl:rotate-180" />
      <span>{{ $t('auth.change_password.logout') }}</span>
    </button>
  </form>
</template>
