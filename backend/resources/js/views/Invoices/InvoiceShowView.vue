<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal transition-colors duration-300">
    <InvoiceShowSkeleton v-if="isLoading" />

    <div
      v-else-if="error"
      class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800"
    >
      <ErrorState :message="error" :status-code="errorStatusCode" :can-retry="canRetry" @retry="retry" />
      <div class="flex justify-center pb-8">
        <BaseButton variant="secondary" size="md" @click="goBack">{{ $t('common.back') }}</BaseButton>
      </div>
    </div>

    <div v-else class="space-y-6">
      <InvoiceShowHeader
        :invoice="invoice"
        :active-mode="activeMode"
        @set-mode="setMode"
        :invoice-time="invoiceTime"
        :is-cancelled="isCancelled"
      />
      <InvoiceShowActionsBar
        :is-cancelled="isCancelled"
        @print-thermal="triggerPrint('thermal')"
        @print-a4="triggerPrint('a4')"
        @whatsapp="openWhatsApp"
        @copy="copyInvoiceDetails"
        @open-cancel="showCancelModal = true"
        @back="goBack"
      />
      <InvoiceShowInteractiveView
        v-if="activeMode === 'interactive'"
        :invoice="invoice"
        :customer-info="customerInfo"
        :invoice-items="invoiceItems"
        :invoice-payments="invoicePayments"
      />
      <InvoiceShowThermalReceipt
        v-else-if="activeMode === 'thermal'"
        :invoice="invoice"
        :items="invoiceItems"
        :company-info="companyInfo"
        :customer-info="customerInfo"
        :invoice-time="invoiceTime"
      />
      <InvoiceShowA4Document
        v-else-if="activeMode === 'a4'"
        :invoice="invoice"
        :items="invoiceItems"
        :company-info="companyInfo"
        :customer-info="customerInfo"
      />
      <CancelInvoiceModal
        :show="showCancelModal"
        :invoice="invoice"
        v-model:reason="cancelReason"
        :loading="isCancelling"
        @close="showCancelModal = false"
        @confirm="confirmCancelInvoice"
      />
    </div>
  </div>
</template>

<script setup>
import { useInvoiceShow } from '../../Composables/useInvoiceShow';
import ErrorState from '../../Components/Common/ErrorState.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import InvoiceShowSkeleton from '../../Components/Invoices/Show/InvoiceShowSkeleton.vue';
import InvoiceShowHeader from '../../Components/Invoices/Show/InvoiceShowHeader.vue';
import InvoiceShowActionsBar from '../../Components/Invoices/Show/InvoiceShowActionsBar.vue';
import InvoiceShowInteractiveView from '../../Components/Invoices/Show/InvoiceShowInteractiveView.vue';
import InvoiceShowThermalReceipt from '../../Components/Invoices/Show/InvoiceShowThermalReceipt.vue';
import InvoiceShowA4Document from '../../Components/Invoices/Show/InvoiceShowA4Document.vue';
import CancelInvoiceModal from '../../Components/Invoices/Show/CancelInvoiceModal.vue';

const {
  invoice,
  isLoading,
  error,
  errorStatusCode,
  canRetry,
  retry,
  activeMode,
  setMode,
  showCancelModal,
  cancelReason,
  isCancelling,
  companyInfo,
  customerInfo,
  invoiceItems,
  invoicePayments,
  invoiceTime,
  isCancelled,
  triggerPrint,
  copyInvoiceDetails,
  openWhatsApp,
  confirmCancelInvoice,
  goBack,
} = useInvoiceShow();
</script>
