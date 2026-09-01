<template>
  <div class="space-y-6 font-tajawal">
    <InvoiceShowKpiGrid :invoice="invoice" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <InvoiceShowCustomerCard
        :customer="customerInfo?.raw"
        :customer-name="customerInfo?.name"
        :customer-phone="customerInfo?.phone"
        :customer-balance="customerInfo?.balance || 0"
      />
      <InvoiceShowStoreCard :invoice="invoice" />
    </div>

    <InvoiceShowItemsTable :items="invoiceItems" />

    <InvoiceShowPaymentsTimeline
      :invoice="invoice"
      :payments="invoicePayments"
    />
  </div>
</template>

<script setup>
import InvoiceShowKpiGrid from './InvoiceShowKpiGrid.vue';
import InvoiceShowCustomerCard from './InvoiceShowCustomerCard.vue';
import InvoiceShowStoreCard from './InvoiceShowStoreCard.vue';
import InvoiceShowItemsTable from './InvoiceShowItemsTable.vue';
import InvoiceShowPaymentsTimeline from './InvoiceShowPaymentsTimeline.vue';

defineProps({
  invoice: { type: Object, default: null },
  customerInfo: { type: Object, default: () => ({}) },
  invoiceItems: { type: Array, default: () => [] },
  invoicePayments: { type: Array, default: () => [] },
});
</script>
