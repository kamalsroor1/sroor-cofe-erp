<script setup>
import { Inbox } from 'lucide-vue-next';
import EmptyState from '../../Common/EmptyState.vue';
import ErrorState from '../../Common/ErrorState.vue';
import Pagination from '../../Common/Pagination.vue';
import CardSkeleton from '../../Common/Skeletons/CardSkeleton.vue';
import SignupRequestCard from './SignupRequestCard.vue';

defineProps({
  requests: { type: Array, default: () => [] },
  pagination: { type: Object, default: null },
  isLoading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
  canManage: { type: Boolean, default: false },
});

defineEmits(['retry', 'page-change', 'approve', 'reject']);
</script>

<template>
  <div v-if="isLoading" class="space-y-3" aria-busy="true">
    <CardSkeleton v-for="n in 3" :key="n" :rows="3" />
  </div>

  <ErrorState v-else-if="hasError" :title="$t('signup.console.load_failed')" @retry="$emit('retry')" />

  <EmptyState
    v-else-if="!requests.length"
    :icon="Inbox"
    :title="$t('signup.console.empty_title')"
    :description="$t('signup.console.empty_description')"
  />

  <div v-else class="space-y-3">
    <SignupRequestCard
      v-for="request in requests"
      :key="request.id"
      :request="request"
      :can-manage="canManage"
      @approve="$emit('approve', $event)"
      @reject="$emit('reject', $event)"
    />
    <Pagination v-if="pagination?.last_page > 1" :pagination="pagination" @page-change="$emit('page-change', $event)" />
  </div>
</template>
