<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('returns.title')"
        :subtitle="$t('returns.subtitle')"
        :icon="'🔄'"
      >
        <template #actions>
          <router-link
            to="/returns/create"
            class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-amber-500/20"
          >
            <Plus class="w-4 h-4" />
            <span>تسجيل مرتجع جديد</span>
          </router-link>
        </template>
      </PageHeader>

      <!-- Financial Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <!-- Total Returns Value -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">إجمالي قيمة المرتجعات</span>
            <TrendingDown class="w-4 h-4 text-rose-400" />
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ formatMoney(summary.total_value || 0) }} <span class="text-xs text-slate-400">ج.م</span>
          </div>
          <span class="text-[10px] text-slate-500">قيمة كافة المرتجعات المسجلة</span>
        </div>

        <!-- Sales Returns Count -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">مرتجعات مبيعات (من عملاء)</span>
            <RotateCcw class="w-4 h-4 text-cyan-400" />
          </div>
          <div class="text-2xl font-black text-cyan-400 font-mono">
            {{ summary.sales_count || 0 }} <span class="text-xs text-slate-400">مستند</span>
          </div>
          <span class="text-[10px] text-slate-500">بضاعة مسترجعة للمخازن</span>
        </div>

        <!-- Purchase Returns Count -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">مرتجعات مشتريات (لموردين)</span>
            <RotateCw class="w-4 h-4 text-amber-400" />
          </div>
          <div class="text-2xl font-black text-amber-400 font-mono">
            {{ summary.purchase_count || 0 }} <span class="text-xs text-slate-400">مستند</span>
          </div>
          <span class="text-[10px] text-slate-500">بضاعة مرتجعة للموردين</span>
        </div>

        <!-- Total Count -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">إجمالي المستندات</span>
            <FileText class="w-4 h-4 text-slate-400" />
          </div>
          <div class="text-2xl font-black text-white font-mono">
            {{ summary.total_count || 0 }} <span class="text-xs text-slate-400">حركة</span>
          </div>
          <span class="text-[10px] text-slate-500">سجل عمليات الإرجاع</span>
        </div>
      </div>

      <!-- Filters & Search Bar -->
      <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="debounceSearch"
            type="text"
            class="w-full h-10 pr-9 pl-4 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none"
            placeholder="بحث برقم المرتجع، اسم العميل أو المورد، أو السبب..."
          >
          <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
        </div>

        <!-- Return Type Filter -->
        <div class="w-full md:w-48">
          <select
            v-model="selectedType"
            @change="fetchReturns(1)"
            class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
            <option value="all">كافة أنواع المرتجعات</option>
            <option value="sales_return">↩️ مرتجع مبيعات (من عميل)</option>
            <option value="purchase_return">↪️ مرتجع مشتريات (إلى مورد)</option>
          </select>
        </div>

        <!-- Date Range Filter -->
        <div class="flex items-center gap-2">
          <input
            v-model="dateFrom"
            @change="fetchReturns(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
          <span class="text-xs text-slate-500 font-bold">—</span>
          <input
            v-model="dateTo"
            @change="fetchReturns(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
        </div>
      </div>

      <!-- Returns Ledger Table -->
      <div class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="returnsList.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900/90 text-slate-400 border-b border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">رقم المستند</th>
                <th class="py-3 px-4 text-start font-bold">النوع</th>
                <th class="py-3 px-4 text-start font-bold">الطرف (العميل / المورد)</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
                <th class="py-3 px-4 text-end font-bold">قيمة المرتجع</th>
                <th class="py-3 px-4 text-start font-bold">السبب</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
              <tr
                v-for="(ret, idx) in returnsList"
                :key="ret.id"
                class="hover:bg-slate-900/50 transition-colors"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">
                  {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-amber-400">
                  {{ ret.return_number }}
                </td>
                <td class="py-3.5 px-4">
                  <span
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold border font-tajawal"
                    :class="ret.return_type === 'sales_return' ? 'bg-cyan-500/10 border-cyan-500/30 text-cyan-400' : 'bg-amber-500/10 border-amber-500/30 text-amber-400'"
                  >
                    {{ ret.return_type === 'sales_return' ? '↩️ مرتجع مبيعات' : '↪️ مرتجع مشتريات' }}
                  </span>
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-white font-tajawal">{{ ret.party_name }}</div>
                  <div v-if="ret.party_phone" class="text-[10px] text-slate-500 font-mono mt-0.5">
                    {{ ret.party_phone }}
                  </div>
                </td>
                <td class="py-3.5 px-4 font-mono text-slate-300">
                  {{ ret.return_date }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black text-rose-400 text-sm">
                  {{ formatMoney(ret.total_amount) }} ج.م
                </td>
                <td class="py-3.5 px-4 text-slate-400 font-tajawal max-w-xs truncate">
                  {{ ret.reason || '—' }}
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Preview Details Button -->
                    <button
                      type="button"
                      @click="openDetailsModal(ret)"
                      class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-900 rounded-xl transition-all cursor-pointer"
                      :title="'عرض تفاصيل المستند'"
                    >
                      <Eye class="w-4 h-4" />
                    </button>

                    <!-- Delete Button -->
                    <button
                      type="button"
                      @click="deleteReturnDoc(ret)"
                      class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer"
                      :title="'أرشفة المستند'"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else
          :title="$t('returns.no_returns_found')"
          :description="'لم يتم تسجيل أي حركات مرتجعات مبيعات أو مشتريات مطابقة للفلاتر المحددة.'"
          :icon="'🔄'"
        >
          <template #action>
            <router-link
              to="/returns/create"
              class="px-5 py-2.5 bg-amber-500 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-amber-500/20"
            >
              تسجيل أول مرتجع
            </router-link>
          </template>
        </EmptyState>

        <!-- Pagination Bar -->
        <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-800 flex items-center justify-between">
          <div class="text-xs text-slate-400">
            إجمالي النتائج: <span class="font-mono text-amber-400">{{ pagination.total }}</span> مستند
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="fetchReturns(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer"
            >
              السابق
            </button>
            <span class="px-3 py-1.5 text-xs font-mono text-slate-300 font-bold">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              type="button"
              @click="fetchReturns(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer"
            >
              التالي
            </button>
          </div>
        </div>
      </div>

      <!-- Return Details Modal -->
      <AppModal
        :show="showDetailsModal"
        :title="`تفاصيل المرتجع: ${selectedReturnDetails?.return_number || ''}`"
        @close="showDetailsModal = false"
      >
        <div v-if="selectedReturnDetails" class="space-y-4 font-tajawal text-xs">
          <!-- Header info -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-slate-900/80 border border-slate-800 rounded-2xl">
            <div>
              <span class="text-slate-400 block font-bold">النوع:</span>
              <span class="font-bold text-white">
                {{ selectedReturnDetails.return_type === 'sales_return' ? '↩️ مرتجع مبيعات' : '↪️ مرتجع مشتريات' }}
              </span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">الطرف:</span>
              <span class="text-amber-400 font-bold">{{ selectedReturnDetails.party_name }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">التاريخ:</span>
              <span class="text-slate-200 font-mono">{{ selectedReturnDetails.return_date }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">المسؤول / الفرع:</span>
              <span class="text-slate-200">{{ selectedReturnDetails.user_name }} ({{ selectedReturnDetails.store_name }})</span>
            </div>
          </div>

          <!-- Items Table -->
          <div class="border border-slate-800 rounded-xl overflow-hidden">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-900 text-slate-400 border-b border-slate-800">
                  <th class="p-2.5 text-start font-bold">الصنف</th>
                  <th class="p-2.5 text-end font-bold">الكمية المرتجعة</th>
                  <th class="p-2.5 text-end font-bold">سعر الوحدة</th>
                  <th class="p-2.5 text-end font-bold">الإجمالي</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/50">
                <tr v-for="it in selectedReturnDetails.items" :key="it.id">
                  <td class="p-2.5 font-bold text-white">{{ it.item_name }}</td>
                  <td class="p-2.5 text-end font-mono text-amber-400">{{ it.quantity }} {{ it.unit }}</td>
                  <td class="p-2.5 text-end font-mono text-slate-300">{{ formatMoney(it.unit_price) }} ج.م</td>
                  <td class="p-2.5 text-end font-mono font-bold text-rose-400">{{ formatMoney(it.total_price) }} ج.م</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Total Footer -->
          <div class="p-3.5 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between text-xs">
            <span class="font-bold text-slate-300">إجمالي قيمة المرتجع:</span>
            <span class="text-base font-black text-rose-400 font-mono">{{ formatMoney(selectedReturnDetails.total_amount) }} ج.م</span>
          </div>

          <div v-if="selectedReturnDetails.reason" class="p-3 bg-slate-900/50 border border-slate-800/80 rounded-xl text-slate-400">
            <span class="font-bold text-slate-300">سبب الإرجاع: </span>
            <span>{{ selectedReturnDetails.reason }}</span>
          </div>
        </div>
      </AppModal>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import AppModal from '../../Components/Common/AppModal.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import {
    Plus,
    Search,
    TrendingDown,
    RotateCcw,
    RotateCw,
    FileText,
    Eye,
    Trash2
} from 'lucide-vue-next';

const returnsList = ref([]);
const summary = ref({
    total_value: 0,
    sales_count: 0,
    purchase_count: 0,
    total_count: 0,
});

const searchQuery = ref('');
const selectedType = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const isLoading = ref(false);

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

let debounceTimer = null;

const showDetailsModal = ref(false);
const selectedReturnDetails = ref(null);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const fetchReturns = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get('/returns', {
            params: {
                search: searchQuery.value || undefined,
                type: selectedType.value !== 'all' ? selectedType.value : undefined,
                from_date: dateFrom.value || undefined,
                to_date: dateTo.value || undefined,
                page: page,
                per_page: 15,
            },
        });
        returnsList.value = response.data?.data || [];
        summary.value = response.data?.summary || {
            total_value: 0,
            sales_count: 0,
            purchase_count: 0,
            total_count: 0,
        };
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 15,
            total: returnsList.value.length,
        };
    } catch (error) {
        console.error('Failed to load returns:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchReturns(1);
    }, 300);
};

const openDetailsModal = async (ret) => {
    try {
        const response = await api.get(`/returns/${ret.id}`);
        selectedReturnDetails.value = response.data?.data;
        showDetailsModal.value = true;
    } catch (error) {
        console.error('Failed to load return details:', error);
    }
};

const deleteReturnDoc = async (ret) => {
    const result = await Swal.fire({
        title: `أرشفة المرتجع (${ret.return_number})؟`,
        text: 'هل أنت متأكد من رغبتك في حذف أو أرشفة هذا المستند؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، قم بالأرشفة',
        cancelButtonText: 'تراجع',
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/returns/${ret.id}`);
            Swal.fire({
                icon: 'success',
                title: 'تم الحذف',
                text: 'تم أرشفة مستند المرتجع بنجاح',
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchReturns(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: error.userMessage || 'تعذر أرشفة المستند',
            });
        }
    }
};

onMounted(() => {
    fetchReturns(1);
});
</script>
