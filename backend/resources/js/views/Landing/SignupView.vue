<script setup>
import { onMounted } from 'vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import SupportFooter from '../../Components/Landing/SupportFooter.vue';
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
  brand,
  loadPlans,
  submit,
} = useSignupRequest();

onMounted(loadPlans);
</script>

<template>
  <LandingShell :brand-name="brand.name || ''">
    <div class="mx-auto max-w-2xl space-y-6 px-4 py-10 sm:px-6">
      <SignupSuccess v-if="successMessage" :message="successMessage" />
      <template v-else>
        <header class="space-y-2 text-center">
          <h1 class="text-2xl font-black text-slate-900 dark:text-white">{{ $t('signup.form.title') }}</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('signup.form.subtitle') }}</p>
        </header>
        <SignupForm
          v-model:form="form"
          :errors="errors"
          :form-error="formError"
          :business-type-options="businessTypeOptions"
          :plan-options="planOptions"
          :plans-failed="plansFailed"
          :is-submitting="isSubmitting"
          @submit="submit"
        />
      </template>
    </div>
    <template #footer><SupportFooter :brand="brand" /></template>
  </LandingShell>
</template>
