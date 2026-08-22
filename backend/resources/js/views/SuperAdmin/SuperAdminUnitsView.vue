<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/90 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center text-xl">
          ⚖️
        </div>
        <div>
          <h1 class="text-xl font-black text-slate-900 dark:text-white">إدارة وحدات القياس للنظام</h1>
          <p class="text-xs text-slate-500 dark:text-slate-400">تحديد وتخصيص وحدات القياس المتاحة لكافة المستأجرين في المنظومة</p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <router-link
          to="/super-admin/dashboard"
          class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-bold text-xs rounded-xl shadow flex items-center gap-2 transition"
        >
          <span>📊</span>
          <span>لوحة التحكم</span>
        </router-link>

        <button
          @click="saveUnits"
          :disabled="isSaving"
          class="px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/20 flex items-center gap-2 transition cursor-pointer disabled:opacity-50 active:scale-95"
        >
          <span v-if="isSaving" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span v-else>💾</span>
          <span>{{ isSaving ? 'جاري الحفظ...' : 'حفظ التعديلات' }}</span>
        </button>
      </div>
    </div>

    <!-- Active Units Container -->
    <div class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xl space-y-6">
      <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">الوحدات المفعلة حالياً بالنظام ({{ units.length }})</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">هذه الوحدات تظهر تلقائياً في قوائم إضافة الأصناف وحركات المخزون</p>
        </div>
      </div>

      <div v-if="isLoading" class="p-12 text-center">
        <div class="w-8 h-8 border-3 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
        <p class="text-xs text-slate-400">جاري تحميل الوحدات...</p>
      </div>

      <div v-else class="space-y-6">
        <!-- Units Badges Grid -->
        <div class="flex flex-wrap gap-2.5">
          <div
            v-for="(u, idx) in units"
            :key="u"
            class="px-4 py-2 rounded-xl text-xs font-bold bg-purple-500/10 dark:bg-purple-950/40 border border-purple-500/30 text-purple-700 dark:text-purple-300 flex items-center gap-2.5 shadow-2xs group"
          >
            <span class="text-sm font-black">{{ u }}</span>
            <span
              class="px-1.5 py-0.2 rounded text-[10px] font-bold"
              :class="isDiscrete(u) ? 'bg-theme-light text-theme-primary text-theme-primary' : 'bg-blue-500/20 text-blue-700 dark:text-blue-300'"
            >
              {{ isDiscrete(u) ? 'عددية (ممنوع الكسور)' : 'وزن/حجم (تقبل الكسور)' }}
            </span>
            <button
              type="button"
              @click="removeUnit(idx)"
              class="w-5 h-5 rounded-full hover:bg-rose-500/20 hover:text-rose-500 flex items-center justify-center text-xs transition cursor-pointer text-slate-400"
              title="حذف الوحدة"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- Add Custom Unit Input -->
        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-3">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            إضافة وحدة قياس مخصصة جديدة للنظام:
          </label>
          <div class="flex items-center gap-2">
            <input
              v-model="newUnitInput"
              @keyup.enter="addCustomUnit"
              type="text"
              placeholder="مثال: باليتة، طقم، زوج، باكت، صندوق..."
              class="flex-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
            />
            <button
              type="button"
              @click="addCustomUnit"
              class="px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white rounded-xl text-xs font-black shadow-md cursor-pointer active:scale-95 shrink-0"
            >
              + إضافة للسيستم
            </button>
          </div>
        </div>

        <!-- Preset Suggestions -->
        <div class="space-y-2">
          <div class="text-xs text-slate-500 dark:text-slate-400 font-bold">وحدات مقترحة شائعة (اضغط للإضافة الفورية):</div>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="preset in presets"
              :key="preset"
              type="button"
              @click="addPreset(preset)"
              :disabled="units.includes(preset)"
              class="px-3 py-1.5 rounded-xl border text-xs font-bold transition cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
              :class="units.includes(preset) ? 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-400' : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-purple-500'"
            >
              + {{ preset }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import BaseInput from '../../Components/Form/BaseInput.vue';
import BaseSearchInput from '../../Components/Form/BaseSearchInput.vue';
import Swal from 'sweetalert2';

const units = ref(['قطعة', 'علبة', 'كرتونة', 'كجم', 'جرام', 'شيكارة', 'طرد', 'دستة', 'باكت', 'حبة', 'لتر', 'مل', 'متر', 'طقم', 'زوج', 'باليتة']);
const presets = ['قطعة', 'علبة', 'كرتونة', 'كجم', 'جرام', 'شيكارة', 'طرد', 'دستة', 'باكت', 'حبة', 'لتر', 'مل', 'متر', 'طقم', 'زوج', 'باليتة', 'صندوق', 'رول', 'برميل', 'شريحة'];
const newUnitInput = ref('');
const isLoading = ref(false);
const isSaving = ref(false);

const isDiscrete = (unit) => {
    if (!unit) return true;
    const u = unit.toString().trim().toLowerCase();
    const discrete = ['قطعة', 'حبة', 'علبة', 'باكت', 'كرتونة', 'شيكارة', 'طرد', 'دستة', 'جوال', 'طقم', 'زوج', 'باليتة', 'صندوق', 'برميل', 'شريحة', 'piece', 'pcs', 'box', 'carton', 'pack', 'unit', 'item'];
    return discrete.includes(u);
};

const fetchUnits = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/super-admin/units');
        if (res.data?.units && Array.isArray(res.data.units) && res.data.units.length > 0) {
            units.value = res.data.units;
        }
    } catch (e) {
        console.error('Failed to load super admin units:', e);
    } finally {
        isLoading.value = false;
    }
};

const addCustomUnit = () => {
    const u = newUnitInput.value.trim();
    if (!u) return;
    if (!units.value.includes(u)) {
        units.value.push(u);
    }
    newUnitInput.value = '';
};

const addPreset = (p) => {
    if (!units.value.includes(p)) {
        units.value.push(p);
    }
};

const removeUnit = (idx) => {
    units.value.splice(idx, 1);
};

const saveUnits = async () => {
    if (units.value.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'تنبيه',
            text: 'يجب الإبقاء على وحدة قياس واحدة على الأقل في النظام',
        });
        return;
    }

    isSaving.value = true;
    try {
        await api.post('/super-admin/units', {
            units: units.value,
        });

        Swal.fire({
            icon: 'success',
            title: 'تم الحفظ بنجاح',
            text: 'تم تحديث وحدات القياس للنظام بنجاح ✓',
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: e.response?.data?.message || 'تعذر حفظ وحدات القياس',
        });
    } finally {
        isSaving.value = false;
    }
};

onMounted(() => {
    fetchUnits();
});
</script>
