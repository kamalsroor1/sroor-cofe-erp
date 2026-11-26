<script setup>
import { onMounted, ref } from 'vue';
import { CreditCard } from 'lucide-vue-next';
import ErrorState from '@/Components/Common/ErrorState.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import BillingInvoiceDetailsModal from '@/Components/Billing/BillingInvoiceDetailsModal.vue';
import BillingPartialBanner from '@/Components/Billing/BillingPartialBanner.vue';
import BillingInvoicesList from '@/Components/Billing/BillingInvoicesList.vue';
import BillingPaymentModal from '@/Components/Billing/BillingPaymentModal.vue';
import PlanChangeSection from '@/Components/Billing/PlanChangeSection.vue';
import BillingSkeleton from '@/Components/Billing/BillingSkeleton.vue';
import SubscriptionAddonsList from '@/Components/Billing/SubscriptionAddonsList.vue';
import SubscriptionPriceSchedule from '@/Components/Billing/SubscriptionPriceSchedule.vue';
import SubscriptionPlanCard from '@/Components/Billing/SubscriptionPlanCard.vue';
import SubscriptionUsageList from '@/Components/Billing/SubscriptionUsageList.vue';
import { useBillingInvoiceScreens } from '@/Composables/useBillingInvoiceScreens';
import { useSubscription } from '@/Composables/useSubscription';

const {
  subscription,
  invoices,
  isLoading,
  error,
  load,
  requestRenewal,
  refreshInvoices,
  submitPayment,
  cancelPayment,
} = useSubscription();
const { detailsId, payId, detailsInvoice, payInvoice, openById, openDetails, openPay, openFromQuery } =
  useBillingInvoiceScreens(invoices);

const renewing = ref(false);
const withdrawing = ref(false);
const requestOpen = ref(false);

const onRenew = async (cycle) => {
  renewing.value = true;
  const result = await requestRenewal(cycle);
  renewing.value = false;
  if (result?.invoiceId) openById(result.invoiceId);
};

const onWithdraw = async (payment) => {
  if (!payment) return;
  withdrawing.value = true;
  await cancelPayment(payment.id);
  withdrawing.value = false;
};

const onCancelPayment = async (payment) => {
  if (await cancelPayment(payment.id)) detailsId.value = null;
};

onMounted(async () => {
  await load();
  openFromQuery();
});
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
      <BillingPartialBanner v-if="subscription.partial_payment" :payment="subscription.partial_payment" />
      <SubscriptionPlanCard :data="subscription" @change-plan="requestOpen = true" />
      <PlanChangeSection
        v-model:open="requestOpen"
        :subscription="subscription"
        :renewing="renewing"
        @changed="refreshInvoices().catch(() => null)"
        @open-invoice="openById"
        @renew="onRenew"
      />
      <SubscriptionPriceSchedule :schedule="subscription.subscription?.price_schedule" />
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
        <SubscriptionUsageList :usage="subscription.usage" />
        <SubscriptionAddonsList :addons="subscription.addons" />
      </div>
      <BillingInvoicesList :invoices="invoices" @details="openDetails" @pay="openPay" />
    </template>

    <BillingInvoiceDetailsModal
      :show="!!detailsInvoice"
      :invoice="detailsInvoice"
      @close="detailsId = null"
      @pay="openPay"
      @cancel-payment="onCancelPayment"
    />
    <BillingPaymentModal
      :show="!!payInvoice"
      :invoice="payInvoice"
      :methods="subscription?.payment_methods || []"
      :support="subscription?.support || {}"
      :plan-name="subscription?.plan?.name || ''"
      :submitter="submitPayment"
      :withdrawing="withdrawing"
      @close="payId = null"
      @paid="payId = null"
      @withdraw="onWithdraw"
      @view-invoice="openDetails"
    />
  </div>
</template>
