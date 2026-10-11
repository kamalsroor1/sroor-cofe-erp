<script setup>
import { ref, watch } from 'vue';
import { ShieldAlert } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import CentralTwoFactorCodeForm from './CentralTwoFactorCodeForm.vue';
import { useCentralAuthStore } from '../../stores/centralAuth';

/**
 * Re-authentication with a fresh 2FA proof (IDEN-1.12 step-up). Opened by centralApi when a
 * request answers 403 central_auth.step_up_required; the request is retried once on success.
 * Mounted once, in SuperAdminLayout.
 */
const centralAuth = useCentralAuthStore();

const isSubmitting = ref(false);
const error = ref('');
const formKey = ref(0);

watch(
  () => centralAuth.stepUpOpen,
  (open) => {
    if (open) {
      error.value = '';
      formKey.value += 1;
    }
  }
);

const submit = async (payload) => {
  isSubmitting.value = true;
  error.value = '';
  try {
    await centralAuth.confirmStepUp(payload);
  } catch (e) {
    error.value = e.userMessage || '';
  } finally {
    isSubmitting.value = false;
  }
};

const cancel = () => {
  if (!isSubmitting.value) centralAuth.cancelStepUp();
};
</script>

<template>
  <AppModal
    :show="centralAuth.stepUpOpen"
    :title="$t('super.central_auth.step_up_title')"
    :subtitle="$t('super.central_auth.step_up_subtitle')"
    :icon="ShieldAlert"
    max-width="sm"
    @close="cancel"
  >
    <CentralTwoFactorCodeForm
      :key="formKey"
      :is-submitting="isSubmitting"
      :error="error"
      :submit-label="$t('super.central_auth.step_up_confirm')"
      @submit="submit"
    />
  </AppModal>
</template>
