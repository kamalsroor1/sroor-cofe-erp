import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { App as CapacitorApp } from '@capacitor/app';
import router from './router';
import App from './App.vue';
import { trans } from './helpers/trans';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Global translation helpers
app.config.globalProperties.$t = trans;
app.config.globalProperties.trans = trans;

window.spaRouter = router;

// 📱 Handle Android Native Hardware Back Button / Swipe Gestures
if (typeof window !== 'undefined') {
    try {
        CapacitorApp.addListener('backButton', () => {
            const currentPath = router.currentRoute.value.path;
            if (currentPath === '/' || currentPath === '/login' || currentPath === '/dashboard') {
                CapacitorApp.minimizeApp();
            } else if (window.history.length > 1) {
                router.back();
            } else {
                router.replace('/');
            }
        });
    } catch (e) {
        // Not in native Capacitor runtime, fallback to default browser behavior
    }
}

const mountEl = document.getElementById('app') || document.getElementById('spa-app');
if (mountEl) {
    router.isReady().then(() => {
        app.mount(mountEl);
    });
}
