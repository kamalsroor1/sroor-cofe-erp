<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl overflow-hidden font-tajawal">
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="4" />
    </div>

    <div v-else-if="logs.length > 0">
      <div class="divide-y divide-slate-200 dark:divide-slate-800/60">
        <div
          v-for="log in logs"
          :key="log.id"
          class="p-4 hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs"
        >
          <div class="flex items-start gap-3 flex-1 min-w-0">
            <span class="text-xl shrink-0 mt-0.5">{{ log.module_icon || '📋' }}</span>
            <div class="space-y-1 flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                  :class="getActionBadgeClass(log.action)"
                >
                  {{ log.action }}
                </span>
                <span class="font-bold text-slate-900 dark:text-white">{{ log.description }}</span>
              </div>

              <div class="flex items-center gap-3 text-[11px] text-slate-500 dark:text-slate-400 flex-wrap font-sans">
                <span>{{ $t('activity.staff_label') }} <strong class="text-slate-700 dark:text-slate-300 font-bold">{{ log.user_name }}</strong></span>
                <span>{{ $t('activity.branch_label') }} <strong class="text-slate-700 dark:text-slate-300 font-bold">{{ log.store_name }}</strong></span>
                <span v-if="log.ip_address" class="font-mono text-slate-400">{{ $t('activity.ip_address') }}: {{ log.ip_address }}</span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-3 self-end md:self-center font-mono text-[11px] shrink-0">
            <span class="text-slate-500 dark:text-slate-400">{{ log.created_at }}</span>
            <button
              v-if="log.properties || log.payload"
              type="button"
              @click="$emit('inspect', log)"
              class="min-h-[30px] px-3 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 rounded-xl text-theme-primary font-sans font-bold transition cursor-pointer active:scale-95"
            >
              {{ $t('activity.details_btn') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 font-mono">
        <span class="font-tajawal">{{ $t('activity.total_records') }} {{ pagination.total }}</span>
        <div class="flex items-center gap-2 font-sans font-tajawal">
          <button
            type="button"
            :disabled="pagination.current_page === 1"
            @click="$emit('page-change', pagination.current_page - 1)"
            class="min-h-[36px] px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-50 border border-slate-300 dark:border-slate-700 rounded-xl cursor-pointer active:scale-95"
          >
            {{ $t('common.previous') }}
          </button>
          <span class="font-mono">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button
            type="button"
            :disabled="pagination.current_page === pagination.last_page"
            @click="$emit('page-change', pagination.current_page + 1)"
            class="min-h-[36px] px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-50 border border-slate-300 dark:border-slate-700 rounded-xl cursor-pointer active:scale-95"
          >
            {{ $t('common.next') }}
          </button>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('activity.no_logs_match')"
      :description="$t('activity.adjust_filter_hint')"
      icon="📋"
    />
  </div>
</template>

<script setup>
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';

defineProps({
  logs: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, per_page: 25 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['inspect', 'page-change']);

const getActionBadgeClass = (action) => {
  if (['deleted', 'cancelled', 'login_failed'].includes(action)) {
    return 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400';
  }
  if (['created', 'invoice_created', 'login_success'].includes(action)) {
    return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400';
  }
  if (['updated', 'shift_opened', 'shift_closed'].includes(action)) {
    return 'bg-theme-light border-theme-border text-theme-primary';
  }
  return 'bg-slate-500/10 border-slate-500/30 text-slate-600 dark:text-slate-400';
};
</script>
