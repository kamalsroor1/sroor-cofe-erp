<script setup>
// SIGN-15: shop reset page, opened from the emailed link (public, guest only).
import { AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
import TenantAuthShell from '../../Components/Auth/TenantAuthShell.vue';
import ResetPasswordForm from '../../Components/Auth/ResetPasswordForm.vue';
import { useResetPassword } from '../../Composables/Auth/usePasswordReset';

const { email, hasLink, form, errors, formError, doneMessage, isSubmitting, minLength, submit } = useResetPassword();
</script>

<template>
  <TenantAuthShell
    :title="$t('auth.password_reset.reset_title')"
    :subtitle="$t('auth.password_reset.reset_subtitle')"
    data-testid="reset-password-screen"
  >
    <div v-if="!hasLink" role="alert" class="space-y-3 text-center">
      <AlertTriangle class="mx-auto h-10 w-10 text-rose-500" aria-hidden="true" />
      <p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $t('auth.password_reset.link_missing') }}</p>
      <router-link
        :to="{ name: 'auth.forgot_password' }"
        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-theme-primary px-4 text-xs font-black text-slate-950"
      >
        {{ $t('auth.password_reset.request_new_link') }}
      </router-link>
    </div>
    <div v-else-if="doneMessage" role="status" class="space-y-3 text-center" data-testid="reset-password-done">
      <CheckCircle2 class="mx-auto h-10 w-10 text-emerald-500" aria-hidden="true" />
      <p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ doneMessage }}</p>
      <router-link
        :to="{ name: 'login' }"
        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-theme-primary px-4 text-xs font-black text-slate-950"
      >
        {{ $t('auth.password_reset.go_to_login') }}
      </router-link>
    </div>
    <ResetPasswordForm
      v-else
      v-model:form="form"
      :email="email"
      :min-length="minLength"
      :errors="errors"
      :form-error="formError"
      :is-submitting="isSubmitting"
      @submit="submit"
    />
    <template #footer>
      <router-link
        :to="{ name: 'login' }"
        class="inline-flex min-h-11 items-center px-3 font-bold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
      >
        {{ $t('auth.password_reset.back_to_login') }}
      </router-link>
    </template>
  </TenantAuthShell>
</template>
