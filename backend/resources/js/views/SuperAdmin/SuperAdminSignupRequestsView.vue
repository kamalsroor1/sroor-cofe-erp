<script setup>
import PageHeader from '../../Components/Common/PageHeader.vue';
import SignupRequestsToolbar from '../../Components/SuperAdmin/SignupRequests/SignupRequestsToolbar.vue';
import SignupRequestsList from '../../Components/SuperAdmin/SignupRequests/SignupRequestsList.vue';
import ApproveSignupModal from '../../Components/SuperAdmin/SignupRequests/ApproveSignupModal.vue';
import { useSignupRequests } from '../../Composables/useSignupRequests';

const {
  requests,
  pagination,
  isLoading,
  hasError,
  status,
  search,
  canManage,
  fetchRequests,
  setStatus,
  setSearch,
  goToPage,
  reject,
  approval,
} = useSignupRequests();
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6 font-tajawal">
    <PageHeader :title="$t('signup.console.page_title')" :subtitle="$t('signup.console.page_subtitle')" />

    <SignupRequestsToolbar :status="status" :search="search" @update:status="setStatus" @update:search="setSearch" />

    <SignupRequestsList
      :requests="requests"
      :pagination="pagination"
      :is-loading="isLoading"
      :has-error="hasError"
      :can-manage="canManage"
      @retry="fetchRequests"
      @page-change="goToPage"
      @approve="approval.open"
      @reject="reject"
    />

    <ApproveSignupModal
      v-model:form="approval.form"
      :show="!!approval.request"
      :business-name="approval.request?.business_name || ''"
      :plans="approval.plans"
      :errors="approval.errors"
      :is-submitting="approval.isSubmitting"
      :is-preparing="approval.isPreparing"
      :credentials="approval.credentials"
      :can-whatsapp="approval.canSendWhatsApp"
      @close="approval.close"
      @submit="approval.submit"
      @regenerate="approval.regenerate"
      @copy="approval.copyPassword"
      @whatsapp="approval.sendWhatsApp"
    />
  </div>
</template>
