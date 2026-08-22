<template>
  <div class="min-h-screen bg-slate-950 flex items-center justify-center p-4 sm:p-6 selection:bg-amber-500 selection:text-white relative overflow-hidden font-sans" dir="rtl">
    <!-- Glowing Ambient Lighting Background Blobs -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md bg-slate-900/90 backdrop-blur-2xl border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative z-10">
      <!-- Header / Brand Logo -->
      <div class="text-center space-y-3">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-tr from-amber-500/20 to-amber-600/10 border border-amber-500/30 text-amber-400 shadow-2xl shadow-amber-500/10">
          <Building2 class="w-10 h-10" />
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 dark:text-white font-tajawal tracking-tight">
            {{ isCentralHub ? 'منظومة ERP السحابية' : (appConfigStore.tenant?.name || appConfigStore.companyName || 'منظومة المحل') }}
          </h1>
          <p class="text-xs text-slate-400 font-bold mt-1">
            {{ isCentralHub ? 'لوحة الإدارة المركزية والفوترة السحابية' : (appConfigStore.companySubtitle || 'لإدارة المبيعات والمخزون والفروع') }}
          </p>
        </div>
      </div>

      <!-- Validation Errors Global Alert -->
      <div v-if="errorMessage" class="p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-2xl text-xs text-rose-400 font-bold flex items-center gap-2">
        <AlertTriangle class="w-4 h-4 shrink-0" />
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="handleLogin" class="space-y-4">
        <!-- Phone / Username Field -->
        <div>
          <label for="login" class="block text-xs font-bold text-slate-300 mb-1.5 font-tajawal">
            {{ isCentralHub ? $t('auth.phone') : 'البريد الإلكتروني أو رقم الهاتف' }} <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <input
              v-model="form.login"
              type="text"
              id="login"
              required
              autofocus
              dir="ltr"
              :placeholder="isCentralHub ? $t('auth.phone_placeholder') : '2m@test.com أو رقم الهاتف'"
              class="w-full h-11 pr-10 pl-4 bg-slate-950/80 border border-slate-700 rounded-2xl text-white text-xs sm:text-sm font-mono focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none transition-all placeholder:text-slate-500 shadow-inner"
              :class="{ 'border-rose-500 focus:ring-rose-500': errorMessage }"
            >
            <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 pointer-events-none">
              <Phone class="w-4 h-4" />
            </span>
          </div>
        </div>

        <!-- Password Field with Toggle -->
        <div>
          <label for="password" class="block text-xs font-bold text-slate-300 mb-1.5 font-tajawal">
            {{ $t('auth.password_label') }} <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              id="password"
              required
              dir="ltr"
              :placeholder="$t('auth.password_placeholder')"
              class="w-full h-11 pr-10 pl-11 bg-slate-950/80 border border-slate-700 rounded-2xl text-white text-xs sm:text-sm font-mono focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none transition-all placeholder:text-slate-500 shadow-inner"
              :class="{ 'border-rose-500 focus:ring-rose-500': errorMessage }"
            >
            <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 pointer-events-none">
              <Lock class="w-4 h-4" />
            </span>
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 hover:text-white transition-colors cursor-pointer"
            >
              <EyeOff v-if="showPassword" class="w-4 h-4" />
              <Eye v-else class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Remember Me Checkbox -->
        <div class="flex items-center justify-between pt-1">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              v-model="form.remember"
              type="checkbox"
              class="w-4 h-4 rounded-lg bg-slate-950 border-slate-700 text-amber-500 focus:ring-amber-500/20 focus:ring-offset-0 transition-all cursor-pointer"
            >
            <span class="text-xs text-slate-400 font-bold font-tajawal">{{ $t('auth.remember_me') }}</span>
          </label>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="isLoading"
          class="w-full h-12 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-sm rounded-2xl shadow-xl shadow-amber-500/20 flex items-center justify-center gap-2 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer font-tajawal"
        >
          <template v-if="isLoading">
            <div class="w-5 h-5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></div>
            <span>{{ $t('auth.logging_in') }}</span>
          </template>
          <template v-else>
            <LogIn class="w-5 h-5" />
            <span>{{ $t('auth.login_button') }}</span>
          </template>
        </button>
      </form>

      <!-- Quick Account Switcher (Only on Central Hub Baraa Solutions) -->
      <div v-if="isCentralHub" class="pt-4 border-t border-slate-800/80 space-y-2.5">
        <div class="flex items-center justify-between text-[11px] text-slate-400 font-bold font-tajawal">
          <span class="flex items-center gap-1">
            <Key class="w-3.5 h-3.5 text-amber-400" />
            {{ $t('auth.quick_accounts') }}
          </span>
          <span class="text-slate-500 text-[10px]">{{ $t('auth.click_to_fill') }}</span>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            @click="fillAccount('01012316954', 'password')"
            class="p-2.5 bg-slate-950/60 hover:bg-slate-800/80 border border-slate-800 hover:border-amber-500/50 rounded-xl text-start transition-all group cursor-pointer"
          >
            <div class="flex items-center gap-1.5">
              <Crown class="w-3.5 h-3.5 text-amber-400 group-hover:scale-110 transition-transform shrink-0" />
              <span class="text-[11px] font-bold text-slate-200 truncate font-tajawal">{{ $t('auth.super_admin_1') }}</span>
            </div>
            <div class="text-[10px] text-slate-400 font-mono mt-0.5" dir="ltr">01012316954</div>
          </button>

          <button
            type="button"
            @click="fillAccount('01140003020', 'password')"
            class="p-2.5 bg-slate-950/60 hover:bg-slate-800/80 border border-slate-800 hover:border-amber-500/50 rounded-xl text-start transition-all group cursor-pointer"
          >
            <div class="flex items-center gap-1.5">
              <Crown class="w-3.5 h-3.5 text-amber-400 group-hover:scale-110 transition-transform shrink-0" />
              <span class="text-[11px] font-bold text-slate-200 truncate font-tajawal">{{ $t('auth.super_admin_2') }}</span>
            </div>
            <div class="text-[10px] text-slate-400 font-mono mt-0.5" dir="ltr">01140003020</div>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useAppConfigStore } from '../../stores/appConfig';
import {
    AlertTriangle,
    Building2,
    Phone,
    Lock,
    Eye,
    EyeOff,
    LogIn,
    Key,
    Crown
} from 'lucide-vue-next';

import { trans } from '../../helpers/trans';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();

const isCentralHub = computed(() => {
    const host = window.location.hostname;
    return host === 'baraa-solutions.com' || host === 'localhost' || host === '127.0.0.1';
});

const form = reactive({
    login: '',
    password: '',
    remember: true,
});

const showPassword = ref(false);
const isLoading = ref(false);
const errorMessage = ref('');

onMounted(async () => {
    if (!window.spaTranslations || Object.keys(window.spaTranslations).length === 0) {
        await appConfigStore.fetchTranslations('ar');
    }
});

const fillAccount = (phone, password) => {
    form.login = phone;
    form.password = password;
    errorMessage.value = '';
};

const handleLogin = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        await authStore.login({
            login: form.login,
            password: form.password,
            device_name: 'vue-spa',
        });

        // Initialize system context in background
        await appConfigStore.fetchBootstrapContext();

        // Redirect to intended route or dashboard
        const redirectPath = route.query.redirect || '/';
        router.push(redirectPath);
    } catch (error) {
        errorMessage.value = error.userMessage || error.response?.data?.message || trans('auth.failed');
    } finally {
        isLoading.value = false;
    }
};
</script>
