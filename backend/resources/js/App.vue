<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 antialiased font-sans selection:bg-amber-500 selection:text-white" dir="rtl">
    <!-- 1. Standalone Guest Views (Login, Marketing Brochure) -->
    <template v-if="isGuestRoute">
      <router-view v-slot="{ Component, route }">
        <transition name="page" mode="out-in">
          <component :is="Component" :key="route.fullPath" />
        </transition>
      </router-view>
    </template>

    <!-- 2. Persistent Authenticated App Shell (Header, Sidebar & Mobile Bottom Nav STAY PERMANENTLY MOUNTED) -->
    <SpaLayout v-else>
      <router-view v-slot="{ Component, route }">
        <transition name="page" mode="out-in">
          <component :is="Component" :key="route.fullPath" />
        </transition>
      </router-view>
    </SpaLayout>

    <!-- 3. Global In-App APK Auto-Updater Modal (Renders seamlessly across all views) -->
    <AppUpdateModal />
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAppConfigStore } from './stores/appConfig';
import { useAuthStore } from './stores/auth';
import { useAppUpdate } from './Composables/useAppUpdate';
import SpaLayout from './Layouts/SpaLayout.vue';
import AppUpdateModal from './Components/AppUpdateModal.vue';

const route = useRoute();
const appConfigStore = useAppConfigStore();
const authStore = useAuthStore();
const { checkForUpdates } = useAppUpdate();

const isGuestRoute = computed(() => {
    return route.meta?.guestOnly || route.name === 'login' || route.name === 'marketing.brochure';
});

onMounted(async () => {
    // 1. Initialize Theme from storage or preference
    const savedTheme = localStorage.getItem('theme_preference') || 'dark';
    appConfigStore.setTheme(savedTheme);

    // 2. Fetch translations if guest or bootstrap context if authenticated
    if (authStore.isAuthenticated) {
        try {
            await appConfigStore.fetchBootstrapContext();
            window.spaTranslations = appConfigStore.translations;
        } catch (e) {
            console.warn('Bootstrapping error, resetting auth session:', e);
            authStore.clearSession();
        }
    } else {
        await appConfigStore.fetchTranslations(appConfigStore.locale);
        window.spaTranslations = appConfigStore.translations;
    }

    // 3. Check for app updates in the background (Non-blocking for authenticated users)
    if (authStore.isAuthenticated) {
        checkForUpdates(false);
    }
});
</script>
