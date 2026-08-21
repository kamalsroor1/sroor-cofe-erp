<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-3xl border border-slate-800 shadow-xl backdrop-blur-md">
      <div class="flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 p-0.5 shadow-lg shadow-purple-500/20 text-white flex items-center justify-center shrink-0">
          <Rocket class="w-6 h-6" />
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-lg sm:text-xl font-black text-white">إدارة إصدارات التطبيق وحزم الـ APK</h1>
            <span class="px-2.5 py-0.5 rounded-full bg-purple-500/15 border border-purple-500/30 text-purple-300 text-[10px] font-bold">
              OTA Updater
            </span>
          </div>
          <p class="text-xs text-slate-400 mt-0.5">
            نشر وإدارة تحديثات تطبيق الأندرويد الهوائية (In-App Releases) والتحكم في التحديثات الإجبارية
          </p>
        </div>
      </div>

      <button
        type="button"
        @click="openCreateModal"
        class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs sm:text-sm rounded-2xl shadow-lg shadow-purple-500/25 flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer"
      >
        <Plus class="w-4.5 h-4.5" />
        <span>نشر إصدار APK جديد 🚀</span>
      </button>
    </div>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="p-5 rounded-3xl bg-slate-950/80 border border-slate-800 shadow-lg flex items-center justify-between">
        <div>
          <span class="text-xs text-slate-400 block font-bold">الإصدار النشط حالياً</span>
          <span class="text-xl font-black text-emerald-400 mt-1 block font-mono">
            v{{ summary.active_version || '1.0.0' }}
          </span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
          <CheckCircle class="w-5 h-5" />
        </div>
      </div>

      <div class="p-5 rounded-3xl bg-slate-950/80 border border-slate-800 shadow-lg flex items-center justify-between">
        <div>
          <span class="text-xs text-slate-400 block font-bold">إجمالي التنزيلات عبر التطبيق</span>
          <span class="text-xl font-black text-amber-400 mt-1 block font-mono">
            {{ summary.total_downloads || 0 }} تنزيل
          </span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
          <Download class="w-5 h-5" />
        </div>
      </div>

      <div class="p-5 rounded-3xl bg-slate-950/80 border border-slate-800 shadow-lg flex items-center justify-between">
        <div>
          <span class="text-xs text-slate-400 block font-bold">عدد الإصدارات المنشورة</span>
          <span class="text-xl font-black text-purple-400 mt-1 block font-mono">
            {{ summary.total_releases || 0 }}
          </span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center">
          <Layers class="w-5 h-5" />
        </div>
      </div>
    </div>

    <!-- Releases List -->
    <div class="bg-slate-950/80 rounded-3xl border border-slate-800 p-6 shadow-xl space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-black text-white flex items-center gap-2">
          <HardDrive class="w-4.5 h-4.5 text-purple-400" />
          <span>سجل الإصدارات وحزم الـ APK</span>
        </h2>
        <button
          type="button"
          @click="fetchVersions"
          class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition cursor-pointer"
          title="تحديث البيانات"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400">جاري تحميل قائمة الإصدارات...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="!versions.length" class="p-12 text-center text-slate-400 space-y-2">
        <Rocket class="w-10 h-10 mx-auto text-slate-600" />
        <p class="text-xs">لم يتم نشر أي إصدارات APK حتى الآن.</p>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-xs text-start">
          <thead>
            <tr class="border-b border-slate-800 text-slate-400 font-bold text-[11px]">
              <th class="pb-3 text-start">الإصدار</th>
              <th class="pb-3 text-start">المنصة</th>
              <th class="pb-3 text-start">النوع</th>
              <th class="pb-3 text-start">الحجم</th>
              <th class="pb-3 text-start">التحميلات</th>
              <th class="pb-3 text-start">تاريخ النشر</th>
              <th class="pb-3 text-start">الحالة</th>
              <th class="pb-3 text-end">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr v-for="v in versions" :key="v.id" class="hover:bg-slate-900/50 transition">
              <td class="py-3.5">
                <div class="flex items-center gap-2">
                  <span class="font-black text-white font-mono text-sm">v{{ v.version_name }}</span>
                  <span class="px-2 py-0.5 rounded-md bg-slate-800 text-slate-400 text-[10px] font-mono">
                    Code: {{ v.version_code }}
                  </span>
                </div>
              </td>
              <td class="py-3.5">
                <span class="capitalize font-bold text-slate-300">{{ v.platform }}</span>
              </td>
              <td class="py-3.5">
                <span
                  v-if="v.is_force_update"
                  class="px-2 py-0.5 rounded-md bg-rose-500/10 border border-rose-500/30 text-rose-400 text-[10px] font-bold"
                >
                  إلزامي
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold"
                >
                  اختياري
                </span>
              </td>
              <td class="py-3.5 font-mono text-slate-300">
                {{ formatBytes(v.apk_size_bytes) }}
              </td>
              <td class="py-3.5 font-mono font-bold text-amber-400">
                {{ v.download_count }}
              </td>
              <td class="py-3.5 text-slate-400 text-[11px]">
                {{ v.published_at ? v.published_at.split('T')[0] : '—' }}
              </td>
              <td class="py-3.5">
                <button
                  type="button"
                  @click="toggleActive(v)"
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold transition cursor-pointer"
                  :class="v.is_active ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-slate-800 text-slate-500'"
                >
                  {{ v.is_active ? 'نشط ومتاح' : 'معطل' }}
                </button>
              </td>
              <td class="py-3.5 text-end">
                <div class="flex items-center justify-end gap-2">
                  <a
                    :href="`/api/v1/app/download-apk?platform=${v.platform}`"
                    class="p-2 bg-slate-800 hover:bg-slate-700 text-amber-400 rounded-xl transition"
                    title="تحميل الحزمة"
                    download
                  >
                    <Download class="w-4 h-4" />
                  </a>

                  <button
                    type="button"
                    @click="deleteVersion(v)"
                    class="p-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-xl transition cursor-pointer"
                    title="حذف الإصدار"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Upload New APK Release Modal -->
    <AppModal
      :is-open="isCreateModalOpen"
      title="نشر إصدار APK جديد للتطبيق 🚀"
      @close="isCreateModalOpen = false"
    >
      <form @submit.prevent="submitCreateVersion" class="space-y-4 text-xs font-tajawal">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="block font-bold text-slate-300">اسم الإصدار المقروء *</label>
            <input
              v-model="form.version_name"
              type="text"
              placeholder="مثال: 1.1.0"
              required
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block font-bold text-slate-300">رقم الإصدار الرقمي (Version Code) *</label>
            <input
              v-model.number="form.version_code"
              type="number"
              min="1"
              placeholder="مثال: 2"
              required
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block font-bold text-slate-300">المنصة المستهدفة</label>
            <select
              v-model="form.platform"
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-purple-500"
            >
              <option value="android">📱 Android (APK)</option>
              <option value="windows">💻 Windows</option>
              <option value="ios">🍏 iOS</option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="block font-bold text-slate-300">الحد الأدنى للإصدار المقبول</label>
            <input
              v-model.number="form.min_version_code"
              type="number"
              min="1"
              placeholder="1"
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
            />
          </div>
        </div>

        <!-- Force update toggle -->
        <label class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 cursor-pointer">
          <div>
            <div class="font-bold text-white">تحديث إلزامي (Force Update)</div>
            <div class="text-[11px] text-slate-400 mt-0.5">منع فتح التطبيق القديم وإجبار المستخدم على التحديث فوراً</div>
          </div>
          <input
            type="checkbox"
            v-model="form.is_force_update"
            class="w-4.5 h-4.5 rounded text-purple-600 focus:ring-purple-500"
          />
        </label>

        <!-- Release notes textarea -->
        <div class="space-y-1.5">
          <label class="block font-bold text-slate-300">سجل التغييرات والمميزات الجديدة بالعربية *</label>
          <textarea
            v-model="form.release_notes_ar"
            rows="3"
            required
            placeholder="• ميزة 1...&#10;• ميزة 2..."
            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-purple-500 leading-relaxed"
          ></textarea>
        </div>

        <!-- File Upload -->
        <div class="space-y-1.5">
          <label class="block font-bold text-slate-300">ملف حزمة التطبيق (.apk)</label>
          <input
            type="file"
            accept=".apk"
            @change="handleFileUpload"
            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs file:me-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-purple-600 file:text-white"
          />
          <span class="text-[10px] text-slate-500 block">الحجم الأقصى المسموح به: 150 ميجابايت.</span>
        </div>

        <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
          <button
            type="button"
            @click="isCreateModalOpen = false"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-bold transition"
          >
            إلغاء
          </button>

          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold rounded-xl shadow-lg shadow-purple-500/25 flex items-center gap-2 transition disabled:opacity-50 cursor-pointer"
          >
            <Rocket class="w-4 h-4" />
            <span>{{ isSubmitting ? 'جاري الرفع والنشر...' : 'نشر التحديث الآن 🚀' }}</span>
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import AppModal from '../../Components/Common/AppModal.vue';
import {
    Rocket,
    Plus,
    CheckCircle,
    Download,
    Layers,
    HardDrive,
    RefreshCw,
    Trash2
} from 'lucide-vue-next';

const versions = ref([]);
const summary = ref({});
const isLoading = ref(false);
const isCreateModalOpen = ref(false);
const isSubmitting = ref(false);
const selectedApkFile = ref(null);

const form = ref({
    platform: 'android',
    version_name: '',
    version_code: 2,
    min_version_code: 1,
    is_force_update: false,
    release_notes_ar: '',
    is_active: true,
});

const formatBytes = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
};

const handleFileUpload = (e) => {
    selectedApkFile.value = e.target.files[0] || null;
};

const fetchVersions = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/super-admin/app-versions');
        versions.value = res.data?.versions?.data || [];
        summary.value = res.data?.summary || {};
    } catch (e) {
        console.error('Failed to load app versions:', e);
    } finally {
        isLoading.value = false;
    }
};

const openCreateModal = () => {
    const nextCode = versions.value.length ? Math.max(...versions.value.map(v => v.version_code)) + 1 : 2;
    form.value = {
        platform: 'android',
        version_name: `1.${nextCode - 1}.0`,
        version_code: nextCode,
        min_version_code: 1,
        is_force_update: false,
        release_notes_ar: '• تحسينات عامة في الأداء والسرعة واستقرار النظام.',
        is_active: true,
    };
    selectedApkFile.value = null;
    isCreateModalOpen.value = true;
};

const submitCreateVersion = async () => {
    isSubmitting.value = true;
    try {
        const formData = new FormData();
        formData.append('platform', form.value.platform);
        formData.append('version_name', form.value.version_name);
        formData.append('version_code', form.value.version_code);
        formData.append('min_version_code', form.value.min_version_code);
        formData.append('is_force_update', form.value.is_force_update ? '1' : '0');
        formData.append('release_notes_ar', form.value.release_notes_ar);
        formData.append('is_active', form.value.is_active ? '1' : '0');

        if (selectedApkFile.value) {
            formData.append('apk_file', selectedApkFile.value);
        }

        await api.post('/super-admin/app-versions', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        Swal.fire({
            icon: 'success',
            title: 'تم النشر',
            text: 'تم نشر إصدار التطبيق الجديد بنجاح ✓',
            timer: 1500,
            showConfirmButton: false,
        });

        isCreateModalOpen.value = false;
        fetchVersions();
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: e.response?.data?.message || 'تعذر نشر الإصدار',
        });
    } finally {
        isSubmitting.value = false;
    }
};

const toggleActive = async (v) => {
    try {
        await api.patch(`/super-admin/app-versions/${v.id}/toggle-active`);
        v.is_active = !v.is_active;
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر تعديل الحالة' });
    }
};

const deleteVersion = async (v) => {
    const result = await Swal.fire({
        title: 'تأكيد الحذف',
        text: `هل أنت متأكد من حذف الإصدار v${v.version_name}؟`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#ef4444',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/super-admin/app-versions/${v.id}`);
            Swal.fire({ icon: 'success', title: 'تم الحذف', text: 'تم حذف الإصدار بنجاح' });
            fetchVersions();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر الحذف' });
        }
    }
};

onMounted(() => {
    fetchVersions();
});
</script>
