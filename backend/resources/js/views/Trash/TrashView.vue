<template>
  <SpaLayout>
    <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
            <Trash2 class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-white">سلة المحذوفات</h1>
            <p class="text-xs text-slate-400">استرجاع أو الحذف النهائي للسجلات المحذوفة عبر كافة أقسام النظام</p>
          </div>
        </div>

        <button
          @click="fetchRecords"
          class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-bold text-xs rounded-xl shadow flex items-center gap-2 transition cursor-pointer"
        >
          <RefreshCw class="w-4 h-4 text-amber-400" :class="{ 'animate-spin': isLoading }" />
          <span>تحديث السلة</span>
        </button>
      </div>

      <!-- Module Tabs with Counts -->
      <div class="flex items-center gap-2 border-b border-slate-800 pb-2 overflow-x-auto">
        <button
          v-for="t in tabsList"
          :key="t.id"
          @click="changeTab(t.id)"
          class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap cursor-pointer"
          :class="currentTab === t.id ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
        >
          <span>{{ t.icon }}</span>
          <span>{{ t.label }}</span>
          <span
            class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
            :class="counts[t.id] > 0 ? 'bg-rose-500/20 text-rose-400' : 'bg-slate-800 text-slate-500'"
          >
            {{ counts[t.id] || 0 }}
          </span>
        </button>
      </div>

      <!-- Search Input -->
      <div class="p-4 bg-slate-950/80 rounded-2xl border border-slate-800 shadow-lg">
        <div class="relative">
          <Search class="w-4 h-4 text-slate-400 absolute start-3 top-2.5" />
          <input
            v-model="search"
            @input="debouncedFetch"
            type="text"
            placeholder="بحث في العناصر المحذوفة..."
            class="w-full bg-slate-900 border border-slate-700 rounded-xl ps-9 pe-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
          />
        </div>
      </div>

      <!-- Records Table -->
      <div class="bg-slate-950/80 rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
        <div v-if="isLoading" class="p-16 text-center">
          <div class="w-10 h-10 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-xs text-slate-400">جاري تحميل عناصر سلة المحذوفات...</p>
        </div>

        <div v-else-if="records.length === 0" class="p-16 text-center">
          <Trash2 class="w-12 h-12 text-slate-600 mx-auto mb-3" />
          <h3 class="text-sm font-bold text-slate-300 mb-1">سلة المحذوفات فارغة</h3>
          <p class="text-xs text-slate-500">لا توجد عناصر محذوفة في هذا التبويب حالياً.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-start text-xs">
            <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 font-bold">
              <tr>
                <th class="p-4 text-start">العنصر / الاسم</th>
                <th class="p-4 text-start">البيان / الرمز</th>
                <th class="p-4 text-start">تاريخ الحذف</th>
                <th class="p-4 text-end">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono">
              <tr v-for="item in records" :key="item.id" class="hover:bg-slate-900/40 transition">
                <td class="p-4 font-sans font-bold text-white">{{ item.title }}</td>
                <td class="p-4 text-slate-400 font-sans">{{ item.subtitle }}</td>
                <td class="p-4 text-slate-500 font-sans">{{ item.deleted_at }}</td>
                <td class="p-4 text-end font-sans">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click="restoreRecord(item)"
                      class="px-3 py-1.5 bg-slate-900 hover:bg-emerald-950/40 border border-slate-700 hover:border-emerald-700 text-emerald-400 rounded-lg text-xs font-bold transition flex items-center gap-1.5"
                    >
                      <RotateCcw class="w-3.5 h-3.5" />
                      <span>استرجاع</span>
                    </button>

                    <button
                      @click="forceDeleteRecord(item)"
                      class="px-3 py-1.5 bg-slate-900 hover:bg-rose-950/40 border border-slate-700 hover:border-rose-800 text-rose-400 rounded-lg text-xs font-bold transition flex items-center gap-1.5"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                      <span>حذف نهائي</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400 font-mono">
          <span>إجمالي المحذوفات: {{ pagination.total }}</span>
          <div class="flex items-center gap-2 font-sans">
            <button
              :disabled="pagination.current_page === 1"
              @click="changePage(pagination.current_page - 1)"
              class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 border border-slate-700 rounded-xl"
            >
              السابق
            </button>
            <span>صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
            <button
              :disabled="pagination.current_page === pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
              class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 border border-slate-700 rounded-xl"
            >
              التالي
            </button>
          </div>
        </div>
      </div>
    </div>
  </SpaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import SpaLayout from '../../Layouts/SpaLayout.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
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

const tabsList = [
    { id: 'items', label: 'الأصناف والخامات', icon: '📦' },
    { id: 'customers', label: 'العملاء', icon: '👥' },
    { id: 'suppliers', label: 'الموردين', icon: '🏭' },
    { id: 'stores', label: 'الفروع والمخازن', icon: '🏬' },
    { id: 'expenses', label: 'المصروفات', icon: '💸' },
    { id: 'returns', label: 'المرتجعات', icon: '🔄' },
];

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
        title: `استرجاع "${item.title}"؟`,
        text: 'سيتم استرجاع السجل وإعادته إلى قائمة السجلات النشطة.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#334155',
        confirmButtonText: 'نعم، استرجع',
        cancelButtonText: 'إلغاء',
    });

    if (result.isConfirmed) {
        try {
            await api.post(`/trash/${currentTab.value}/${item.id}/restore`);
            Swal.fire({ icon: 'success', title: 'تم الاسترجاع', timer: 1500, showConfirmButton: false });
            fetchRecords();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'خطأ', text: e.response?.data?.message || 'تعذر استرجاع السجل' });
        }
    }
};

const forceDeleteRecord = async (item) => {
    const result = await Swal.fire({
        title: `حذف نهائي لـ "${item.title}"؟`,
        text: 'تحذير: الحذف النهائي لا يمكن التراجع عنه مطلقاً!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#334155',
        confirmButtonText: 'نعم، احذف نهائياً',
        cancelButtonText: 'إلغاء',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/trash/${currentTab.value}/${item.id}/force`);
            Swal.fire({ icon: 'success', title: 'تم الحذف النهائي', timer: 1500, showConfirmButton: false });
            fetchRecords();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'خطأ', text: e.response?.data?.message || 'تعذر حذف السجل' });
        }
    }
};

onMounted(() => {
    fetchRecords();
});
</script>
