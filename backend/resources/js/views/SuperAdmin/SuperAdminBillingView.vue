<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <PageHeader
      :title="$t('billing.console.queue.title')"
      :subtitle="$t('billing.console.queue.subtitle')"
      :icon="Receipt"
    />

    <BillingStatusTabs :model-value="status" :pending-count="pendingCount" @update:model-value="setStatus" />

    <BillingReviewWorkspace
      :payments="payments"
      :selected="selected"
      :selected-id="selectedId"
      :total="total"
      :is-loading="isLoading"
      :load-error="loadError"
      :can-manage="canManage"
      @select="select"
      @back="select(null)"
      @retry="fetchPayments"
      @decided="afterDecision"
    />

    <BillingPager v-if="!isLoading && !loadError" :page="page" :last-page="lastPage" @change="goToPage" />
  </div>
</template>

<script setup>
import { Receipt } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BillingStatusTabs from '../../Components/SuperAdmin/Billing/BillingStatusTabs.vue';
import BillingReviewWorkspace from '../../Components/SuperAdmin/Billing/BillingReviewWorkspace.vue';
import BillingPager from '../../Components/SuperAdmin/Billing/BillingPager.vue';
import { useBillingPayments } from '../../Composables/useBillingPayments';

const {
  status,
  page,
  lastPage,
  total,
  payments,
  isLoading,
  loadError,
  selectedId,
  selected,
  canManage,
  pendingCount,
  fetchPayments,
  setStatus,
  goToPage,
  select,
  afterDecision,
} = useBillingPayments();
</script>
