<script setup>
// SIGN-15: shop "forgot password" page (public, guest only).
import TenantAuthShell from '../../Components/Auth/TenantAuthShell.vue';
import ForgotPasswordForm from '../../Components/Auth/ForgotPasswordForm.vue';
import { useForgotPassword } from '../../Composables/Auth/usePasswordReset';

const { email, isSubmitting, sentMessage, error, submit } = useForgotPassword();
</script>

<template>
  <TenantAuthShell
    :title="$t('auth.password_reset.forgot_title')"
    :subtitle="$t('auth.password_reset.forgot_subtitle')"
    data-testid="forgot-password-screen"
  >
    <ForgotPasswordForm
      v-model:email="email"
      :is-submitting="isSubmitting"
      :sent-message="sentMessage"
      :error="error"
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
