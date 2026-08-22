<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/90 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
            <Trash2 class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ $t('trash.trash_title') }}</h1>
            <p class="text-xs text-slate-400">{{ $t('trash.trash_subtitle') }}</p>
          </div>
        </div>

        <button
          @click="fetchRecords"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-xl shadow-xs flex items-center gap-2 transition cursor-pointer"
        >
          <RefreshCw class="w-4 h-4 text-theme-primary" :class="{ 'animate-spin': isLoading }" />
          <span>{{ $t('trash.refresh_trash') }}</span>
        </button>
      </div>

      <!-- Module Tabs with Counts -->
      <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2 overflow-x-auto">
        <button
          v-for="t in tabsList"
          :key="t.id"
          @click="changeTab(t.id)"
          class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap cursor-pointer font-tajawal"
          :class="currentTab === t.id ? 'bg-theme-primary text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-900 dark:text-slate-200 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800'"
        >
          <span>{{ t.icon }}</span>
          <span>{{ t.label }}</span>
          <span
            class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
            :class="currentTab === t.id ? 'bg-white/25 text-white' : (counts[t.id] > 0 ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400')"
          >
            {{ counts[t.id] || 0 }}
          </span>
        </button>
      </div>

      <!-- Search Input -->
      <div class="p-4 bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-lg">
        <div class="relative">
          <Search class="w-4 h-4 text-slate-400 absolute start-3 top-2.5" />
          <input
            v-model="search"
            @input="debouncedFetch"
            type="text"
            :placeholder="$t('trash.search_trash_placeholder')"
            class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl ps-9 pe-3 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-theme-primary font-tajawal"
          />
        </div>
      </div>

      <!-- Records Table -->
      <div class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden">
        <div v-if="isLoading" class="p-16 text-center">
          <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('trash.loading_trash') }}</p>
        </div>

        <div v-else-if="records.length === 0" class="p-16 text-center">
          <Trash2 class="w-12 h-12 text-slate-600 mx-auto mb-3" />
          <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1 font-tajawal">{{ $t('trash.empty_trash_title') }}</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('trash.empty_trash_desc') }}</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-start text-xs">
            <thead class="bg-slate-100/90 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-400 font-bold font-tajawal">
              <tr>
                <th class="p-4 text-start">{{ $t('trash.item_name_col') }}</th>
                <th class="p-4 text-start">{{ $t('trash.description_code_col') }}</th>
                <th class="p-4 text-start">{{ $t('trash.deleted_at_col') }}</th>
                <th class="p-4 text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-mono">
              <tr v-for="item in records" :key="item.id" class="hover:bg-slate-100 dark:hover:bg-slate-900/40 transition">
                <td class="p-4 font-sans font-bold text-slate-900 dark:text-white font-tajawal">{{ item.title }}</td>
                <td class="p-4 text-slate-400 font-sans font-tajawal">{{ item.subtitle }}</td>
                <td class="p-4 text-slate-500 font-sans">{{ item.deleted_at }}</td>
                <td class="p-4 text-end font-sans">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click="restoreRecord(item)"
                      class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 dark:bg-slate-900 dark:hover:bg-emerald-950/40 border border-slate-300 dark:border-slate-700 text-emerald-600 dark:text-emerald-400 rounded-lg text-xs font-bold transition flex items-center gap-1.5 font-tajawal cursor-pointer"
                    >
                      <RotateCcw class="w-3.5 h-3.5" />
                      <span>{{ $t('common.restore') }}</span>
                    </button>

                    <button
                      @click="forceDeleteRecord(item)"
                      class="px-3 py-1.5 bg-slate-100 hover:bg-rose-50 dark:bg-slate-900 dark:hover:bg-rose-950/40 border border-slate-300 dark:border-slate-700 text-rose-600 dark:text-rose-400 rounded-lg text-xs font-bold transition flex items-center gap-1.5 font-tajawal cursor-pointer"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                      <span>{{ $t('common.force_delete') }}</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-400 font-mono">
          <span class="font-tajawal">{{ $t('trash.total_deleted_items', { count: pagination.total }) }}</span>
          <div class="flex items-center gap-2 font-sans font-tajawal">
            <button
              :disabled="pagination.current_page === 1"
              @click="changePage(pagination.current_page - 1)"
              class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 disabled:opacity-50 border border-slate-300 dark:border-slate-700 rounded-xl cursor-pointer"
            >
              {{ $t('common.previous') }}
            </button>
            <span class="font-mono">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button
              :disabled="pagination.current_page === pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
              class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 disabled:opacity-50 border border-slate-300 dark:border-slate-700 rounded-xl cursor-pointer"
            >
              {{ $t('common.next') }}
            </button>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    Trash2,
    RotateCcw,
    Search,
    RefreshCw
} from 'lucide-vue-next';

const currentTab = ref('items');
const search = ref('');
const records = ref([]);
const counts = ref({});
const isLoading = ref(false);

const tabsList = computed(() => [
    { id: 'items', label: trans('trash.tab_items_label'), icon: '📦' },
    { id: 'customers', label: trans('trash.tab_customers_label'), icon: '👥' },
    { id: 'suppliers', label: trans('trash.tab_suppliers_label'), icon: '🏭' },
    { id: 'stores', label: trans('trash.tab_stores_label'), icon: '🏬' },
    { id: 'expenses', label: trans('trash.tab_expenses_label'), icon: '💸' },
    { id: 'returns', label: trans('trash.tab_returns_label'), icon: '🔄' },
]);

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        pagination.value.current_page = 1;
        fetchRecords();
    }, 300);
};

const changeTab = (tab) => {
    currentTab.value = tab;
    search.value = '';
    pagination.value.current_page = 1;
    fetchRecords();
};

const fetchRecords = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/trash', {
            params: {
                tab: currentTab.value,
                search: search.value,
                page: pagination.value.current_page,
            },
        });
        records.value = res.data?.data || [];
        counts.value = res.data?.counts || {};
        pagination.value = res.data?.pagination || pagination.value;
    } catch (e) {
        console.error('Failed to load trash records:', e);
    } finally {
        isLoading.value = false;
    }
};

const changePage = (page) => {
    pagination.value.current_page = page;
    fetchRecords();
};

const restoreRecord = async (item) => {
    const result = await Swal.fire({
        title: trans('trash.restore_record_confirm_title', { title: item.title }),
        text: trans('trash.restore_record_confirm_text'),
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#334155',
        confirmButtonText: trans('common.yes'),
        cancelButtonText: trans('common.cancel'),
    });

    if (result.isConfirmed) {
        try {
            await api.post(`/trash/${currentTab.value}/${item.id}/restore`);
            Swal.fire({ icon: 'success', title: trans('trash.restore_success'), timer: 1500, showConfirmButton: false });
            fetchRecords();
        } catch (e) {
            Swal.fire({ icon: 'error', title: trans('common.error'), text: e.response?.data?.message || trans('trash.restore_failed') });
        }
    }
};

const forceDeleteRecord = async (item) => {
    const result = await Swal.fire({
        title: trans('trash.force_delete_confirm_title', { title: item.title }),
        text: trans('trash.force_delete_confirm_text'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#334155',
        confirmButtonText: trans('common.yes'),
        cancelButtonText: trans('common.cancel'),
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/trash/${currentTab.value}/${item.id}/force`);
            Swal.fire({ icon: 'success', title: trans('trash.force_delete_success'), timer: 1500, showConfirmButton: false });
            fetchRecords();
        } catch (e) {
            Swal.fire({ icon: 'error', title: trans('common.error'), text: e.response?.data?.message || trans('trash.force_delete_failed') });
        }
    }
};

onMounted(() => {
    fetchRecords();
});
</script>
