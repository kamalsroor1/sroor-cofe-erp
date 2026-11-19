<script setup>
import { onMounted } from 'vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import LegalDocumentContent from '../../Components/Landing/LegalDocumentContent.vue';
import AccountDeletionRequestForm from '../../Components/Landing/AccountDeletion/AccountDeletionRequestForm.vue';
import { useLegalDocument } from '../../Composables/useLegalDocument';
import { useAccountDeletionRequest } from '../../Composables/useAccountDeletionRequest';

// MOB-05: what is deleted / retained (legal document) + the public deletion request form.
const { blocks, version, isDraft, brand, isLoading, hasError, load } = useLegalDocument();
const { form, errors, formError, isSubmitting, successMessage, submit } = useAccountDeletionRequest();

const refresh = () => load('account-deletion');

onMounted(refresh);
</script>

<template>
  <LandingShell :brand="brand">
    <div class="mx-auto max-w-4xl space-y-8 px-4 py-10 sm:px-6">
      <LegalDocumentContent
        :blocks="blocks"
        :version="version"
        :is-draft="isDraft"
        :is-loading="isLoading"
        :has-error="hasError"
        @retry="refresh"
      />
      <AccountDeletionRequestForm
        v-model:form="form"
        :errors="errors"
        :form-error="formError"
        :success-message="successMessage"
        :is-submitting="isSubmitting"
        @submit="submit"
      />
    </div>
  </LandingShell>
</template>
