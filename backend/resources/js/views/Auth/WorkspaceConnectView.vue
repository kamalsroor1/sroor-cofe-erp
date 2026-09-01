<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-900 flex items-center justify-center p-4 sm:p-6 selection:bg-theme-primary selection:text-white relative overflow-hidden font-sans transition-colors duration-300" dir="rtl">
    <!-- Glowing Ambient Lighting Background Blobs -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-theme-light rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md bg-white/95 dark:bg-slate-900/90 backdrop-blur-2xl border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl shadow-slate-300/40 dark:shadow-none space-y-6 relative z-10">
      <!-- State 1: Connecting / Resolving Magic Link -->
      <WorkspaceConnectingState
        v-if="isAutoConnecting || (isLoading && resolvedTenant)"
        :tenant-code="code"
        :resolved-tenant="resolvedTenant"
        :status-message="statusMessage"
        @cancel="cancelAutoConnect"
      />

      <!-- State 2: Manual Code Input Form (Step 1) -->
      <WorkspaceStepInput
        v-else
        v-model="code"
        :is-loading="isLoading"
        :error-message="errorMessage"
        @submit="handleManualSubmit"
        @central-login="navigateToCentralLogin"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import { trans } from '../../helpers/trans';
import WorkspaceStepInput from '../../Components/Auth/WorkspaceStepInput.vue';
import WorkspaceConnectingState from '../../Components/Auth/WorkspaceConnectingState.vue';

const route = useRoute();
const router = useRouter();

const code = ref('');
const isLoading = ref(false);
const isAutoConnecting = ref(false);
const errorMessage = ref('');
const statusMessage = ref('');
const resolvedTenant = ref(null);

const resolveWorkspace = async (targetCode) => {
    if (!targetCode || !targetCode.trim()) return;
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await api.get(`/central/tenants/resolve?code=${encodeURIComponent(targetCode.trim())}`);
        const data = response.data?.data;

        if (data) {
            resolvedTenant.value = data;
            statusMessage.value = trans('auth.magic_link_connecting');

            // 1. Persistent workspace memory in localStorage
            localStorage.setItem('active_tenant', data.slug || data.tenant_id);
            localStorage.setItem('tenant_id', data.tenant_id);
            localStorage.setItem('tenant_name', data.name);
            localStorage.setItem('tenant_server_url', data.server_url);
            localStorage.setItem('tenant_domain', data.domain);

            if (data.users && Array.isArray(data.users)) {
                localStorage.setItem('tenant_users', JSON.stringify(data.users));
            }

            // 2. Electron Desktop Sync
            if (window.electronAPI?.isElectron) {
                await window.electronAPI.saveSettings({
                    serverUrl: data.server_url,
                    tenantId: data.tenant_id,
                });
                return;
            }

            // 3. Navigation: On mobile apps / Capacitor, NEVER use external window.location.href
            const host = window.location.hostname;
            const isCentralHost = host === 'baraa-solutions.com' || host === 'www.baraa-solutions.com';
            const isNativeMobile = window.Capacitor !== undefined || window.isNativeMobile === true || navigator.userAgent.includes('wv') || navigator.userAgent.includes('Mobile');

            setTimeout(() => {
                if (!isNativeMobile && isCentralHost && data.domain && !host.startsWith(data.domain)) {
                    window.location.href = `${data.server_url}/login`;
                } else {
                    router.push({ name: 'login' });
                }
            }, 600);
        }
    } catch (err) {
        isAutoConnecting.value = false;
        errorMessage.value = err.response?.data?.message || trans('auth.workspace_not_found');
    } finally {
        isLoading.value = false;
    }
};

const handleManualSubmit = () => {
    resolveWorkspace(code.value);
};

const cancelAutoConnect = () => {
    isAutoConnecting.value = false;
    isLoading.value = false;
    resolvedTenant.value = null;
    errorMessage.value = '';
};

const navigateToCentralLogin = () => {
    router.push({ name: 'login', query: { central: '1' } });
};

onMounted(() => {
    const queryCode = route.query.tenant || route.query.code;
    if (queryCode) {
        code.value = String(queryCode).trim();
        isAutoConnecting.value = true;
        resolveWorkspace(code.value);
    }
});
</script>
