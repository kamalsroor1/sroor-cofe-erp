<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <PageHeader
      :title="$t('billing.console.queue.title')"
      :subtitle="$t('billing.console.queue.subtitle')"
      :icon="Receipt"
    />

    <BillingStatusTabs :model-value="status" :pending-count="pendingCount" @update:model-value="setStatus" />

    <BillingPaymentsList
      :payments="payments"
      :status="status"
      :is-loading="isLoading"
      :load-error="loadError"
      :can-manage="canManage"
      :busy-id="busyId"
      @retry="fetchPayments"
      @receipt="openReceipt"
      @verify="verifyPayment"
      @reject="rejectPayment"
    />

    <BillingPager v-if="!isLoading && !loadError" :page="page" :last-page="lastPage" @change="goToPage" />

    <BillingReceiptModal
      :show="!!receiptPayment"
      :subtitle="receiptPayment?.invoice_number || ''"
      :receipt="receipt"
      @close="closeReceipt"
    />
  </div>
</template>

<script setup>
import { Receipt } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BillingStatusTabs from '../../Components/SuperAdmin/Billing/BillingStatusTabs.vue';
import BillingPaymentsList from '../../Components/SuperAdmin/Billing/BillingPaymentsList.vue';
import BillingPager from '../../Components/SuperAdmin/Billing/BillingPager.vue';
import BillingReceiptModal from '../../Components/SuperAdmin/Billing/BillingReceiptModal.vue';
import { useBillingPayments } from '../../Composables/useBillingPayments';
import { useBillingPendingCount } from '../../Composables/useBillingPendingCount';

const {
  status,
  page,
  lastPage,
  payments,
  isLoading,
  loadError,
  busyId,
  canManage,
  receiptPayment,
  receipt,
  fetchPayments,
  setStatus,
  goToPage,
  verifyPayment,
  rejectPayment,
  openReceipt,
  closeReceipt,
} = useBillingPayments();

const { pendingCount } = useBillingPendingCount();
</script>
