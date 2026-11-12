<template>
  <main
    class="min-h-full h-full overflow-y-auto flex items-center justify-center p-4 bg-slate-50 dark:bg-slate-950 font-tajawal"
    role="alert"
    data-testid="no-store-notice"
  >
    <section
      class="w-full max-w-md space-y-5 rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-xl dark:border-slate-800 dark:bg-slate-900 sm:p-8"
    >
      <div
        class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-theme-border bg-theme-light text-theme-primary"
      >
        <StoreIcon class="h-8 w-8" aria-hidden="true" />
      </div>
      <div class="space-y-2">
        <h1 class="text-lg font-black text-slate-900 dark:text-white">{{ $t('store_scope.notice_title') }}</h1>
        <p
          class="text-sm font-medium leading-relaxed text-slate-600 dark:text-slate-300"
          data-testid="no-store-message"
        >
          {{ authStore.storeNotice?.message }}
        </p>
      </div>
      <button
        type="button"
        data-testid="no-store-logout"
        :disabled="loggingOut"
        class="min-h-[44px] w-full px-5 rounded-2xl btn-primary-theme text-sm font-black flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer disabled:opacity-60"
        @click="logout"
      >
        <LogOut class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
        <span>{{ $t('nav.logout') }}</span>
      </button>
    </section>
  </main>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { LogOut, Store as StoreIcon } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';

const authStore = useAuthStore();
const router = useRouter();
const loggingOut = ref(false);

// The notice is the whole screen, so signing out needs no extra confirmation.
const logout = async () => {
  loggingOut.value = true;
  try {
    await authStore.logout();
  } finally {
    loggingOut.value = false;
  }
  router.replace({ name: 'login' });
};
</script>
