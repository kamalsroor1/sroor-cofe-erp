<template>
  <div class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased font-sans transition-colors duration-200" dir="rtl">
    <router-view />
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useAppConfigStore } from './stores/appConfig';
import { useAuthStore } from './stores/auth';

const appConfigStore = useAppConfigStore();
const authStore = useAuthStore();

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
