<script setup>
import PageHeader from '../../Components/Common/PageHeader.vue';
import AccountDeletionRequestsToolbar from '../../Components/SuperAdmin/AccountDeletion/AccountDeletionRequestsToolbar.vue';
import AccountDeletionRequestsList from '../../Components/SuperAdmin/AccountDeletion/AccountDeletionRequestsList.vue';
import { useAccountDeletionRequests } from '../../Composables/useAccountDeletionRequests';

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
  markHandled,
} = useAccountDeletionRequests();
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6 font-tajawal">
    <PageHeader
      :title="$t('account_deletion.console.page_title')"
      :subtitle="$t('account_deletion.console.page_subtitle')"
    />

    <AccountDeletionRequestsToolbar
      :status="status"
      :search="search"
      @update:status="setStatus"
      @update:search="setSearch"
    />

    <AccountDeletionRequestsList
      :requests="requests"
      :pagination="pagination"
      :is-loading="isLoading"
      :has-error="hasError"
      :can-manage="canManage"
      @retry="fetchRequests"
      @page-change="goToPage"
      @handle="markHandled"
    />
  </div>
</template>
