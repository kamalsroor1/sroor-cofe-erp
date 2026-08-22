<template>
  <div class="space-y-6 font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('inventory.categories_management') || 'إدارة فئات وتصنيفات الأصناف'"
      :subtitle="$t('inventory.categories_subtitle') || 'تنظيم وتصنيف المنتجات لسهولة الوصول إليها وشريط الفئات في نقطة البيع (POS)'"
    >
      <template #actions>
        <button
          type="button"
          @click="openCreateModal"
          class="h-11 px-5 rounded-2xl bg-theme-gradient text-white font-black text-xs sm:text-sm shadow-theme-primary shadow-lg transition active:scale-95 flex items-center gap-2 cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>{{ $t('inventory.add_category') || 'إضافة فئة جديدة' }}</span>
        </button>
      </template>
    </PageHeader>

    <!-- Categories Grid -->
    <div v-if="isLoading" class="p-16 text-center">
      <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
    </div>

    <div v-else-if="categories.length === 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-12 text-center shadow-xs">
      <div class="w-16 h-16 rounded-3xl bg-theme-light text-theme-primary flex items-center justify-center text-3xl mx-auto mb-3 shadow-xs">
        🗂️
      </div>
      <h3 class="text-sm font-black text-slate-900 dark:text-white mb-1">{{ $t('inventory.no_categories_yet') || 'لا توجد فئات مسجلة حالياً' }}</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ $t('inventory.create_first_category_hint') || 'ابدأ بإضافة فئات لتقسيم أصنافك مثل (قهوة، مشروبات، حلويات، سندوتشات)' }}</p>
      <button
        type="button"
        @click="openCreateModal"
        class="px-6 py-2.5 rounded-2xl btn-primary-theme text-xs font-black shadow-theme-primary shadow-md cursor-pointer inline-flex items-center gap-2"
      >
        <Plus class="w-4 h-4" />
        <span>{{ $t('inventory.add_first_category') || 'إضافة أول فئة' }}</span>
      </button>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div
        v-for="cat in categories"
        :key="cat.id"
        class="p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs hover:border-theme-primary transition-all duration-200 flex flex-col justify-between space-y-4 group"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-2xl shrink-0 shadow-2xs group-hover:scale-110 transition-transform">
              {{ cat.icon || '☕' }}
            </div>
            <div class="min-w-0">
              <h4 class="font-black text-sm text-slate-900 dark:text-white truncate group-hover:text-theme-primary transition-colors">
                {{ cat.name }}
              </h4>
              <div class="flex items-center gap-2 mt-1">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-theme-light text-theme-primary border border-theme-border">
                  {{ cat.items_count || 0 }} {{ $t('inventory.items_unit') || 'صنف' }}
                </span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="cat.is_active ? 'bg-emerald-500/10 text-emerald-500' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
                >
                  {{ cat.is_active ? ($t('common.active') || 'نشط') : ($t('common.inactive') || 'معطل') }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800/80 text-xs">
          <span class="text-[10px] text-slate-400 font-mono">
            #{{ cat.sort_order ?? 0 }}
          </span>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="openEditModal(cat)"
              class="p-2 rounded-xl text-slate-400 hover:text-theme-primary hover:bg-theme-light transition cursor-pointer"
              :title="$t('common.edit')"
            >
              <Pencil class="w-4 h-4" />
            </button>
            <button
              type="button"
              @click="deleteCategory(cat)"
              class="p-2 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 transition cursor-pointer"
              :title="$t('common.delete')"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit Category Modal -->
    <AppModal
      :show="showModal"
      :title="editingCategory ? ($t('inventory.edit_category') || 'تعديل الفئة') : ($t('inventory.add_category') || 'إضافة فئة جديدة')"
      @close="showModal = false"
      max-width="md"
    >
      <form @submit.prevent="saveCategory" class="space-y-4 font-tajawal">
        <!-- Name -->
        <BaseInput
          v-model="form.name"
          :label="$t('inventory.category_name') || 'اسم الفئة'"
          :required="true"
          :placeholder="$t('inventory.category_name_placeholder') || 'مثال: مشروبات ساخنة، بن مطحون، حلويات'"
          :error="formErrors.name"
        />

        <!-- Emoji / Icon Selector -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            {{ $t('inventory.category_icon_emoji') || 'أيقونة أو إيموجي الفئة' }}
          </label>
          <div class="flex items-center gap-2">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-2xl shrink-0">
              {{ form.icon || '☕' }}
            </div>
            <BaseInput
              v-model="form.icon"
              placeholder="☕"
              input-class="h-12 text-center text-lg"
              wrapper-class="flex-1"
            />
          </div>

          <!-- Quick Emoji Presets -->
          <div class="flex flex-wrap gap-1.5 pt-1">
            <button
              v-for="emoji in emojiPresets"
              :key="emoji"
              type="button"
              @click="form.icon = emoji"
              class="w-9 h-9 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-base transition active:scale-90 cursor-pointer shadow-2xs"
              :class="form.icon === emoji ? 'border-theme-primary ring-2 ring-theme-primary/30' : ''"
            >
              {{ emoji }}
            </button>
          </div>
        </div>

        <!-- Sort Order -->
        <BaseNumberInput
          v-model="form.sort_order"
          :label="$t('inventory.sort_order') || 'ترتيب الظهور في شريط الفئات'"
          :step="1"
          :min="0"
          :show-stepper="true"
        />

        <!-- Active Status -->
        <BaseSwitch
          v-model="form.is_active"
          :label="$t('common.status') || 'الحالة'"
          :description="$t('inventory.category_active_desc') || 'تفعيل ظهور هذه الفئة في شاشات الكاشير ونقاط البيع'"
        />

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
          <button
            type="button"
            @click="showModal = false"
            class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 transition cursor-pointer"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="isSaving"
            class="px-6 py-2.5 rounded-xl bg-theme-primary text-slate-950 hover:bg-theme-hover font-black text-xs transition cursor-pointer shadow-lg shadow-theme-primary/20 disabled:opacity-50 flex items-center gap-2"
          >
            <span v-if="isSaving" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
            <span>{{ isSaving ? $t('common.saving') : (editingCategory ? $t('common.save_changes') : $t('inventory.create_category_btn') || 'إضافة الفئة') }}</span>
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import AppModal from '../../Components/Common/AppModal.vue';
import BaseInput from '../../Components/Form/BaseInput.vue';
import BaseNumberInput from '../../Components/Form/BaseNumberInput.vue';
import BaseSwitch from '../../Components/Form/BaseSwitch.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import { Plus, Pencil, Trash2, Tag } from 'lucide-vue-next';

const categories = ref([]);
const isLoading = ref(false);
const showModal = ref(false);
const editingCategory = ref(null);
const isSaving = ref(false);
const formErrors = reactive({});

const emojiPresets = [
  '☕', '🧃', '🍰', '🥪', '🍪', '🫘', '🥤', '🧊', '🎁', '📦', '🥐', '🥗', '🍕', '🍦', '🍨', '🍵'
];

const form = reactive({
  name: '',
  icon: '☕',
  sort_order: 0,
  is_active: true,
});

const fetchCategories = async () => {
  isLoading.value = true;
  try {
    const res = await api.get('/categories');
    categories.value = res.data?.data || [];
  } catch (e) {
    console.error('Failed to load categories:', e);
  } finally {
    isLoading.value = false;
  }
};

const openCreateModal = () => {
  editingCategory.value = null;
  form.name = '';
  form.icon = '☕';
  form.sort_order = categories.value.length;
  form.is_active = true;
  Object.keys(formErrors).forEach(k => delete formErrors[k]);
  showModal.value = true;
};

const openEditModal = (cat) => {
  editingCategory.value = cat;
  form.name = cat.name;
  form.icon = cat.icon || '☕';
  form.sort_order = cat.sort_order ?? 0;
  form.is_active = !!cat.is_active;
  Object.keys(formErrors).forEach(k => delete formErrors[k]);
  showModal.value = true;
};

const saveCategory = async () => {
  isSaving.value = true;
  Object.keys(formErrors).forEach(k => delete formErrors[k]);

  try {
    if (editingCategory.value) {
      await api.put(`/categories/${editingCategory.value.id}`, form);
    } else {
      await api.post('/categories', form);
    }
    showModal.value = false;
    await fetchCategories();
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: editingCategory.value ? (trans('inventory.category_updated_success') || 'تم تعديل الفئة بنجاح ✓') : (trans('inventory.category_created_success') || 'تم إنشاء الفئة بنجاح ✓'),
      showConfirmButton: false,
      timer: 2500
    });
  } catch (e) {
    if (e.response?.data?.errors) {
      Object.assign(formErrors, e.response.data.errors);
    } else {
      Swal.fire({
        icon: 'error',
        title: trans('common.error'),
        text: e.response?.data?.message || trans('common.server_error')
      });
    }
  } finally {
    isSaving.value = false;
  }
};

const deleteCategory = async (cat) => {
  const result = await Swal.fire({
    title: trans('common.confirm_delete') || 'هل أنت متأكد من الحذف؟',
    text: `سيتم حذف الفئة "${cat.name}" وإلغاء ارتباط الأصناف بها دون حذف الأصناف.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    confirmButtonText: trans('common.yes_delete') || 'نعم، احذف',
    cancelButtonText: trans('common.cancel') || 'إلغاء',
  });

  if (result.isConfirmed) {
    try {
      await api.delete(`/categories/${cat.id}`);
      await fetchCategories();
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: trans('inventory.category_deleted_success') || 'تم حذف الفئة بنجاح ✓',
        showConfirmButton: false,
        timer: 2500
      });
    } catch (e) {
      Swal.fire({
        icon: 'error',
        title: trans('common.error'),
        text: e.response?.data?.message || trans('common.server_error')
      });
    }
  }
};

onMounted(() => {
  fetchCategories();
});
</script>