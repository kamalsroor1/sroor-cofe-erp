<script setup>
import { computed, onMounted, ref } from 'vue';
import { CreditCard } from 'lucide-vue-next';
import ErrorState from '@/Components/Common/ErrorState.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import BillingInvoiceDetailsModal from '@/Components/Billing/BillingInvoiceDetailsModal.vue';
import BillingInvoicesList from '@/Components/Billing/BillingInvoicesList.vue';
import BillingPaymentModal from '@/Components/Billing/BillingPaymentModal.vue';
import PlanChangeSection from '@/Components/Billing/PlanChangeSection.vue';
import BillingSkeleton from '@/Components/Billing/BillingSkeleton.vue';
import SubscriptionAddonsList from '@/Components/Billing/SubscriptionAddonsList.vue';
import SubscriptionPlanCard from '@/Components/Billing/SubscriptionPlanCard.vue';
import SubscriptionUsageList from '@/Components/Billing/SubscriptionUsageList.vue';
import { useSubscription } from '@/Composables/useSubscription';

const { subscription, invoices, isLoading, error, load, renew, refreshInvoices, submitPayment } = useSubscription();

const renewing = ref(false);
const requestOpen = ref(false);
const detailsId = ref(null);
const payId = ref(null);
const detailsInvoice = computed(() => invoices.value.find((i) => i.id === detailsId.value) || null);
const payInvoice = computed(() => invoices.value.find((i) => i.id === payId.value) || null);

const openInvoice = (invoice) => {
  if (invoice.status.value === 'pending' && !invoice.has_pending_payment) payId.value = invoice.id;
  else detailsId.value = invoice.id;
};

const openInvoiceById = (id) => {
  const invoice = invoices.value.find((i) => i.id === id);
  if (invoice) openInvoice(invoice);
};

const onRenew = async () => {
  renewing.value = true;
  const id = await renew();
  renewing.value = false;
  openInvoiceById(id);
};

const onPay = (invoice) => {
  detailsId.value = null;
  payId.value = invoice.id;
};

onMounted(load);
</script>

<template>
  <div class="space-y-4 font-tajawal">
    <PageHeader
      :title="$t('subscription.billing.page.title')"
      :subtitle="$t('subscription.billing.page.subtitle')"
      :icon="CreditCard"
    />

    <BillingSkeleton v-if="isLoading" />
    <ErrorState v-else-if="error" :message="error" :retry-label="$t('subscription.billing.page.retry')" @retry="load" />
    <template v-else-if="subscription">
      <SubscriptionPlanCard :data="subscription" :busy="renewing" @renew="onRenew" @change-plan="requestOpen = true" />
      <PlanChangeSection
        v-model:open="requestOpen"
        :subscription="subscription"
        @changed="refreshInvoices().catch(() => null)"
        @open-invoice="openInvoiceById"
      />
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
        <SubscriptionUsageList :usage="subscription.usage" />
        <SubscriptionAddonsList :addons="subscription.addons" />
      </div>
      <BillingInvoicesList :invoices="invoices" @details="detailsId = $event.id" @pay="onPay" />
    </template>

    <BillingInvoiceDetailsModal
      :show="!!detailsInvoice"
      :invoice="detailsInvoice"
      @close="detailsId = null"
      @pay="onPay"
    />
    <BillingPaymentModal
      :show="!!payInvoice"
      :invoice="payInvoice"
      :instructions="subscription?.payment_instructions"
      :support-phone="subscription?.support?.phone || ''"
      :submitter="submitPayment"
      @close="payId = null"
      @paid="payId = null"
    />
  </div>
</template>
