<script setup>
import { onMounted, ref } from 'vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import SignupAside from '../../Components/Signup/SignupAside.vue';
import SignupForm from '../../Components/Signup/SignupForm.vue';
import SignupSuccess from '../../Components/Signup/SignupSuccess.vue';
import { useSignupRequest } from '../../Composables/useSignupRequest';

const {
  form,
  errors,
  isSubmitting,
  formError,
  successMessage,
  businessTypeOptions,
  planOptions,
  plansFailed,
  selectedPlan,
  founder,
  currency,
  brand,
  loadPlans,
  submit,
} = useSignupRequest();
const formRef = ref(null);

onMounted(loadPlans);
</script>

<template>
  <LandingShell :brand="brand">
    <div class="bg-mk-bg px-5 pt-6 pb-14 sm:px-6 lg:pt-14 lg:pb-24">
      <div v-if="successMessage" class="py-10">
        <SignupSuccess :message="successMessage" />
      </div>
      <div
        v-else
        class="mx-auto grid max-w-[1200px] grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-10 xl:grid-cols-[minmax(0,1fr)_440px]"
      >
        <SignupForm
          ref="formRef"
          v-model:form="form"
          :errors="errors"
          :form-error="formError"
          :business-type-options="businessTypeOptions"
          :plan-options="planOptions"
          :plans-failed="plansFailed"
          :is-submitting="isSubmitting"
          @submit="submit"
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
    </div>
  </LandingShell>
</template>
