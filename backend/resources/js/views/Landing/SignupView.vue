<script setup>
import { onMounted, ref } from 'vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import Skeleton from '../../Components/Common/Skeletons/Skeleton.vue';
import SignupAside from '../../Components/Signup/SignupAside.vue';
import SignupForm from '../../Components/Signup/SignupForm.vue';
import SignupOtpStep from '../../Components/Signup/SignupOtpStep.vue';
import SignupProvisioningStep from '../../Components/Signup/SignupProvisioningStep.vue';
import { SIGNUP_STEPS, useSelfSignup } from '../../Composables/useSelfSignup';

const {
  step,
  token,
  emailMasked,
  form,
  errors,
  formError,
  isSubmitting,
  codeError,
  refusedMessage,
  isVerifying,
  isResending,
  resendTimer,
  businessTypeOptions,
  planOptions,
  plansFailed,
  plansLoading,
  selectedPlan,
  founder,
  currency,
  brand,
  init,
  submit,
  verify,
  resend,
  startOver,
  clearToken,
  onSubdomainInput,
} = useSelfSignup();
const formRef = ref(null);

onMounted(init);
</script>

<template>
  <LandingShell :brand="brand">
    <div class="bg-mk-bg px-5 pt-6 pb-14 sm:px-6 lg:pt-14 lg:pb-24">
      <div
        v-if="step === SIGNUP_STEPS.form"
        class="mx-auto grid max-w-[1200px] grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-10 xl:grid-cols-[minmax(0,1fr)_440px]"
      >
        <SignupForm
          ref="formRef"
          v-model:form="form"
          :errors="errors"
          :form-error="formError"
          :business-type-options="businessTypeOptions"
          :plan-options="planOptions"
          :plans-loading="plansLoading"
          :plans-failed="plansFailed"
          :is-submitting="isSubmitting"
          @submit="submit"
          @subdomain-input="onSubdomainInput"
        />
        <SignupAside
          class="lg:sticky lg:top-[100px]"
          :plan="selectedPlan"
          :founder="founder"
          :currency="currency"
          :brand="brand"
          @change-plan="formRef?.focusPlan()"
        />
      </div>
      <SignupOtpStep
        v-else-if="step === SIGNUP_STEPS.verify"
        :email-masked="emailMasked"
        :code-error="codeError"
        :is-verifying="isVerifying"
        :is-resending="isResending"
        :resend-label="resendTimer.label"
        :can-resend="!resendTimer.isRunning"
        @verify="verify"
        @resend="resend"
        @start-over="startOver"
      />
      <SignupProvisioningStep
        v-else-if="step === SIGNUP_STEPS.provisioning"
        :token="token"
        :email-masked="emailMasked"
        :refused-message="refusedMessage"
        :brand="brand"
        @finished="clearToken"
        @start-over="startOver"
      />
      <Skeleton v-else class-name="mx-auto max-w-[520px]" height="h-72" rounded="rounded-3xl" />
    </div>
  </LandingShell>
</template>
