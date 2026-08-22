<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
            <Activity class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ $t('activity.title') }}</h1>
            <p class="text-xs text-slate-400">{{ $t('activity.subtitle') }}</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="fetchLogs"
            class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-bold text-xs rounded-xl shadow flex items-center gap-2 transition cursor-pointer"
          >
            <RefreshCw class="w-4 h-4 text-amber-400" :class="{ 'animate-spin': isLoading }" />
            <span>{{ $t('activity.refresh_log') }}</span>
          </button>
        </div>
      </div>

      <!-- 4 Stats Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-1">
          <div class="text-slate-400 text-xs font-bold">{{ $t('activity.today_total') }}</div>
          <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ stats.today_total || 0 }}</div>
          <div class="text-[10px] text-slate-500">{{ $t('activity.sub_total_desc') }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-1">
          <div class="text-rose-400 text-xs font-bold">{{ $t('activity.today_critical') }}</div>
          <div class="text-2xl font-black text-rose-400 font-mono">{{ stats.today_critical || 0 }}</div>
          <div class="text-[10px] text-slate-500">{{ $t('activity.sub_critical_desc') }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-1">
          <div class="text-amber-400 text-xs font-bold">{{ $t('activity.today_users') }}</div>
          <div class="text-2xl font-black text-amber-400 font-mono">{{ stats.today_users || 0 }}</div>
          <div class="text-[10px] text-slate-500">{{ $t('activity.sub_users_desc') }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-1">
          <div class="text-cyan-400 text-xs font-bold">{{ $t('activity.today_stores') }}</div>
          <div class="text-2xl font-black text-cyan-400 font-mono">{{ stats.today_stores || 0 }}</div>
          <div class="text-[10px] text-slate-500">{{ $t('activity.sub_stores_desc') }}</div>
        </div>
      </div>

      <!-- Filter Controls -->
      <div class="p-4 bg-slate-950/80 rounded-2xl border border-slate-800 shadow-lg space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <!-- Search -->
          <div class="relative">
            <Search class="w-4 h-4 text-slate-400 absolute start-3 top-2.5" />
            <input
              v-model="filters.search"
              @input="debouncedFetch"
              type="text"
              :placeholder="$t('activity.search_placeholder')"
              class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl ps-9 pe-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-theme-primary"
            />
          </div>

          <!-- Module -->
          <select
            v-model="filters.module"
            @change="fetchLogs"
            class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-theme-primary"
          >
            <option value="all">{{ $t('activity.all_modules') }}</option>
            <option v-for="(label, key) in modulesList" :key="key" :value="key">{{ label }}</option>
          </select>

          <!-- User -->
          <select
            v-model="filters.user_id"
            @change="fetchLogs"
            class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-theme-primary"
          >
            <option value="all">{{ $t('activity.all_users') }}</option>
            <option v-for="u in usersList" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>

          <!-- Store -->
          <select
            v-model="filters.store_id"
            @change="fetchLogs"
            class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-theme-primary"
          >
            <option value="all">{{ $t('activity.all_stores') }}</option>
            <option v-for="st in storesList" :key="st.id" :value="st.id">{{ st.name }}</option>
          </select>
        </div>
      </div>

      <!-- Logs Timeline / List -->
      <div class="bg-slate-950/80 rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
        <div v-if="isLoading" class="p-16 text-center">
          <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-xs text-slate-400">{{ $t('activity.loading_logs') }}</p>
        </div>

        <div v-else-if="logs.length === 0" class="p-16 text-center">
          <Activity class="w-12 h-12 text-slate-600 mx-auto mb-3" />
          <h3 class="text-sm font-bold text-slate-300 mb-1">{{ $t('activity.no_logs_match') }}</h3>
          <p class="text-xs text-slate-500">{{ $t('activity.adjust_filter_hint') }}</p>
        </div>

        <div v-else class="divide-y divide-slate-200 dark:divide-slate-800/60">
          <div
            v-for="log in logs"
            :key="log.id"
            class="p-4 hover:bg-slate-100 dark:hover:bg-slate-900/40 transition flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs"
          >
            <div class="flex items-start gap-3">
              <span class="text-xl shrink-0 mt-0.5">{{ log.module_icon || '📋' }}</span>
              <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="getActionBadgeClass(log.action)"
                  >
                    {{ log.action }}
                  </span>
                  <span class="font-bold text-slate-900 dark:text-white">{{ log.description }}</span>
                </div>

                <div class="flex items-center gap-3 text-[11px] text-slate-400 flex-wrap font-sans">
                  <span>{{ $t('activity.staff_label') }} <strong class="text-slate-300">{{ log.user_name }}</strong></span>
                  <span>{{ $t('activity.branch_label') }} <strong class="text-slate-300">{{ log.store_name }}</strong></span>
                  <span v-if="log.ip_address" class="font-mono text-slate-500">{{ $t('activity.ip_address') }}: {{ log.ip_address }}</span>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-3 self-end md:self-center font-mono text-[11px]">
              <span class="text-slate-400">{{ log.created_at }}</span>
              <button
                v-if="log.properties || log.payload"
                @click="openDetails(log)"
                class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 rounded-lg text-amber-400 font-sans font-bold transition"
              >
                {{ $t('activity.details_btn') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400 font-mono">
          <span>{{ $t('activity.total_records') }} {{ pagination.total }}</span>
          <div class="flex items-center gap-2 font-sans">
            <button
              :disabled="pagination.current_page === 1"
              @click="changePage(pagination.current_page - 1)"
              class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 border border-slate-700 rounded-xl"
            >
              {{ $t('common.previous') }}
            </button>
            <span>{{ $t('pagination.page_of', { page: pagination.current_page, total: pagination.last_page }) || `صفحة ${pagination.current_page} من ${pagination.last_page}` }}</span>
            <button
              :disabled="pagination.current_page === pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
              class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 border border-slate-700 rounded-xl"
            >
              {{ $t('common.next') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Payload Details Modal -->
      <div v-if="selectedLog" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h2 class="text-base font-black text-white flex items-center gap-2">
              <Activity class="w-4 h-4 text-cyan-400" />
              <span>{{ $t('activity.log_details_title', { id: selectedLog.id }) }}</span>
            </h2>
            <button @click="selectedLog = null" class="text-slate-400 hover:text-white">✕</button>
          </div>

          <div class="space-y-3 text-xs">
            <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
              <div class="text-slate-400 font-bold mb-1">{{ $t('activity.full_description') }}</div>
              <div class="text-white">{{ selectedLog.description }}</div>
            </div>

            <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800 font-mono text-[11px] overflow-x-auto max-h-60">
              <pre class="text-emerald-400">{{ JSON.stringify(selectedLog.properties || selectedLog.payload, null, 2) }}</pre>
            </div>
          </div>

          <div class="flex justify-end pt-2 border-t border-slate-800">
            <button
              @click="selectedLog = null"
              class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded-xl font-bold text-xs"
            >
              {{ $t('common.close') }}
            </button>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import {
    Activity,
    Search,
    RefreshCw
} from 'lucide-vue-next';

const logs = ref([]);
const stats = ref({});
const usersList = ref([]);
const storesList = ref([]);
const modulesList = ref({});
const isLoading = ref(false);
const selectedLog = ref(null);

const filters = ref({
    search: '',
    module: 'all',
    action: 'all',
    user_id: 'all',
    store_id: 'all',
    page: 1,
});

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchLogs();
    }, 300);
};

const fetchLogs = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/activity-logs', { params: filters.value });
        logs.value = res.data?.data || [];
        stats.value = res.data?.stats || {};
        usersList.value = res.data?.users || [];
        storesList.value = res.data?.stores || [];
        modulesList.value = res.data?.modules_list || {};
        pagination.value = res.data?.pagination || pagination.value;
    } catch (e) {
        console.error('Failed to fetch activity logs:', e);
    } finally {
        isLoading.value = false;
    }
};

const changePage = (page) => {
    filters.value.page = page;
    fetchLogs();
};

const openDetails = (log) => {
    selectedLog.value = log;
};

const getActionBadgeClass = (action) => {
    if (['deleted', 'cancelled', 'login_failed'].includes(action)) {
        return 'bg-rose-500/10 border-rose-500/30 text-rose-400';
    }
    if (['created', 'invoice_created', 'login_success'].includes(action)) {
        return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
    }
    if (['updated', 'shift_opened', 'shift_closed'].includes(action)) {
        return 'bg-amber-500/10 border-amber-500/30 text-amber-400';
    }
    return 'bg-slate-500/10 border-slate-500/30 text-slate-400';
};

onMounted(() => {
    fetchLogs();
});
</script>
