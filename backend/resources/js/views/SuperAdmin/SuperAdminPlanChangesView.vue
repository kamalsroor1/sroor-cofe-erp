<script setup>
import { ArrowUpCircle } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import PlanChangeStatusTabs from '../../Components/SuperAdmin/PlanChanges/PlanChangeStatusTabs.vue';
import PlanChangeList from '../../Components/SuperAdmin/PlanChanges/PlanChangeList.vue';
import IssuePlanChangeInvoiceModal from '../../Components/SuperAdmin/PlanChanges/IssuePlanChangeInvoiceModal.vue';
import { usePlanChangeQueue } from '../../Composables/usePlanChangeQueue';
import { usePendingPlanChangeRequests } from '../../Composables/usePendingPlanChangeRequests';

const {
  requests,
  pagination,
  isLoading,
  hasError,
  status,
  canManage,
  plans,
  plansFailed,
  target,
  issued,
  errors,
  isSubmitting,
  fetchRequests,
  setStatus,
  goToPage,
  openIssue,
  closeIssue,
  issue,
  reject,
} = usePlanChangeQueue();
const { pendingCount } = usePendingPlanChangeRequests();
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6 font-tajawal">
    <PageHeader
      :title="$t('billing.console.plan_changes.title')"
      :subtitle="$t('billing.console.plan_changes.subtitle')"
      :icon="ArrowUpCircle"
    />

    <PlanChangeStatusTabs :model-value="status" :pending-count="pendingCount" @update:model-value="setStatus" />

    <PlanChangeList
      :requests="requests"
      :pagination="pagination"
      :is-loading="isLoading"
      :has-error="hasError"
      :can-manage="canManage"
      @retry="fetchRequests"
      @page-change="goToPage"
      @issue="openIssue"
      @reject="reject"
    />

    <IssuePlanChangeInvoiceModal
      :request="target"
      :plans="plans"
      :plans-failed="plansFailed"
      :issued="issued"
      :errors="errors"
      :is-submitting="isSubmitting"
      @close="closeIssue"
      @submit="issue"
    />
  </div>
</template>
