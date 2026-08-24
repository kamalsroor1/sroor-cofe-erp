<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <div v-if="isLoading" class="p-16 text-center">
      <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <p class="text-xs text-slate-400 font-bold">{{ $t('invoices.preparing_receipt') }}</p>
    </div>
    <div v-else-if="error" class="p-16 text-center text-rose-500 font-bold space-y-3">
      <p class="text-sm">{{ error }}</p>
      <BaseButton variant="secondary" size="md" @click="goBack">{{ $t('common.back') }}</BaseButton>
    </div>
    <div v-else class="space-y-6">
      <InvoiceShowHeader :invoice="invoice" :active-mode="activeMode" @set-mode="setMode" :invoice-time="invoiceTime" :is-cancelled="isCancelled" />
      <InvoiceShowActionsBar :is-cancelled="isCancelled" @print-thermal="triggerPrint('thermal')" @print-a4="triggerPrint('a4')" @whatsapp="openWhatsApp" @copy="copyInvoiceDetails" @open-cancel="showCancelModal = true" @back="goBack" />
      <InvoiceShowInteractiveView v-if="activeMode === 'interactive'" :invoice="invoice" :customer-info="customerInfo" :invoice-items="invoiceItems" :invoice-payments="invoicePayments" />
      <InvoiceShowThermalReceipt v-else-if="activeMode === 'thermal'" :invoice="invoice" :items="invoiceItems" :company-info="companyInfo" :customer-info="customerInfo" :invoice-time="invoiceTime" />
      <InvoiceShowA4Document v-else-if="activeMode === 'a4'" :invoice="invoice" :items="invoiceItems" :company-info="companyInfo" :customer-info="customerInfo" />
      <CancelInvoiceModal :show="showCancelModal" :invoice="invoice" v-model:reason="cancelReason" :loading="isCancelling" @close="showCancelModal = false" @confirm="confirmCancelInvoice" />
    </div>
  </div>
</template>

<script setup>
import { useInvoiceShow } from '../../Composables/useInvoiceShow';
import BaseButton from '../../Components/Common/BaseButton.vue';
import InvoiceShowHeader from '../../Components/Invoices/Show/InvoiceShowHeader.vue';
import InvoiceShowActionsBar from '../../Components/Invoices/Show/InvoiceShowActionsBar.vue';
import InvoiceShowInteractiveView from '../../Components/Invoices/Show/InvoiceShowInteractiveView.vue';
import InvoiceShowThermalReceipt from '../../Components/Invoices/Show/InvoiceShowThermalReceipt.vue';
import InvoiceShowA4Document from '../../Components/Invoices/Show/InvoiceShowA4Document.vue';
import CancelInvoiceModal from '../../Components/Invoices/Show/CancelInvoiceModal.vue';

const {
  invoice, isLoading, error, activeMode, setMode, showCancelModal, cancelReason, isCancelling,
  companyInfo, customerInfo, invoiceItems, invoicePayments, invoiceTime, isCancelled,
  triggerPrint, copyInvoiceDetails, openWhatsApp, confirmCancelInvoice, goBack
} = useInvoiceShow();
</script>
