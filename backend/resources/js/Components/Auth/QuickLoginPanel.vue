<script setup>
import { FlaskConical, LogIn, RefreshCw, UserRound, Users } from 'lucide-vue-next';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import EmptyState from '../Common/EmptyState.vue';

defineProps({
  users: {
    type: Array,
    default: () => [],
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: '',
  },
  loggingInUserId: {
    type: [Number, String],
    default: null,
  },
});

const emit = defineEmits(['select', 'retry']);
</script>

<template>
  <section class="space-y-3" data-testid="quick-login-panel">
    <div
      class="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/25 flex items-start gap-2.5 text-amber-700 dark:text-amber-300"
    >
      <FlaskConical class="w-4 h-4 mt-0.5 shrink-0" />
      <div class="space-y-1">
        <span
          class="inline-flex items-center px-2 py-0.5 rounded-lg bg-amber-500/20 text-[11px] font-black font-tajawal"
          data-testid="quick-login-testing-badge"
        >
          {{ $t('auth.quick_login_testing_badge') }}
        </span>
        <p class="text-xs font-bold leading-relaxed">{{ $t('auth.quick_login_hint') }}</p>
      </div>
    </div>

    <ul v-if="isLoading" class="space-y-2" aria-busy="true" :aria-label="$t('common.loading')">
      <li
        v-for="n in 4"
        :key="n"
        class="min-h-14 px-3 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center gap-3"
      >
        <Skeleton width="w-9" height="h-9" rounded="rounded-xl" class-name="shrink-0" />
        <Skeleton :width="n % 2 ? 'w-1/2' : 'w-2/3'" height="h-3.5" />
      </li>
    </ul>

    <div
      v-else-if="error"
      class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-center space-y-3"
      role="alert"
    >
      <p class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ error }}</p>
      <button
        type="button"
        class="min-h-11 px-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-black font-tajawal inline-flex items-center gap-2 cursor-pointer active:scale-95 transition"
        @click="emit('retry')"
      >
        <RefreshCw class="w-4 h-4 text-theme-primary" />
        <span>{{ $t('auth.quick_login_retry') }}</span>
      </button>
    </div>

    <EmptyState v-else-if="users.length === 0" :icon="Users" :title="$t('auth.quick_login_no_users')" />

    <ul v-else class="space-y-2 max-h-80 overflow-y-auto overscroll-contain pe-1" data-testid="quick-login-users">
      <li v-for="user in users" :key="user.id">
        <button
          type="button"
          :disabled="loggingInUserId !== null"
          :aria-label="$t('auth.quick_login_as', { name: user.name })"
          class="w-full min-h-14 px-3 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-start flex items-center gap-3 transition cursor-pointer active:scale-[0.99] active:border-theme-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50 disabled:cursor-not-allowed"
          @click="emit('select', user.id)"
        >
          <span
            class="w-9 h-9 shrink-0 rounded-xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center"
          >
            <UserRound class="w-4 h-4" />
          </span>
          <span class="flex-1 min-w-0 truncate text-sm font-black text-slate-800 dark:text-slate-100 font-tajawal">
            {{ user.name }}
          </span>
          <span
            v-if="loggingInUserId === user.id"
            class="w-5 h-5 shrink-0 border-2 border-primary border-t-transparent rounded-full animate-spin"
          ></span>
          <LogIn v-else class="w-4 h-4 shrink-0 text-slate-400 dark:text-slate-500" />
        </button>
      </li>
    </ul>
  </section>
</template>
