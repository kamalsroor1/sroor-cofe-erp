<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-900 flex items-center justify-center p-4 sm:p-6 selection:bg-theme-primary selection:text-white relative overflow-hidden font-sans transition-colors duration-300" dir="rtl">
    <!-- Theme Switcher floating button on top left -->
    <div class="absolute top-4 left-4 z-20">
      <button
        type="button"
        @click="toggleTheme"
        class="px-3 py-2 rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 shadow-md backdrop-blur-md hover:scale-105 transition cursor-pointer flex items-center gap-2 text-xs font-bold font-tajawal"
        :title="appConfigStore.theme === 'dark' ? 'التحويل للوضع النهاري' : 'التحويل للوضع الليلي'"
      >
        <Sun v-if="appConfigStore.theme === 'dark'" class="w-4 h-4 text-theme-primary" />
        <Moon v-else class="w-4 h-4 text-indigo-500" />
        <span class="hidden sm:inline">{{ appConfigStore.theme === 'dark' ? 'الوضع النهاري' : 'الوضع الليلي' }}</span>
      </button>
    </div>

    <!-- Glowing Ambient Lighting Background Blobs -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-theme-light rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md bg-white/95 dark:bg-slate-900/90 backdrop-blur-2xl border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl shadow-slate-300/40 dark:shadow-none space-y-6 relative z-10">
      <!-- Active Workspace Badge (Tenant Switcher) -->
      <WorkspaceBadge
        v-if="hasActiveWorkspace"
        :workspace-name="displayWorkspaceName"
        :workspace-code="activeWorkspaceCode"
        @change="switchWorkspace"
      />

      <!-- Header / Brand Logo -->
      <div class="text-center space-y-3">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-tr from-amber-500/20 to-amber-600/10 border border-theme-border text-theme-primary shadow-2xl shadow-amber-500/10">
          <Building2 class="w-10 h-10" />
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 dark:text-white font-tajawal tracking-tight">
            {{ isExplicitCentralAdmin ? 'منظومة ERP السحابية' : (displayWorkspaceName || appConfigStore.tenant?.name || appConfigStore.companyName || 'منظومة المحل') }}
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-bold mt-1">
            {{ isExplicitCentralAdmin ? 'لوحة الإدارة المركزية والفوترة السحابية' : (appConfigStore.companySubtitle || 'لإدارة المبيعات والمخزون والفروع') }}
          </p>
        </div>
      </div>

      <!-- Validation Errors Global Alert -->
      <div v-if="errorMessage" class="p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-2xl text-xs text-rose-500 dark:text-rose-400 font-bold flex items-center gap-2">
        <AlertTriangle class="w-4 h-4 shrink-0" />
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Biometric Quick Login (If Enabled on Device) -->
      <div v-if="isBiometricEnabled" class="space-y-3">
        <button
          type="button"
          @click="handleBiometricLogin"
          :disabled="isLoading || isAuthenticating"
          class="w-full h-12 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-black shadow-lg shadow-emerald-500/25 text-sm rounded-2xl flex items-center justify-center gap-2.5 transition-all active:scale-[0.98] cursor-pointer font-tajawal disabled:opacity-50"
        >
          <template v-if="isAuthenticating">
            <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            <span>جاري التحقق من البصمة...</span>
          </template>
          <template v-else>
            <Fingerprint class="w-5 h-5 animate-pulse" />
            <span>الدخول السريع بالبصمة ({{ biometricUser }})</span>
          </template>
        </button>

        <div class="relative flex items-center justify-center my-3">
          <div class="border-t border-slate-200 dark:border-slate-800 w-full"></div>
          <span class="bg-white dark:bg-slate-900 px-3 text-[10px] font-bold text-slate-400">أو إدخال كلمة المرور يدويًا</span>
        </div>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="handleLogin" class="space-y-4">
        <!-- Phone / Username Field -->
        <BaseInput
          v-model="form.login"
          id="login"
          :label="isCentralHub ? $t('auth.phone') : $t('auth.phone_or_email')"
          :placeholder="isCentralHub ? $t('auth.phone_placeholder') : '2m@test.com أو رقم الهاتف'"
          :required="true"
          :leading-icon="Phone"
          dir="ltr"
          :error="errorMessage"
          wrapper-class="text-right"
        />

        <!-- Password Field with Toggle -->
        <BaseInput
          v-model="form.password"
          id="password"
          type="password"
          :label="$t('auth.password_label')"
          :placeholder="$t('auth.password_placeholder')"
          :required="true"
          :leading-icon="Lock"
          dir="ltr"
          :error="errorMessage"
          wrapper-class="text-right"
        />

        <!-- Options: Remember Me & Biometric Setup -->
        <div class="space-y-2 pt-1">
          <div class="flex items-center justify-between">
            <BaseCheckbox
              v-model="form.remember"
              :label="$t('auth.remember_me')"
            />
          </div>

          <div v-if="isAvailable && !isBiometricEnabled" class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-600 dark:text-emerald-400 font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Fingerprint class="w-4 h-4 text-emerald-500" />
              <span>تفعيل الدخول بالبصمة لهذا الحساب</span>
            </div>
            <input
              type="checkbox"
              v-model="enableBiometricOnSuccess"
              class="w-4 h-4 rounded border-slate-300 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
            />
          </div>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="isLoading || isAuthenticating"
          class="w-full h-12 bg-theme-gradient text-white font-black shadow-theme-primary text-sm rounded-2xl shadow-xl shadow-theme-primary flex items-center justify-center gap-2 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer font-tajawal"
        >
          <template v-if="isLoading">
            <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            <span>{{ $t('auth.logging_in') }}</span>
          </template>
          <template v-else>
            <LogIn class="w-5 h-5" />
            <span>{{ $t('auth.login_button') }}</span>
          </template>
        </button>
      </form>

      <!-- Quick Account Switcher (Only on Central Hub Baraa Solutions) -->
      <div v-if="isCentralHub" class="pt-4 border-t border-slate-200 dark:border-slate-800/80 space-y-2.5">
        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 font-bold font-tajawal">
          <span class="flex items-center gap-1">
            <Key class="w-3.5 h-3.5 text-theme-primary" />
            {{ $t('auth.quick_accounts') }}
          </span>
          <span class="text-slate-400 text-[10px]">{{ $t('auth.click_to_fill') }}</span>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            @click="fillAccount('01012316954', 'password')"
            class="p-2.5 bg-slate-50 dark:bg-slate-800/60 hover:bg-slate-100 dark:hover:bg-slate-800/80 border border-slate-200 dark:border-slate-800 hover:border-theme-primary rounded-xl text-start transition-all group cursor-pointer"
          >
            <div class="flex items-center gap-1.5">
              <Crown class="w-3.5 h-3.5 text-theme-primary group-hover:scale-110 transition-transform shrink-0" />
              <span class="text-[11px] font-bold text-slate-900 dark:text-slate-200 truncate font-tajawal">{{ $t('auth.super_admin_1') }}</span>
            </div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5" dir="ltr">01012316954</div>
          </button>

          <button
            type="button"
            @click="fillAccount('01140003020', 'password')"
            class="p-2.5 bg-slate-50 dark:bg-slate-800/60 hover:bg-slate-100 dark:hover:bg-slate-800/80 border border-slate-200 dark:border-slate-800 hover:border-theme-primary rounded-xl text-start transition-all group cursor-pointer"
          >
            <div class="flex items-center gap-1.5">
              <Crown class="w-3.5 h-3.5 text-theme-primary group-hover:scale-110 transition-transform shrink-0" />
              <span class="text-[11px] font-bold text-slate-900 dark:text-slate-200 truncate font-tajawal">{{ $t('auth.super_admin_2') }}</span>
            </div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5" dir="ltr">01140003020</div>
          </button>
        </div>
      </div>

      <!-- Version & Platform Badge -->
      <div class="text-center pt-2">
        <span class="text-[11px] font-mono font-medium text-slate-500 dark:text-slate-400">
          {{ appConfigStore.platformName || 'منظومة ERP السحابية' }} • <span class="text-theme-primary font-bold">v{{ versionData?.version || '1.0.1' }}</span>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import versionData from '../../version.json';
import { useAppConfigStore } from '../../stores/appConfig';
import { useBiometricAuth } from '../../Composables/useBiometricAuth';
import BaseInput from '../../Components/Form/BaseInput.vue';
import BaseCheckbox from '../../Components/Form/BaseCheckbox.vue';
import WorkspaceBadge from '../../Components/Auth/WorkspaceBadge.vue';
import {
    AlertTriangle,
    Building2,
    Phone,
    Lock,
    Eye,
    EyeOff,
    LogIn,
    Key,
    Crown,
    Sun,
    Moon,
    Fingerprint,
} from 'lucide-vue-next';

import { trans } from '../../helpers/trans';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();

const activeWorkspaceCode = ref(localStorage.getItem('active_tenant') || localStorage.getItem('tenant_id') || '');
const activeWorkspaceName = ref(localStorage.getItem('tenant_name') || '');

const {
    isAvailable,
    isBiometricEnabled,
    biometricUser,
    isAuthenticating,
    checkAvailability,
    registerBiometrics,
    loginWithBiometrics,
} = useBiometricAuth();

const enableBiometricOnSuccess = ref(true);

const toggleTheme = () => {
    const nextTheme = appConfigStore.theme === 'dark' ? 'light' : 'dark';
    appConfigStore.setTheme(nextTheme);
};

const isCentralHub = computed(() => {
    const host = window.location.hostname;
    return host === 'baraa-solutions.com' || host === 'www.baraa-solutions.com' || host === 'localhost' || host === '127.0.0.1';
});

const isExplicitCentralAdmin = computed(() => {
    return isCentralHub.value && route.query.central === '1';
});

const hasActiveWorkspace = computed(() => {
    return !isExplicitCentralAdmin.value && (!!activeWorkspaceCode.value || !isCentralHub.value);
});

const displayWorkspaceName = computed(() => {
    return activeWorkspaceName.value || appConfigStore.tenant?.name || activeWorkspaceCode.value;
});

const switchWorkspace = async () => {
    localStorage.removeItem('active_tenant');
    localStorage.removeItem('tenant_id');
    localStorage.removeItem('tenant_name');
    localStorage.removeItem('tenant_server_url');
    localStorage.removeItem('tenant_domain');

    if (window.electronAPI?.isElectron) {
        await window.electronAPI.saveSettings({
            tenantId: '',
            serverUrl: 'https://baraa-solutions.com',
        });
    }

    const host = window.location.hostname;
    const isCentral = host === 'baraa-solutions.com' || host === 'www.baraa-solutions.com' || host === 'localhost' || host === '127.0.0.1';

    if (!isCentral) {
        window.location.href = 'https://baraa-solutions.com/workspace';
    } else {
        router.push({ name: 'workspace.connect' });
    }
};

const form = reactive({
    login: '',
    password: '',
    remember: true,
});

const showPassword = ref(false);
const isLoading = ref(false);
const errorMessage = ref('');

onMounted(async () => {
    // 0. If on central domain and not explicit central admin, verify active workspace or guide to step 1
    if (isCentralHub.value && route.query.central !== '1') {
        const storedTenant = localStorage.getItem('active_tenant') || localStorage.getItem('tenant_id');
        if (!storedTenant) {
            router.replace({ name: 'workspace.connect' });
            return;
        }
    }

    if (!window.spaTranslations || Object.keys(window.spaTranslations).length === 0) {
        await appConfigStore.fetchTranslations('ar');
    }
    await checkAvailability();

    // If biometric is enabled and available, prompt once seamlessly
    if (isBiometricEnabled.value && !authStore.isAuthenticated) {
        handleBiometricLogin();
    }
});


const fillAccount = (phone, password) => {
    form.login = phone;
    form.password = password;
    errorMessage.value = '';
};

const handleBiometricLogin = async () => {
    errorMessage.value = '';
    const credentials = await loginWithBiometrics();
    if (credentials) {
        form.login = credentials.login;
        form.password = credentials.password;
        await handleLogin(true);
    }
};

const handleLogin = async (isBiometricFlow = false) => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        await authStore.login({
            login: form.login,
            password: form.password,
            device_name: 'vue-spa',
        });

        // Register biometrics if requested and supported
        if (!isBiometricFlow && enableBiometricOnSuccess.value && isAvailable.value && !isBiometricEnabled.value) {
            await registerBiometrics(form.login, form.password);
        }

        // Initialize system context in background
        await appConfigStore.fetchBootstrapContext();

        // Redirect to intended route or super admin / tenant dashboard
        if (authStore.isSuperAdmin) {
            const redirectPath = route.query.redirect && route.query.redirect !== '/'
                ? route.query.redirect
                : '/super-admin/dashboard';
            router.push(redirectPath);
        } else {
            const redirectPath = route.query.redirect || '/';
            router.push(redirectPath);
        }
    } catch (error) {
        errorMessage.value = error.userMessage || error.response?.data?.message || trans('auth.failed');
    } finally {
        isLoading.value = false;
    }
};
</script>
