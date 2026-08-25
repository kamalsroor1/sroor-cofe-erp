<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased font-sans selection:bg-theme-primary selection:text-white flex flex-col" dir="rtl">
    <!-- 0. 🖥️ Native Desktop Frameless Titlebar (Visible ONLY when running in Electron) -->
    <DesktopTitlebar
      @open-hardware="isDesktopHardwareOpen = true"
      @open-shortcuts="isDesktopShortcutsOpen = true"
    />

    <!-- 0. ☕ Global System Initial Boot Splash Screen (Facebook/Native-App Shimmer Loader) -->
    <SystemBootSplash :show="isBooting" />

    <!-- 1. Standalone / Print / Guest Views (Completely Isolated with ZERO Sidebar or Navbars) -->
    <template v-if="isStandaloneRoute">
      <router-view v-slot="{ Component, route }">
        <transition name="page" mode="out-in">
          <component :is="Component" :key="route.fullPath" />
        </transition>
      </router-view>
    </template>

    <!-- 2. Super Admin Dedicated Layout (Isolated Shell for Central Platform Admins) -->
    <SuperAdminLayout v-else-if="isSuperAdminRoute">
      <router-view v-slot="{ Component, route }">
        <transition name="page" mode="out-in">
          <component :is="Component" :key="route.fullPath" />
        </transition>
      </router-view>
    </SuperAdminLayout>

    <!-- 3. Persistent Tenant ERP / POS App Shell -->
    <SpaLayout v-else>
      <router-view v-slot="{ Component, route }">
        <transition name="page" mode="out-in">
          <component :is="Component" :key="route.fullPath" />
        </transition>
      </router-view>
    </SpaLayout>

    <!-- 4. Global In-App APK Auto-Updater Modal (Hidden on Print) -->
    <div class="no-print">
      <AppUpdateModal />
    </div>

    <!-- 5. 🖨️ Desktop Hardware & Shortcuts Modals (Desktop Only) -->
    <template v-if="isDesktop">
      <DesktopPrinterSettingsModal
        :show="isDesktopHardwareOpen"
        @close="isDesktopHardwareOpen = false"
      />
      <DesktopShortcutsModal
        :show="isDesktopShortcutsOpen"
        @close="isDesktopShortcutsOpen = false"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAppConfigStore } from './stores/appConfig';
import { useAuthStore } from './stores/auth';
import { useAppUpdate } from './Composables/useAppUpdate';
import { useDesktopHardware } from './Composables/useDesktopHardware';
import SpaLayout from './Layouts/SpaLayout.vue';
import SuperAdminLayout from './Layouts/SuperAdminLayout.vue';
import AppUpdateModal from './Components/AppUpdateModal.vue';
import SystemBootSplash from './Components/Common/SystemBootSplash.vue';
import DesktopTitlebar from './Components/Common/DesktopTitlebar.vue';
import DesktopPrinterSettingsModal from './Components/Common/DesktopPrinterSettingsModal.vue';
import DesktopShortcutsModal from './Components/Common/DesktopShortcutsModal.vue';

const route = useRoute();
const appConfigStore = useAppConfigStore();
const authStore = useAuthStore();
const { checkForUpdates } = useAppUpdate();
const { isDesktop, openCashDrawer } = useDesktopHardware();

const isDesktopHardwareOpen = ref(false);
const isDesktopShortcutsOpen = ref(false);

const isBooting = ref(true);

const isStandaloneRoute = computed(() => {
    const path = route.path || (typeof window !== 'undefined' ? window.location.pathname : '');
    const meta = route.meta || {};
    return meta.guestOnly || 
           meta.layout === 'blank' ||
           meta.isPrintView ||
           route.name === 'login' || 
           route.name === 'marketing.brochure' ||
           route.name === 'invoices.print' ||
           path.includes('/print') ||
           path === '/login' ||
           (typeof window !== 'undefined' && (window.location.pathname === '/login' || window.location.pathname.includes('/print')));
});

const isSuperAdminRoute = computed(() => {
    const currentPath = route.path || (typeof window !== 'undefined' ? window.location.pathname : '');
    const currentMeta = route.meta || {};
    return currentPath.startsWith('/super-admin') || 
           (typeof window !== 'undefined' && window.location.pathname.startsWith('/super-admin')) ||
           currentMeta.isSuperAdmin;
});

onMounted(async () => {
    try {
        // 1. Initialize Theme from storage or preference
        const savedTheme = localStorage.getItem('theme_preference') || 'dark';
        appConfigStore.setTheme(savedTheme);

        // 2. Fetch translations if guest or bootstrap context if authenticated
        if (authStore.isAuthenticated) {
            await appConfigStore.fetchBootstrapContext();
        } else {
            await appConfigStore.fetchTranslations();
        }
    } catch (error) {
        console.error('Failed to initialize bootstrap context:', error);
    } finally {
        // Smooth transition out of boot splash
        setTimeout(() => {
            isBooting.value = false;
        }, 350);
    }

    // 3. Native App APK Update Check
    checkForUpdates();

    // 4. Global Desktop Hotkeys Listener
    if (isDesktop.value) {
        window.addEventListener('keydown', handleDesktopGlobalKeydown);
    }
});

const handleDesktopGlobalKeydown = (e) => {
    if (e.key === 'F1') {
        e.preventDefault();
        isDesktopShortcutsOpen.value = true;
    } else if (e.key === 'F12') {
        e.preventDefault();
        openCashDrawer();
    }
};

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleDesktopGlobalKeydown);
    }
});
</script>

<style>
/* Page Transition Animations */
.page-enter-active,
.page-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.page-enter-from {
  opacity: 0;
  transform: translateY(4px);
}
.page-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>