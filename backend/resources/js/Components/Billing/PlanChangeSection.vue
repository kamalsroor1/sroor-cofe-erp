<script setup>
import { onMounted, toRef } from 'vue';
import BillingChangePanel from './BillingChangePanel.vue';
import PlanChangePendingCard from './PlanChangePendingCard.vue';
import PlanChangeRequestModal from './PlanChangeRequestModal.vue';
import PlanChangeRequestsList from './PlanChangeRequestsList.vue';
import SubscriptionDowngradeCard from './SubscriptionDowngradeCard.vue';
import { usePlanChangeRequests } from '@/Composables/usePlanChangeRequests';

const props = defineProps({
  subscription: { type: Object, required: true },
  open: { type: Boolean, default: false },
  renewing: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'changed', 'open-invoice', 'renew', 'issued']);

const {
  requests,
  isLoading,
  hasError,
  isSubmitting,
  cancellingId,
  errors,
  pendingPlanChange,
  pendingAddon,
  availablePlans,
  availableAddons,
  loadRequests,
  submit,
  cancel,
} = usePlanChangeRequests(toRef(props, 'subscription'), async () => emit('changed'));

const onSubmit = async (payload) => {
  if (await submit(payload)) emit('update:open', false);
};

// #232: the panel issues the invoice itself. A new invoice or the existing one it points to opens on the
// payment screen; a held request or a refusal changes what the page shows, so re-read it all.
const onOutcome = async (outcome) => {
  if (outcome.type === 'invoice' || outcome.type === 'duplicate') {
    emit('issued', outcome.invoiceId);
    return;
  }
  emit('changed');
  await loadRequests();
};

// PLAN-13: the downgrade request lands in the history list too, so re-read it together with the summary.
const onDowngraded = async () => {
  emit('changed');
  await loadRequests();
};

onMounted(loadRequests);
</script>

<template>
  <div class="space-y-4">
    <PlanChangePendingCard
      v-if="pendingPlanChange"
      :request="pendingPlanChange"
      :busy="cancellingId === pendingPlanChange.id"
      :ends-at="subscription.subscription?.ends_at || null"
      :has-paid-term="subscription.subscription?.has_paid_term !== false"
      @cancel="cancel"
    />
    <PlanChangePendingCard
      v-if="pendingAddon"
      :request="pendingAddon"
      :busy="cancellingId === pendingAddon.id"
      @cancel="cancel"
    />

    <BillingChangePanel
      :subscription="subscription"
      :has-pending-request="!!pendingPlanChange"
      :busy="isSubmitting || renewing"
      @outcome="onOutcome"
      @renew="$emit('renew', $event)"
      @open-invoice="$emit('open-invoice', $event)"
    />

    <SubscriptionDowngradeCard :subscription="subscription" @changed="onDowngraded" />

    <PlanChangeRequestsList
      :requests="requests"
      :is-loading="isLoading"
      :has-error="hasError"
      :support="subscription.support"
      @retry="loadRequests"
      @open-invoice="$emit('open-invoice', $event)"
    />

    <PlanChangeRequestModal
      :show="open"
      :plans="availablePlans"
      :addons="availableAddons"
      :has-pending-plan="!!pendingPlanChange"
      :has-pending-addon="!!pendingAddon"
      :can-renew-too="subscription.subscription?.has_paid_term !== false"
      :support="subscription.support"
      :is-submitting="isSubmitting"
      :errors="errors"
      @close="$emit('update:open', false)"
      @submit="onSubmit"
    />
  </div>
</template>
