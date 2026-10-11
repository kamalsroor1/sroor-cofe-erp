<script setup>
import { ArrowRight } from 'lucide-vue-next';
import CentralAuthShell from '../../Components/SuperAdmin/CentralAuthShell.vue';
import CentralLoginForm from '../../Components/SuperAdmin/CentralLoginForm.vue';
import CentralTwoFactorCodeForm from '../../Components/SuperAdmin/CentralTwoFactorCodeForm.vue';
import CentralTwoFactorSetup from '../../Components/SuperAdmin/CentralTwoFactorSetup.vue';
import CentralRecoveryCodes from '../../Components/SuperAdmin/CentralRecoveryCodes.vue';
import CentralPasswordResetRequired from '../../Components/SuperAdmin/CentralPasswordResetRequired.vue';
import { useSuperAdminLogin } from '../../Composables/useSuperAdminLogin';

const login = useSuperAdminLogin();
const { step, email, title, subtitle, notice, isSubmitting, error, sentMessage } = login;
const { setup, isSetupLoading, setupLoadError, recoveryCodes } = login;
</script>

<template>
  <CentralAuthShell :title="title" :subtitle="subtitle" :notice="step === 'credentials' ? notice : ''">
    <CentralLoginForm
      v-if="step === 'credentials'"
      :initial-email="email"
      :is-submitting="isSubmitting"
      :error="error"
      @submit="login.submitCredentials"
    />
    <CentralTwoFactorCodeForm
      v-else-if="step === 'challenge'"
      :is-submitting="isSubmitting"
      :error="error"
      @submit="login.submitChallenge"
      @mode-change="login.setChallengeMode"
    />
    <CentralTwoFactorSetup
      v-else-if="step === 'setup'"
      :setup="setup"
      :is-loading="isSetupLoading"
      :load-error="setupLoadError"
      :is-submitting="isSubmitting"
      :error="error"
      @retry="login.loadSetup"
      @confirm="login.confirmSetup"
    />
    <CentralRecoveryCodes v-else-if="step === 'recovery'" :codes="recoveryCodes" @done="login.finishRecovery" />
    <CentralPasswordResetRequired
      v-else
      :email="email"
      :is-submitting="isSubmitting"
      :sent-message="sentMessage"
      :error="error"
      @send="login.sendResetLink"
    />

    <template #footer>
      <router-link
        v-if="step === 'credentials'"
        :to="{ name: 'super_admin.forgot_password', query: email ? { email } : undefined }"
        class="inline-flex min-h-11 items-center px-3 font-bold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
      >
        {{ $t('super.central_auth.forgot_password_link') }}
      </router-link>
      <button
        v-else-if="step !== 'recovery'"
        type="button"
        class="inline-flex min-h-11 items-center gap-1.5 px-3 font-bold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
        @click="login.restart()"
      >
        <ArrowRight class="w-4 h-4 ltr:rotate-180" aria-hidden="true" />
        {{ $t('super.central_auth.back_to_sign_in') }}
      </button>
    </template>
  </CentralAuthShell>
</template>
