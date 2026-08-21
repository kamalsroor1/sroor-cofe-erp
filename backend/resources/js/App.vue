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
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAppConfigStore } from './stores/appConfig';
import { useAuthStore } from './stores/auth';
import SpaLayout from './Layouts/SpaLayout.vue';

const route = useRoute();
const appConfigStore = useAppConfigStore();
const authStore = useAuthStore();

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
            console.error('Error bootstrapping SPA app:', e);
        }
    } else {
        await appConfigStore.fetchTranslations(appConfigStore.locale);
        window.spaTranslations = appConfigStore.translations;
    }
});
</script>
