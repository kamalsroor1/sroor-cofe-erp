<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Bell, BellOff, CheckCheck } from 'lucide-vue-next';
import ErrorState from '../../Common/ErrorState.vue';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import { notificationDotClass, notificationLocation } from '../../../helpers/billingReview';
import { usePlatformNotifications } from '../../../Composables/usePlatformNotifications';

const router = useRouter();
const { items, unreadCount, hasUnread, isLoading, loadError, load, markRead, markAllRead, startPolling } =
  usePlatformNotifications();

const open = ref(false);
const root = ref(null);

const toggle = () => {
  open.value = !open.value;
  if (open.value) load();
};

const openItem = async (item) => {
  open.value = false;
  markRead(item);
  await router.push(notificationLocation(item));
};

const onPointerDown = (event) => {
  if (open.value && root.value && !root.value.contains(event.target)) open.value = false;
};
const onKeydown = (event) => {
  if (event.key === 'Escape') open.value = false;
};

onMounted(() => {
  startPolling();
  document.addEventListener('pointerdown', onPointerDown);
  document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onPointerDown);
  document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="relative flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl border border-slate-300 bg-slate-100 text-slate-800 transition active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
      :aria-label="
        hasUnread
          ? $t('billing.console.notifications.bell_unread', { count: unreadCount })
          : $t('billing.console.notifications.bell_label')
      "
      aria-haspopup="true"
      :aria-expanded="open"
      data-testid="notification-bell"
      @click="toggle"
    >
      <Bell class="h-5 w-5" aria-hidden="true" />
      <span
        v-if="hasUnread"
        class="absolute -end-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[11px] font-black text-white"
        aria-hidden="true"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <div
      v-if="open"
      class="fixed inset-x-3 top-[4.25rem] z-50 max-h-[70vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-800 dark:bg-slate-900 sm:absolute sm:inset-x-auto sm:end-0 sm:top-full sm:mt-2 sm:w-96"
      role="dialog"
      :aria-label="$t('billing.console.notifications.title')"
    >
      <div class="mb-2 flex items-center justify-between gap-2">
        <h2 class="text-sm font-black text-slate-900 dark:text-white">
          {{ $t('billing.console.notifications.title') }}
        </h2>
        <button
          v-if="items.length"
          type="button"
          class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-xl px-2 text-xs font-black text-slate-700 dark:text-slate-200"
          @click="markAllRead"
        >
          <CheckCheck class="h-4 w-4" aria-hidden="true" />
          <span>{{ $t('billing.console.notifications.mark_all') }}</span>
        </button>
      </div>

      <div v-if="isLoading" class="space-y-2" role="status" aria-busy="true" :aria-label="$t('common.loading')">
        <Skeleton v-for="row in 3" :key="row" height="h-16" rounded="rounded-xl" />
      </div>
      <ErrorState
        v-else-if="loadError"
        :title="$t('billing.console.notifications.load_failed')"
        :message="loadError"
        @retry="load"
      />
      <p
        v-else-if="!items.length"
        class="flex items-center justify-center gap-2 py-8 text-xs font-bold text-slate-500 dark:text-slate-400"
      >
        <BellOff class="h-4 w-4" aria-hidden="true" />
        <span>{{ $t('billing.console.notifications.empty') }}</span>
      </p>
      <ul v-else class="space-y-2">
        <li v-for="item in items" :key="item.id">
          <button
            type="button"
            class="min-h-14 w-full cursor-pointer space-y-0.5 rounded-xl border p-3 text-start transition active:scale-[0.99]"
            :class="
              item.severity === 'warning'
                ? 'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200'
                : 'border-slate-200 bg-slate-50 text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100'
            "
            :data-testid="`notification-${item.id}`"
            @click="openItem(item)"
          >
            <span class="flex items-center gap-2 text-sm font-black">
              <span
                class="h-2.5 w-2.5 shrink-0 rounded-full"
                :class="notificationDotClass(item.severity)"
                role="img"
                :aria-label="$t('billing.console.notifications.unread_dot')"
              ></span>
              <span class="min-w-0 truncate">{{ item.title }}</span>
            </span>
            <span class="block text-xs font-bold opacity-80">{{ item.body }}</span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
