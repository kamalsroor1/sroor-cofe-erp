<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
import CentralAuthShell from '../../Components/SuperAdmin/CentralAuthShell.vue';
import CentralResetPasswordForm from '../../Components/SuperAdmin/CentralResetPasswordForm.vue';
import { useCentralAuthStore } from '../../stores/centralAuth';
import { useTrans } from '../../Composables/useTrans';

// Mirrors ResetCentralPasswordRequest::MIN_PASSWORD_LENGTH (the server still validates).
const MIN_PASSWORD_LENGTH = 12;

const route = useRoute();
const centralAuth = useCentralAuthStore();
const { t } = useTrans();

const token = typeof route.query.token === 'string' ? route.query.token : '';
const email = typeof route.query.email === 'string' ? route.query.email : '';
const hasLink = computed(() => token !== '' && email !== '');

const isSubmitting = ref(false);
const error = ref('');
const doneMessage = ref('');

// Keep the single-use token out of the address bar / history once it has been read. Not a router
// navigation on purpose: App.vue keys the page by fullPath, so router.replace would remount it.
onMounted(() => {
  if (hasLink.value) window.history.replaceState(window.history.state, '', route.path);
});

const submit = async ({ password, passwordConfirmation }) => {
  isSubmitting.value = true;
  error.value = '';
  try {
    doneMessage.value = await centralAuth.resetPassword({ token, email, password, passwordConfirmation });
  } catch (e) {
    error.value = e.userMessage || t('common.unexpected_error');
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <CentralAuthShell
    :title="$t('super.central_auth.reset_title')"
    :subtitle="$t('super.central_auth.reset_subtitle', { min: MIN_PASSWORD_LENGTH })"
  >
    <div v-if="!hasLink" role="alert" class="space-y-3 text-center">
      <AlertTriangle class="w-10 h-10 mx-auto text-rose-500" aria-hidden="true" />
      <p class="text-sm font-bold text-slate-700 dark:text-slate-200">
        {{ $t('super.central_auth.reset_link_invalid') }}
      </p>
      <router-link
        :to="{ name: 'super_admin.forgot_password' }"
        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-theme-primary px-4 text-xs font-black text-slate-950"
      >
        {{ $t('super.central_auth.request_new_link') }}
      </router-link>
    </div>
    <div v-else-if="doneMessage" role="status" class="space-y-3 text-center">
      <CheckCircle2 class="w-10 h-10 mx-auto text-emerald-500" aria-hidden="true" />
      <p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ doneMessage }}</p>
    </div>
    <CentralResetPasswordForm
      v-else
      :email="email"
      :min-length="MIN_PASSWORD_LENGTH"
      :is-submitting="isSubmitting"
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
