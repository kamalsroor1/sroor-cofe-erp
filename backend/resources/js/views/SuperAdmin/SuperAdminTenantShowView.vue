<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Loading State -->
    <div v-if="isLoading" class="p-20 text-center bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl">
      <div class="w-12 h-12 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
      <p class="text-sm font-bold text-slate-700 dark:text-slate-300">جاري تحميل بيانات المستأجر وإحصائيات المنشأة...</p>
    </div>

    <template v-else-if="tenant">
      <!-- Executive Header -->
      <div class="bg-white dark:bg-slate-900/90 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
          <router-link
            to="/super-admin/tenants"
            class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center transition cursor-pointer shrink-0"
            title="رجوع للمستأجرين"
          >
            ←
          </router-link>

          <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white font-black text-2xl flex items-center justify-center shadow-lg shadow-purple-500/20 shrink-0">
            🏪
          </div>

          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-black text-slate-900 dark:text-white">{{ tenant.name }}</h1>
              <span
                class="px-3 py-0.5 rounded-full text-xs font-black border"
                :class="getStatusBadgeClass(tenant.status)"
              >
                {{ getStatusLabel(tenant.status) }}
              </span>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400 mt-1 font-mono">
              <span>المعرف: <strong class="text-purple-600 dark:text-purple-400">{{ tenant.id }}</strong></span>
              <span>•</span>
              <a :href="`http://${tenant.domain}`" target="_blank" class="text-cyan-600 dark:text-cyan-400 hover:underline flex items-center gap-1 font-bold">
                <span>{{ tenant.domain }}</span>
                <span>↗</span>
              </a>
              <span>•</span>
              <span>الباقة: <strong class="text-amber-500">{{ tenant.plan?.name || 'مخصصة' }}</strong></span>
            </div>
          </div>
        </div>

        <!-- Action Buttons Toolbar -->
        <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
          <button
            @click="impersonateTenant"
            :disabled="isImpersonating"
            class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs rounded-xl shadow-lg shadow-emerald-500/20 flex items-center gap-1.5 transition cursor-pointer active:scale-95 disabled:opacity-50"
          >
            <span>🚀</span>
            <span>{{ isImpersonating ? 'جاري التحويل...' : 'دخول كأدمن' }}</span>
          </button>

          <button
            @click="showStatusModal = true"
            class="px-4 py-2.5 bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-xl shadow-xs flex items-center gap-1.5 transition cursor-pointer"
          >
            <span>⚙️</span>
            <span>الحالة والاشتراك</span>
          </button>

          <button
            @click="runMigrations"
            :disabled="isMigrating"
            class="px-4 py-2.5 bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 text-indigo-600 dark:text-indigo-400 border border-indigo-300 dark:border-indigo-800 font-bold text-xs rounded-xl shadow-xs flex items-center gap-1.5 transition cursor-pointer disabled:opacity-50"
          >
            <span>🗄️</span>
            <span>{{ isMigrating ? 'جاري التحديث...' : 'تحديث الميجريشن' }}</span>
          </button>

          <button
            @click="deleteTenant"
            class="p-2.5 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-500 rounded-xl transition cursor-pointer"
            title="حذف المستأجر"
          >
            🗑️
          </button>
        </div>
      </div>

      <!-- Live Operational Stats Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
            <span>المستخدمين</span>
            <span class="text-base">👥</span>
          </div>
          <div class="text-2xl font-black text-slate-900 dark:text-white font-mono mt-3">
            {{ stats.users_count || 0 }}
          </div>
          <div class="text-[10px] text-slate-400 mt-1 font-sans">مستخدمين نشطين</div>
        </div>

        <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
            <span>الفروع والمخازن</span>
            <span class="text-base">🏪</span>
          </div>
          <div class="text-2xl font-black text-cyan-600 dark:text-cyan-400 font-mono mt-3">
            {{ stats.stores_count || 0 }}
          </div>
          <div class="text-[10px] text-slate-400 mt-1 font-sans">فروع تشغيلية</div>
        </div>

        <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
            <span>الأصناف</span>
            <span class="text-base">📦</span>
          </div>
          <div class="text-2xl font-black text-purple-600 dark:text-purple-400 font-mono mt-3">
            {{ stats.items_count || 0 }}
          </div>
          <div class="text-[10px] text-slate-400 mt-1 font-sans">أصناف مسجلة بالمخزون</div>
        </div>

        <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
            <span>فواتير المبيعات</span>
            <span class="text-base">🧾</span>
          </div>
          <div class="text-2xl font-black text-amber-500 font-mono mt-3">
            {{ stats.invoices_count || 0 }}
          </div>
          <div class="text-[10px] text-slate-400 mt-1 font-sans">فاتورة صادرة</div>
        </div>

        <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between col-span-2 sm:col-span-1">
          <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
            <span>إجمالي المبيعات</span>
            <span class="text-base">💰</span>
          </div>
          <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-3 truncate">
            {{ formatMoney(stats.total_sales) }} <span class="text-xs font-sans">ج.م</span>
          </div>
          <div class="text-[10px] text-slate-400 mt-1 font-sans">حجم العمليات الإجمالي</div>
        </div>
      </div>

      <!-- Main Columns Grid: Units Customization & Features Overrides -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- ⚖️ Left Column: Allowed Units for This Tenant (6 Cols) -->
        <div class="lg:col-span-6 bg-white dark:bg-slate-900/90 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl space-y-6">
          <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-2xl bg-purple-500/10 text-purple-400 flex items-center justify-center text-xl">
                ⚖️
              </div>
              <div>
                <h2 class="text-base font-black text-slate-900 dark:text-white">وحدات القياس المعتمدة للمستأجر</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">حدد وحدات القياس المتاحة للاستخدام في حساب هذا المستأجر</p>
              </div>
            </div>

            <button
              @click="saveTenantUnits"
              :disabled="isSavingUnits"
              class="px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-md cursor-pointer transition active:scale-95 disabled:opacity-50"
            >
              <span>{{ isSavingUnits ? 'جاري الحفظ...' : 'حفظ الوحدات للمستأجر' }}</span>
            </button>
          </div>

          <!-- Active Units Badges -->
          <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
              الوحدات النشطة حالياً في حساب ({{ tenant.name }}):
            </label>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="(u, idx) in tenantAllowedUnits"
                :key="u"
                class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-purple-500/10 dark:bg-purple-950/40 border border-purple-500/30 text-purple-700 dark:text-purple-300 flex items-center gap-2 shadow-2xs"
              >
                <span>{{ u }}</span>
                <button
                  type="button"
                  @click="removeTenantUnit(idx)"
                  class="hover:text-rose-500 transition cursor-pointer text-xs"
                  title="حذف"
                >
                  ✕
                </button>
              </span>
            </div>
          </div>

          <!-- Quick Add from System Presets -->
          <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-3">
            <div class="text-xs font-bold text-slate-700 dark:text-slate-300">إضافة وحدات من قائمة النظام:</div>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="gu in globalUnitsList"
                :key="gu"
                type="button"
                @click="addTenantUnit(gu)"
                :disabled="tenantAllowedUnits.includes(gu)"
                class="px-2.5 py-1 rounded-lg border text-[11px] font-bold transition cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                :class="tenantAllowedUnits.includes(gu) ? 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-400' : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-purple-500'"
              >
                + {{ gu }}
              </button>
            </div>
          </div>

          <!-- Add Custom Unit for this Tenant -->
          <div class="flex items-center gap-2">
            <input
              v-model="customTenantUnit"
              @keyup.enter="addCustomUnitDirect"
              type="text"
              placeholder="إضافة وحدة مخصصة أخرى..."
              class="flex-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
            />
            <button
              type="button"
              @click="addCustomUnitDirect"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition cursor-pointer"
            >
              + إضافة
            </button>
          </div>
        </div>

        <!-- 🚀 Right Column: Feature Overrides & Configuration (6 Cols) -->
        <div class="lg:col-span-6 bg-white dark:bg-slate-900/90 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl space-y-6">
          <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-xl">
                ⚡
              </div>
              <div>
                <h2 class="text-base font-black text-slate-900 dark:text-white">المميزات والخصائص المخصصة</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">تفعيل أو تعطيل مميزات الباقة بشكل استثنائي لهذا المشترك</p>
              </div>
            </div>
          </div>

          <!-- Feature Matrix List -->
          <div class="space-y-2.5 max-h-[420px] overflow-y-auto pr-1">
            <div
              v-for="feat in allFeatures"
              :key="feat.key"
              class="p-3.5 rounded-2xl border transition flex items-center justify-between gap-3"
              :class="isFeatureActive(feat.key) ? 'bg-purple-500/5 dark:bg-purple-950/20 border-purple-500/30' : 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800'"
            >
              <div class="flex items-center gap-3 min-w-0">
                <span class="text-lg">{{ feat.icon || '✨' }}</span>
                <div>
                  <div class="text-xs font-black text-slate-900 dark:text-white truncate">{{ feat.name }}</div>
                  <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ feat.key }}</div>
                </div>
              </div>

              <button
                type="button"
                @click="toggleFeature(feat.key)"
                class="px-3 py-1 rounded-xl text-xs font-black border transition cursor-pointer"
                :class="isFeatureActive(feat.key) ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-300 dark:border-slate-700'"
              >
                {{ isFeatureActive(feat.key) ? 'مفعل ✓' : 'معطل ✕' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Status & Plan Modal -->
    <AppModal
      :show="showStatusModal"
      title="تعديل حالة واشتراك المستأجر"
      @close="showStatusModal = false"
    >
      <form @submit.prevent="updateStatusAndPlan" class="space-y-4 font-tajawal">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">حالة الحساب</label>
          <select
            v-model="statusForm.status"
            class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-bold focus:ring-2 focus:ring-purple-500"
          >
            <option value="active">نشط (Active)</option>
            <option value="trial">فترة تجريبية (Trial)</option>
            <option value="suspended">موقوف مؤقتاً (Suspended)</option>
            <option value="expired">منتهي الاشتراك (Expired)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">تمديد فترة الاشتراك (أيام)</label>
          <input
            v-model="statusForm.extend_days"
            type="number"
            min="0"
            class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-purple-500"
            placeholder="مثال: 30"
          />
        </div>

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
          <button
            type="button"
            @click="showStatusModal = false"
            class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold"
          >
            إلغاء
          </button>
          <button
            type="submit"
            :disabled="isUpdatingStatus"
            class="px-5 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-xl text-xs font-black shadow-md"
          >
            {{ isUpdatingStatus ? 'جاري التحديث...' : 'حفظ التعديلات' }}
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import Swal from 'sweetalert2';
import AppModal from '../../Components/Common/AppModal.vue';

const route = useRoute();
const router = useRouter();

const tenantId = route.params.id;
const tenant = ref(null);
const stats = ref({});
const allFeatures = ref([]);
const globalUnitsList = ref([]);
const tenantAllowedUnits = ref([]);
const customTenantUnit = ref('');

const isLoading = ref(true);
const isSavingUnits = ref(false);
const isImpersonating = ref(false);
const isMigrating = ref(false);
const showStatusModal = ref(false);
const isUpdatingStatus = ref(false);

const statusForm = ref({
    status: 'active',
    extend_days: 0,
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'active':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30';
        case 'trial':
            return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30';
        case 'suspended':
            return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30';
        default:
            return 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/30';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'active': return 'نشط ✓';
        case 'trial': return 'فترة تجريبية ⏳';
        case 'suspended': return 'موقوف ⚠️';
        default: return 'غير معروف';
    }
};

const fetchTenantDetails = async () => {
    isLoading.value = true;
    try {
        const res = await api.get(`/super-admin/tenants/${tenantId}`);
        const data = res.data?.data;
        if (data) {
            tenant.value = data.tenant;
            stats.value = data.stats || {};
            allFeatures.value = data.features || [];
            globalUnitsList.value = data.global_units || ['قطعة', 'علبة', 'كرتونة', 'كجم', 'جرام', 'شيكارة', 'طرد', 'دستة', 'لتر'];
            tenantAllowedUnits.value = data.allowed_units || ['قطعة', 'علبة', 'كرتونة', 'كجم', 'جرام', 'شيكارة', 'طرد', 'دستة', 'لتر'];
            statusForm.value.status = data.tenant.status;
        }
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'تعذر العثور على بيانات المستأجر المطلوب',
        });
        router.push('/super-admin/tenants');
    } finally {
        isLoading.value = false;
    }
};

const isFeatureActive = (featureKey) => {
    return tenant.value?.enabled_features?.includes(featureKey) ?? false;
};

const toggleFeature = async (featureKey) => {
    try {
        await api.post(`/super-admin/tenants/${tenantId}/override-feature`, {
            feature_key: featureKey,
        });
        const current = tenant.value.enabled_features || [];
        if (current.includes(featureKey)) {
            tenant.value.enabled_features = current.filter(f => f !== featureKey);
        } else {
            tenant.value.enabled_features = [...current, featureKey];
        }
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'تعذر تعديل الميزة',
        });
    }
};

const addTenantUnit = (unit) => {
    if (!tenantAllowedUnits.value.includes(unit)) {
        tenantAllowedUnits.value.push(unit);
    }
};

const addCustomUnitDirect = () => {
    const u = customTenantUnit.value.trim();
    if (!u) return;
    if (!tenantAllowedUnits.value.includes(u)) {
        tenantAllowedUnits.value.push(u);
    }
    customTenantUnit.value = '';
};

const removeTenantUnit = (idx) => {
    tenantAllowedUnits.value.splice(idx, 1);
};

const saveTenantUnits = async () => {
    if (tenantAllowedUnits.value.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'تنبيه',
            text: 'يجب تحديد وحدة قياس واحدة على الأقل للمستأجر',
        });
        return;
    }

    isSavingUnits.value = true;
    try {
        await api.post(`/super-admin/tenants/${tenantId}/update-units`, {
            units: tenantAllowedUnits.value,
        });

        Swal.fire({
            icon: 'success',
            title: 'تم الحفظ',
            text: 'تم حفظ وتخصيص وحدات القياس للمستأجر بنجاح ✓',
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: e.response?.data?.message || 'تعذر حفظ الوحدات للمستأجر',
        });
    } finally {
        isSavingUnits.value = false;
    }
};

const runMigrations = async () => {
    isMigrating.value = true;
    try {
        const res = await api.post(`/super-admin/tenants/${tenantId}/run-migrations`);
        Swal.fire({
            icon: 'success',
            title: 'اكتمل التحديث',
            text: res.data?.message || 'تم تحديث جداول وقواعد بيانات المستأجر بنجاح ✓',
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'فشل التحديث',
            text: e.response?.data?.message || 'تعذر تشغيل الميجريشن للمستأجر',
        });
    } finally {
        isMigrating.value = false;
    }
};

const impersonateTenant = () => {
    if (tenant.value?.domain) {
        window.open(`http://${tenant.value.domain}`, '_blank');
    }
};

const updateStatusAndPlan = async () => {
    isUpdatingStatus.value = true;
    try {
        await api.post(`/super-admin/tenants/${tenantId}/toggle-status`, statusForm.value);
        tenant.value.status = statusForm.value.status;
        showStatusModal.value = false;
        Swal.fire({
            icon: 'success',
            title: 'تم التحديث',
            text: 'تم تحديث حالة واشتراك المستأجر بنجاح ✓',
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: e.response?.data?.message || 'تعذر تحديث الحالة',
        });
    } finally {
        isUpdatingStatus.value = false;
    }
};

const deleteTenant = async () => {
    const result = await Swal.fire({
        title: 'تأكيد حذف المستأجر نهائياً؟',
        text: `سيتم حذف المنشأة (${tenant.value?.name}) وكافة نطاقاتها. لا يمكن التراجع!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'نعم، احذف المستأجر',
        cancelButtonText: 'إلغاء',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/super-admin/tenants/${tenantId}`);
            Swal.fire({
                icon: 'success',
                title: 'تم الحذف',
                text: 'تم حذف المستأجر بنجاح',
                timer: 1500,
                showConfirmButton: false,
            });
            router.push('/super-admin/tenants');
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: e.response?.data?.message || 'تعذر حذف المستأجر',
            });
        }
    }
};

onMounted(() => {
    fetchTenantDetails();
});
</script>
