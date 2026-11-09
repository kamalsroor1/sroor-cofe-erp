<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { Bell, BellOff, AlertTriangle, Info, TriangleAlert, Users, Wallet } from 'lucide-vue-next';
import { useAppConfigStore } from '../../stores/appConfig';

const appConfigStore = useAppConfigStore();
const router = useRouter();

const isOpen = ref(false);
const rootRef = ref(null);

const notifications = computed(() => appConfigStore.notifications || []);
const count = computed(() => notifications.value.length);
const badgeLabel = computed(() => (count.value > 99 ? '99+' : String(count.value)));

const TYPE_STYLES = {
  danger: { icon: AlertTriangle, cls: 'text-rose-500 bg-rose-500/10' },
  warning: { icon: TriangleAlert, cls: 'text-amber-500 bg-amber-500/10' },
  info: { icon: Info, cls: 'text-sky-500 bg-sky-500/10' },
};
// The server sends lucide icon names (kebab-case); anything unknown falls back to the type icon.
const ICONS = { 'alert-triangle': AlertTriangle, users: Users, wallet: Wallet, info: Info };
const styleOf = (n) => TYPE_STYLES[n.type] || TYPE_STYLES.info;
const iconOf = (n) => ICONS[n.icon] || styleOf(n).icon;

const openLink = (n) => {
  isOpen.value = false;
  if (n.link) router.push(n.link);
};

const onOutsideClick = (e) => {
  if (rootRef.value && !rootRef.value.contains(e.target)) isOpen.value = false;
};

onMounted(() => document.addEventListener('click', onOutsideClick));
onUnmounted(() => document.removeEventListener('click', onOutsideClick));
</script>

<template>
  <div ref="rootRef" class="relative">
    <button
      type="button"
      class="min-h-[44px] min-w-[44px] p-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-xl sm:rounded-2xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition cursor-pointer active:scale-95 shadow-2xs flex items-center justify-center relative"
      :title="$t('nav.layout.notifications')"
      :aria-label="$t('nav.layout.notifications')"
      :aria-expanded="isOpen"
      @click="isOpen = !isOpen"
    >
      <Bell class="w-4 h-4 text-theme-primary" />
      <span
        v-if="count > 0"
        class="absolute -top-1 -end-1 min-w-4 h-4 px-1 bg-rose-500 text-white rounded-full text-[9px] font-black flex items-center justify-center"
      >
        {{ badgeLabel }}
      </span>
    </button>

    <Transition name="fade">
      <div
        v-if="isOpen"
        class="absolute end-0 top-full mt-2 w-80 max-w-[90vw] bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-3 z-50 font-tajawal space-y-2"
      >
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
          <div class="flex items-center gap-1.5 text-xs font-black text-slate-900 dark:text-white">
            <Bell class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('nav.notifications_title') }}</span>
          </div>
          <span
            v-if="count > 0"
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-theme-light text-theme-primary"
          >
            {{ $t('nav.layout.notifications_count', { count }) }}
          </span>
        </div>

        <div v-if="!appConfigStore.isLoaded" class="space-y-1.5" aria-hidden="true">
          <div v-for="i in 3" :key="i" class="h-14 rounded-xl bg-slate-100 dark:bg-slate-900 animate-pulse"></div>
        </div>

        <div v-else-if="count === 0" class="py-8 text-center space-y-2 select-none">
          <BellOff class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600" />
          <div class="text-xs font-bold text-slate-700 dark:text-slate-200">
            {{ $t('nav.layout.notifications_empty') }}
          </div>
          <div class="text-[11px] text-slate-400">{{ $t('nav.layout.notifications_empty_hint') }}</div>
        </div>

        <div v-else class="space-y-1.5 max-h-64 overflow-y-auto text-xs">
          <button
            v-for="(n, idx) in notifications"
            :key="idx"
            type="button"
            class="w-full min-h-[44px] text-start p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-theme-border transition flex items-start gap-2.5 cursor-pointer"
            @click="openLink(n)"
          >
            <span class="shrink-0 mt-0.5 p-1 rounded-lg" :class="styleOf(n).cls">
              <component :is="iconOf(n)" class="w-4 h-4" />
            </span>
            <span class="flex-1 min-w-0">
              <span class="block font-bold text-slate-900 dark:text-white">{{ n.title }}</span>
              <span v-if="n.description" class="block text-[10px] text-slate-400 mt-0.5">{{ n.description }}</span>
              <span v-if="n.link_label" class="block text-[10px] font-bold text-theme-primary mt-1">
                {{ n.link_label }}
              </span>
            </span>
          </button>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-800 pt-2 text-center">
          <router-link
            to="/activity-logs"
            class="text-[11px] font-bold text-theme-primary hover:underline"
            @click="isOpen = false"
          >
            {{ $t('nav.layout.view_all_activity') }}
          </router-link>
        </div>
      </div>
    </Transition>
  </div>
</template>
