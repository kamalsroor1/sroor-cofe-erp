<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import CentralAuthShell from '../../Components/SuperAdmin/CentralAuthShell.vue';
import CentralForgotPasswordForm from '../../Components/SuperAdmin/CentralForgotPasswordForm.vue';
import { useCentralAuthStore } from '../../stores/centralAuth';
import { useTrans } from '../../Composables/useTrans';

const route = useRoute();
const centralAuth = useCentralAuthStore();
const { t } = useTrans();

const initialEmail = typeof route.query.email === 'string' ? route.query.email : '';
const isSubmitting = ref(false);
const error = ref('');
const sentMessage = ref('');

const submit = async (email) => {
  isSubmitting.value = true;
  error.value = '';
  try {
    sentMessage.value = await centralAuth.forgotPassword(email);
  } catch (e) {
    error.value = e.userMessage || t('common.unexpected_error');
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <CentralAuthShell :title="$t('super.central_auth.forgot_title')" :subtitle="$t('super.central_auth.forgot_subtitle')">
    <CentralForgotPasswordForm
      :initial-email="initialEmail"
      :is-submitting="isSubmitting"
      :sent-message="sentMessage"
      :error="error"
      @submit="submit"
    />
    <template #footer>
      <router-link
        :to="{ name: 'super_admin.login' }"
        class="inline-flex min-h-11 items-center px-3 font-bold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
      >
        {{ $t('super.central_auth.back_to_sign_in') }}
      </router-link>
    </template>
  </CentralAuthShell>
</template>
