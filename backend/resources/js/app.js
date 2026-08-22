import { createApp } from 'vue';
import { createPinia } from 'pinia';
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

const mountEl = document.getElementById('app') || document.getElementById('spa-app');
if (mountEl) {
    router.isReady().then(() => {
        app.mount(mountEl);
    });
}
