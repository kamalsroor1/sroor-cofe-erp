<script setup>
import { FilePlus2, ReceiptText } from 'lucide-vue-next';
import DashboardSectionCard from '../../Common/DashboardSectionCard.vue';
import ErrorState from '../../Common/ErrorState.vue';
import EmptyState from '../../Common/EmptyState.vue';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import TenantSubscriptionSummary from './TenantSubscriptionSummary.vue';
import TenantInvoicesTable from './TenantInvoicesTable.vue';
import IssueInvoiceModal from './IssueInvoiceModal.vue';
import RecordPaymentModal from './RecordPaymentModal.vue';
import { useTenantBilling } from '../../../Composables/useTenantBilling';
import { useIssueInvoice } from '../../../Composables/useIssueInvoice';
import { useRecordPayment } from '../../../Composables/useRecordPayment';

const props = defineProps({
  tenantId: { type: [String, Number], required: true },
  tenantName: { type: String, default: '' },
});

const {
  invoices,
  subscription,
  isLoading,
  loadError,
  voidingId,
  canView,
  canManage,
  daysLeft,
  fetchBilling,
  voidInvoice,
} = useTenantBilling(props.tenantId);

const issue = useIssueInvoice(props.tenantId, fetchBilling, () => subscription.value?.plan?.id ?? null);
const record = useRecordPayment(fetchBilling);
</script>

<template>
  <DashboardSectionCard
    v-if="canView"
    :title="$t('billing.console.tenant.section_title')"
    :subtitle="$t('billing.console.tenant.section_subtitle')"
    :icon="ReceiptText"
    icon-bg="bg-emerald-100 dark:bg-emerald-500/15"
    icon-color="text-emerald-600 dark:text-emerald-400"
    wrapper-class="shadow-sm dark:shadow-xl"
  >
    <template v-if="isLoading">
      <div class="space-y-4" role="status" aria-busy="true" :aria-label="$t('common.loading')">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
          <div v-for="cell in 5" :key="cell" class="space-y-1.5">
            <Skeleton width="w-16" height="h-2.5" rounded="rounded-md" />
            <Skeleton width="w-24" height="h-4" rounded="rounded-md" />
          </div>
        </div>
        <Skeleton height="h-40" rounded="rounded-2xl" />
      </div>
    </template>

    <ErrorState
      v-else-if="loadError"
      compact
      :title="$t('billing.console.tenant.load_failed')"
      :message="loadError"
      @retry="fetchBilling"
    />

    <div v-else class="space-y-5">
      <TenantSubscriptionSummary :subscription="subscription" :days-left="daysLeft" />

      <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">
          {{ $t('billing.console.tenant.invoices_title') }}
        </h3>
        <button
          v-if="canManage"
          type="button"
          class="min-h-[44px] px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black flex items-center gap-2 cursor-pointer transition active:scale-[0.98]"
          @click="issue.open"
        >
          <FilePlus2 class="w-4 h-4" />
          <span>{{ $t('billing.console.tenant.issue') }}</span>
        </button>
      </div>

      <EmptyState v-if="!invoices.length" :icon="ReceiptText" :title="$t('billing.console.tenant.invoices_empty')" />
      <TenantInvoicesTable
        v-else
        :invoices="invoices"
        :can-manage="canManage"
        :voiding-id="voidingId"
        @void="voidInvoice"
        @record-payment="record.open"
      />
    </div>

    <RecordPaymentModal
      v-if="canManage"
      :show="record.isOpen.value"
      :invoice="record.invoice.value"
      :form="record.form.value"
      :errors="record.errors.value"
      :is-submitting="record.isSubmitting.value"
      @close="record.close"
      @submit="record.submit"
      @update:field="record.setField"
    />

    <IssueInvoiceModal
      v-if="canManage"
      :show="issue.isOpen.value"
      :tenant-name="tenantName"
      :form="issue.form.value"
      :errors="issue.errors.value"
      :plans="issue.plans.value"
      :is-service="issue.isService.value"
      :can-add-line="issue.canAddLine.value"
      :is-submitting="issue.isSubmitting.value"
      @close="issue.close"
      @submit="issue.submit"
      @update:field="issue.setField"
      @update:line="issue.setLine"
      @add-line="issue.addLine"
      @remove-line="issue.removeLine"
    />
  </DashboardSectionCard>
</template>
