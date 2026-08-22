<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
            <Crown class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ $t('super.super_admin_title') }}</h1>
            <p class="text-xs text-slate-400">{{ $t('super.super_admin_subtitle') }}</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <router-link
            to="/super-admin/tenants"
            class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/20 flex items-center gap-2 transition cursor-pointer"
          >
            <Building2 class="w-4 h-4" />
            <span>{{ $t('super.tenants_management') }}</span>
          </router-link>

          <router-link
            to="/super-admin/plans"
            class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-bold text-xs rounded-xl shadow flex items-center gap-2 transition"
          >
            <Layers class="w-4 h-4 text-amber-400" />
            <span>{{ $t('super.plans_management') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-16 text-center">
        <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400">{{ $t('super.loading_metrics') }}</p>
      </div>

      <div v-else class="space-y-6">
        <!-- 4 Platform Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-1">
            <span class="text-slate-400 text-xs font-bold">{{ $t('super.total_tenants') }}</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ metrics.total_tenants || 0 }}</div>
            <div class="text-[10px] text-slate-500">{{ $t('super.platform_accounts') }}</div>
          </div>

          <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-1">
            <span class="text-emerald-400 text-xs font-bold">{{ $t('super.active_tenants') }}</span>
            <div class="text-2xl font-black text-emerald-400 font-mono">{{ metrics.active_tenants || 0 }}</div>
            <div class="text-[10px] text-slate-500">{{ $t('super.active_subscriptions') }}</div>
          </div>

          <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-1">
            <span class="text-amber-400 text-xs font-bold">{{ $t('super.trial_tenants') }}</span>
            <div class="text-2xl font-black text-amber-400 font-mono">{{ metrics.trial_tenants || 0 }}</div>
            <div class="text-[10px] text-slate-500">{{ $t('super.under_trial') }}</div>
          </div>

          <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-1">
            <span class="text-rose-400 text-xs font-bold">{{ $t('super.suspended_tenants') }}</span>
            <div class="text-2xl font-black text-rose-400 font-mono">{{ metrics.suspended_tenants || 0 }}</div>
            <div class="text-[10px] text-slate-500">{{ $t('super.suspended_or_expired') }}</div>
          </div>

          <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-1 sm:col-span-2 lg:col-span-1">
            <span class="text-purple-400 text-xs font-bold">{{ $t('super.mrr') }}</span>
            <div class="text-2xl font-black text-purple-400 font-mono">{{ formatMoney(metrics.mrr || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span></div>
            <div class="text-[10px] text-slate-500">Monthly Recurring Revenue</div>
          </div>
        </div>

        <!-- Plans Distribution & Recent Tenants (2 Cols) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Plans Distribution (Col 1) -->
          <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
              <h2 class="text-xs font-bold text-slate-300 flex items-center gap-2">
                <Layers class="w-4 h-4 text-amber-400" />
                <span>{{ $t('super.tenants_distribution') }}</span>
              </h2>
            </div>

            <div v-if="planStats.length > 0" class="space-y-3">
              <div
                v-for="p in planStats"
                :key="p.id"
                class="p-3 bg-slate-900/60 border border-slate-800 rounded-xl flex items-center justify-between text-xs"
              >
                <div>
                  <div class="font-bold text-slate-900 dark:text-white">{{ p.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono">{{ $t('super.monthly_rate', { amount: formatMoney(p.price_monthly) }) }}</div>
                </div>
                <div class="text-end font-mono">
                  <span class="px-2.5 py-1 bg-purple-500/10 border border-purple-500/30 text-purple-400 rounded-full font-bold">
                    {{ $t('super.subscribers_count', { count: p.tenants_count }) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Tenants (Col 2-3) -->
          <div class="lg:col-span-2 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
              <h2 class="text-xs font-bold text-slate-300 flex items-center gap-2">
                <Building2 class="w-4 h-4 text-purple-400" />
                <span>{{ $t('super.recent_tenants') }}</span>
              </h2>
              <router-link to="/super-admin/tenants" class="text-[11px] text-amber-400 hover:underline">
                {{ $t('super.view_all_tenants_link') }}
              </router-link>
            </div>

            <div v-if="recentTenants.length > 0" class="space-y-2">
              <div
                v-for="t in recentTenants"
                :key="t.id"
                class="p-3 bg-slate-900/60 border border-slate-800 rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs"
              >
                <div class="space-y-0.5">
                  <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>{{ t.name }}</span>
                    <span class="text-[10px] text-slate-400 font-mono">({{ t.domain }})</span>
                  </div>
                  <div class="text-[10px] text-slate-400">{{ $t('super.subscribed_plan_col') }}: <span class="text-amber-400 font-bold">{{ t.plan_name }}</span></div>
                </div>

                <div class="flex items-center gap-3 self-end sm:self-center">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="getStatusBadgeClass(t.status)"
                  >
                    {{ getStatusLabel(t.status) }}
                  </span>
                  <span class="text-[10px] text-slate-500 font-mono">{{ t.created_at }}</span>
                </div>
              </div>
            </div>

            <div v-else class="p-8 text-center text-xs text-slate-500">
              {{ $t('super.no_tenants_registered') }}
            </div>
          </div>
        </div>

        <!-- Central Platform Whitelabel & Branding Card -->
        <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 sm:p-6 shadow-lg space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
                <Sliders class="w-4 h-4" />
              </div>
              <div>
                <h2 class="text-sm font-black text-white">{{ $t('super.platform_settings_title') }}</h2>
                <p class="text-[11px] text-slate-400 font-medium">{{ $t('super.platform_settings_subtitle') }}</p>
              </div>
            </div>
          </div>

          <!-- Settings Form -->
          <form @submit.prevent="savePlatformSettings" class="space-y-4 pt-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Platform Name -->
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-300">
                  {{ $t('super.platform_name_label') }}
                </label>
                <input
                  v-model="platformSettings.platform_name"
                  type="text"
                  required
                  class="w-full h-11 px-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  :placeholder="$t('super.platform_name_placeholder')"
                >
              </div>

              <!-- Platform Subtitle -->
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-300">
                  {{ $t('super.platform_subtitle_label') }}
                </label>
                <input
                  v-model="platformSettings.platform_subtitle"
                  type="text"
                  class="w-full h-11 px-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  :placeholder="$t('super.platform_subtitle_placeholder')"
                >
              </div>

              <!-- Support Email -->
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-300">
                  {{ $t('super.support_email_label') }}
                </label>
                <input
                  v-model="platformSettings.support_email"
                  type="email"
                  dir="ltr"
                  class="w-full h-11 px-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-mono text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  placeholder="support@domain.com"
                >
              </div>

              <!-- Support Phone -->
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-300">
                  {{ $t('super.support_phone_label') }}
                </label>
                <input
                  v-model="platformSettings.support_phone"
                  type="text"
                  dir="ltr"
                  class="w-full h-11 px-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-mono text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  placeholder="010XXXXXXXX"
                >
              </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-800/80">
              <span v-if="saveSuccessMessage" class="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                <span>✓</span> {{ saveSuccessMessage }}
              </span>
              <span v-else></span>

              <button
                type="submit"
                :disabled="isSavingSettings"
                class="px-5 py-2.5 bg-theme-gradient text-white font-black shadow-theme-primary text-xs rounded-xl shadow-lg shadow-amber-500/20 flex items-center gap-2 transition cursor-pointer disabled:opacity-50"
              >
                <span>{{ isSavingSettings ? $t('super.saving_platform_settings') : $t('super.save_platform_settings_btn') }}</span>
              </button>
            </div>
          </form>
        </div>

        <!-- 🖥️ Central Server & System Information Section -->
        <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-lg space-y-4">
          <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <h2 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-500 flex items-center justify-center">
                <Cpu class="w-4.5 h-4.5" />
              </div>
              <span>معلومات بيئة السيرفر والنظام المركزي (Central Server Specs)</span>
            </h2>
            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 text-[10px] font-bold">
              ● متصل ويعمل بكفاءة
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center">
                  <Code2 class="w-4 h-4" />
                </div>
                <div>
                  <span class="text-slate-400 text-[11px] block">إصدار PHP</span>
                  <span class="text-slate-900 dark:text-white font-bold font-mono text-xs">{{ systemInfo.php_version || '8.3+' }}</span>
                </div>
              </div>
              <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-500 text-[10px] font-bold">Active</span>
            </div>

            <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-500 flex items-center justify-center">
                  <Layers class="w-4 h-4" />
                </div>
                <div>
                  <span class="text-slate-400 text-[11px] block">إصدار Laravel</span>
                  <span class="text-slate-900 dark:text-white font-bold font-mono text-xs">{{ systemInfo.laravel_version || '11.x' }}</span>
                </div>
              </div>
              <span class="px-2 py-0.5 rounded bg-purple-500/10 text-purple-500 text-[10px] font-bold">SaaS</span>
            </div>

            <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-500 flex items-center justify-center">
                  <Database class="w-4 h-4" />
                </div>
                <div>
                  <span class="text-slate-400 text-[11px] block">قاعدة البيانات (DB Engine)</span>
                  <span class="text-cyan-600 dark:text-cyan-400 font-bold font-mono text-xs">{{ systemInfo.db_driver || 'MySQL' }} ({{ systemInfo.mysql_version || '8.0' }})</span>
                </div>
              </div>
            </div>

            <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center">
                  <Server class="w-4 h-4" />
                </div>
                <div>
                  <span class="text-slate-400 text-[11px] block">بيئة التشغيل (Environment)</span>
                  <span class="text-amber-500 font-bold font-mono text-xs">{{ systemInfo.environment || 'Production' }}</span>
                </div>
              </div>
              <span class="px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-mono font-bold">LIVE</span>
            </div>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import { trans } from '../../helpers/trans';
import { useAppConfigStore } from '../../stores/appConfig';
import {
    Crown,
    Building2,
    Layers,
    Sliders,
    Cpu,
    Code2,
    Server,
    Database
} from 'lucide-vue-next';

const appConfigStore = useAppConfigStore();
const metrics = ref({});
const planStats = ref([]);
const recentTenants = ref([]);
const systemInfo = ref({});
const isLoading = ref(false);

const platformSettings = ref({
    platform_name: '',
    platform_subtitle: '',
    support_email: '',
    support_phone: ''
});
const isSavingSettings = ref(false);
const saveSuccessMessage = ref('');

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'active': return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
        case 'trial': return 'bg-amber-500/10 border-amber-500/30 text-amber-400';
        case 'suspended': return 'bg-rose-500/10 border-rose-500/30 text-rose-400';
        default: return 'bg-slate-500/10 border-slate-500/30 text-slate-400';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'active': return trans('super.status_active_badge');
        case 'trial': return trans('super.status_trial_badge');
        case 'suspended': return trans('super.status_suspended_badge');
        case 'expired': return trans('super.status_expired_badge');
        default: return status;
    }
};

const fetchDashboard = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/super-admin/dashboard');
        metrics.value = res.data?.metrics || {};
        planStats.value = res.data?.plan_stats || [];
        recentTenants.value = res.data?.recent_tenants || [];
        systemInfo.value = res.data?.system_info || {};
    } catch (e) {
        console.error('Failed to load super admin dashboard:', e);
    } finally {
        isLoading.value = false;
    }
};

const fetchPlatformSettings = async () => {
    try {
        const res = await api.get('/super-admin/settings');
        if (res.data?.data) {
            platformSettings.value = {
                platform_name: res.data.data.platform_name || '',
                platform_subtitle: res.data.data.platform_subtitle || '',
                support_email: res.data.data.support_email || '',
                support_phone: res.data.data.support_phone || ''
            };
        }
    } catch (e) {
        console.error('Failed to load platform settings:', e);
    }
};

const savePlatformSettings = async () => {
    isSavingSettings.value = true;
    saveSuccessMessage.value = '';
    try {
        const res = await api.post('/super-admin/settings', platformSettings.value);
        if (res.data?.success) {
            saveSuccessMessage.value = res.data.message || trans('super.platform_settings_saved_success');
            // Update global Pinia store immediately
            appConfigStore.system.platform_name = platformSettings.value.platform_name;
            appConfigStore.system.company_subtitle = platformSettings.value.platform_subtitle;
            const appName = platformSettings.value.platform_name;
            document.title = `${trans('super.super_admin_title')} - ${appName}`;
            setTimeout(() => {
                saveSuccessMessage.value = '';
            }, 4000);
        }
    } catch (e) {
        console.error('Failed to save platform settings:', e);
    } finally {
        isSavingSettings.value = false;
    }
};

onMounted(() => {
    fetchDashboard();
    fetchPlatformSettings();
});
</script>
