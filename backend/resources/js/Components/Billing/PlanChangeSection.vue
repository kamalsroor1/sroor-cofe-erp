<script setup>
import { onMounted, toRef } from 'vue';
import PlanChangePendingCard from './PlanChangePendingCard.vue';
import PlanChangeRequestModal from './PlanChangeRequestModal.vue';
import PlanChangeRequestsList from './PlanChangeRequestsList.vue';
import { usePlanChangeRequests } from '@/Composables/usePlanChangeRequests';

const props = defineProps({
  subscription: { type: Object, required: true },
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'changed', 'open-invoice']);

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

onMounted(loadRequests);
</script>

<template>
  <div class="space-y-4">
    <PlanChangePendingCard
      v-if="pendingPlanChange"
      :request="pendingPlanChange"
      :busy="cancellingId === pendingPlanChange.id"
      @cancel="cancel"
    />
    <PlanChangePendingCard
      v-if="pendingAddon"
      :request="pendingAddon"
      :busy="cancellingId === pendingAddon.id"
      @cancel="cancel"
    />

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
      :support="subscription.support"
      :is-submitting="isSubmitting"
      :errors="errors"
      @close="$emit('update:open', false)"
      @submit="onSubmit"
    />
  </div>
</template>
