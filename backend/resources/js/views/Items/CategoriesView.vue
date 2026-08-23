<template>
  <div class="space-y-6 font-tajawal transition-colors duration-300">
    <!-- 1. 🔝 Page Header & Actions -->
    <PageHeader
      :title="$t('inventory.categories_management') || 'إدارة فئات وتصنيفات الأصناف'"
      :subtitle="$t('inventory.categories_subtitle') || 'تنظيم وتصنيف المنتجات لسهولة الوصول إليها وشريط الفئات في نقطة البيع (POS)'"
      :icon="'🗂️'"
    >
      <template #actions>
        <BaseButton
          type="button"
          variant="gradient"
          size="md"
          :icon="Plus"
          :label="$t('inventory.add_category') || 'إضافة فئة جديدة'"
          @click="openCreateModal"
        />
      </template>
    </PageHeader>

    <!-- 2. 🗂️ Categories Grid (With Loading & Empty State) -->
    <CategoriesGrid
      :categories="categories"
      :is-loading="isLoading"
      @create="openCreateModal"
      @edit="openEditModal"
      @delete="deleteCategory"
    />

    <!-- 3. 📝 Create / Edit Category Modal -->
    <CategoryFormModal
      :show="showModal"
      :editing-category="editingCategory"
      :form="form"
      :errors="formErrors"
      :is-submitting="isSaving"
      @close="showModal = false"
      @submit="saveCategory"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Plus } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import CategoriesGrid from '../../Components/Categories/CategoriesGrid.vue';
import CategoryFormModal from '../../Components/Categories/CategoryFormModal.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';

const categories = ref([]);
const isLoading = ref(false);
const showModal = ref(false);
const editingCategory = ref(null);
const isSaving = ref(false);
const formErrors = reactive({});

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
  Object.keys(formErrors).forEach((k) => delete formErrors[k]);
  showModal.value = true;
};

const openEditModal = (cat) => {
  editingCategory.value = cat;
  form.name = cat.name;
  form.icon = cat.icon || '☕';
  form.sort_order = cat.sort_order ?? 0;
  form.is_active = !!cat.is_active;
  Object.keys(formErrors).forEach((k) => delete formErrors[k]);
  showModal.value = true;
};

const saveCategory = async () => {
  isSaving.value = true;
  Object.keys(formErrors).forEach((k) => delete formErrors[k]);

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
      title: editingCategory.value
        ? (trans('inventory.category_updated_success') || 'تم تعديل الفئة بنجاح ✓')
        : (trans('inventory.category_created_success') || 'تم إنشاء الفئة بنجاح ✓'),
      showConfirmButton: false,
      timer: 2500,
    });
  } catch (e) {
    if (e.response?.data?.errors) {
      Object.assign(formErrors, e.response.data.errors);
    } else {
      Swal.fire({
        icon: 'error',
        title: trans('common.error'),
        text: e.response?.data?.message || trans('common.server_error'),
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
        timer: 2500,
      });
    } catch (e) {
      Swal.fire({
        icon: 'error',
        title: trans('common.error'),
        text: e.response?.data?.message || trans('common.server_error'),
      });
    }
  }
};

onMounted(() => {
  fetchCategories();
});
</script>