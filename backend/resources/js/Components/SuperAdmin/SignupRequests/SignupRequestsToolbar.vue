<script setup>
import BaseSearchInput from '../../Form/BaseSearchInput.vue';
import { SIGNUP_STATUSES } from '../../../Composables/useSignupRequests';

defineProps({
  status: { type: String, default: 'pending' },
  search: { type: String, default: '' },
});

defineEmits(['update:status', 'update:search']);
</script>

<template>
  <div
    class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900/90 md:flex-row md:items-center"
  >
    <div role="tablist" class="flex gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 dark:bg-slate-800">
      <button
        v-for="item in SIGNUP_STATUSES"
        :key="item"
        type="button"
        role="tab"
        :aria-selected="status === item"
        class="min-h-11 shrink-0 cursor-pointer rounded-xl px-4 text-xs font-black transition active:scale-95"
        :class="
          status === item
            ? 'bg-white text-indigo-600 shadow-xs dark:bg-slate-900 dark:text-indigo-400'
            : 'text-slate-600 dark:text-slate-300'
        "
        @click="$emit('update:status', item)"
      >
        {{ $t(`signup.statuses.${item}`) }}
      </button>
    </div>
    <div class="flex-1">
      <BaseSearchInput
        :model-value="search"
        :placeholder="$t('signup.console.search_placeholder')"
        :debounce="300"
        @update:model-value="$emit('update:search', $event)"
      />
    </div>
  </div>
</template>
