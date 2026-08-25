<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal transition-colors duration-300">
    <!-- 1. 🔝 Page Header & Actions -->
    <PageHeader
      :title="$t('inventory.items_title')"
      :subtitle="$t('inventory.items_subtitle')"
      :icon="'☕'"
    >
      <template #actions>
        <BaseButton
          type="button"
          variant="gradient"
          size="md"
          :icon="Plus"
          :label="$t('inventory.add_item')"
          @click="openCreateModal"
        />
      </template>
    </PageHeader>

    <!-- 2. 📊 Summary KPIs Grid -->
    <ItemsMetricsGrid :metrics="metrics" :is-loading="isLoading" />

    <!-- 3. 🔍 Search & Status Filters Bar -->
    <ItemsSearchFilterBar
      v-model:search-query="searchQuery"
      v-model:selected-category="selectedCategory"
      v-model:stock-status="stockStatus"
      :categories="categories"
      @search="fetchItems(1)"
    />

    <!-- 4. 📦 Items Table (Dual Responsive: Desktop Table + Mobile Cards Stack) -->
    <ItemsTable
      :items="items"
      :pagination="pagination"
      :is-loading="isLoading"
      @create="openCreateModal"
      @edit="openEditModal"
      @adjust="openAdjustModal"
      @delete="deleteItem"
      @page-change="fetchItems"
    />

    <!-- 5. 📝 Add / Edit Item Modal -->
    <ItemFormModal
      :show="showItemModal"
      :editing-item="editingItem"
      :form="form"
      :categories="categories"
      :units="systemUnits"
      :is-submitting="isSubmitting"
      @close="showItemModal = false"
      @submit="saveItem"
    />

    <!-- 6. ⚖️ Quick Stock Adjustment Modal -->
    <ItemStockAdjustModal
      :show="showAdjustModal"
      :target-item="targetItem"
      :adjust-form="adjustForm"
      :is-submitting="isSubmitting"
      @close="showAdjustModal = false"
      @submit="saveAdjustment"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Plus } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import ItemsMetricsGrid from '../../Components/Items/ItemsMetricsGrid.vue';
import ItemsSearchFilterBar from '../../Components/Items/ItemsSearchFilterBar.vue';
import ItemsTable from '../../Components/Items/ItemsTable.vue';
import ItemFormModal from '../../Components/Items/ItemFormModal.vue';
import ItemStockAdjustModal from '../../Components/Items/ItemStockAdjustModal.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';

const items = ref([]);
const categories = ref([]);
const metrics = ref({
  total_items: 0,
  low_stock_count: 0,
  total_stock_value: 0,
});

const searchQuery = ref('');
const selectedCategory = ref('all');
const stockStatus = ref('all');
const isLoading = ref(true);
const isSubmitting = ref(false);

const systemUnits = ref(['كجم', 'جرام', 'قطعة', 'علبة', 'كرتونة', 'شيكارة', 'طرد', 'دستة', 'لتر']);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
});

// Modals State
const showItemModal = ref(false);
const editingItem = ref(null);
const form = reactive({
  name: '',
  code: '',
  category: '',
  unit: 'كجم',
  cost_price: 0,
  selling_price: 0,
  min_selling_price: 0,
  min_stock_level: 0,
  notes: '',
});

const showAdjustModal = ref(false);
const targetItem = ref(null);
const adjustForm = reactive({
  movement_type: 'stock_adjustment_in',
  quantity: 0,
  notes: '',
});

const fetchItems = async (page = 1) => {
  isLoading.value = true;
  try {
    const response = await api.get('/items', {
      params: {
        search: searchQuery.value,
        category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
        stock_status: stockStatus.value !== 'all' ? stockStatus.value : undefined,
        page,
        per_page: 20,
      },
    });
    items.value = response.data?.data || [];
    metrics.value = response.data?.summary || {
      total_items: 0,
      low_stock_count: 0,
      total_stock_value: 0,
    };
    categories.value = response.data?.categories || [];
    pagination.value = response.data?.meta || {
      current_page: page,
      last_page: 1,
      per_page: 20,
      total: items.value.length,
    };
  } catch (error) {
    console.error('Failed to load items:', error);
  } finally {
    isLoading.value = false;
  }
};

const openCreateModal = () => {
  editingItem.value = null;
  form.name = '';
  form.code = '';
  form.category = '';
  form.unit = 'كجم';
  form.cost_price = 0;
  form.selling_price = 0;
  form.min_selling_price = 0;
  form.min_stock_level = 0;
  form.notes = '';
  showItemModal.value = true;
};

const openEditModal = (item) => {
  editingItem.value = item;
  form.name = item.name;
  form.code = item.code || '';
  form.category = item.category || '';
  form.unit = item.unit || 'كجم';
  form.cost_price = Number(item.cost_price) || 0;
  form.selling_price = Number(item.selling_price) || 0;
  form.min_selling_price = Number(item.min_selling_price || item.price_wholesale || item.selling_price) || 0;
  form.min_stock_level = Number(item.min_stock_level) || 0;
  form.notes = item.notes || '';
  showItemModal.value = true;
};

const saveItem = async () => {
  isSubmitting.value = true;
  try {
    if (editingItem.value) {
      await api.put(`/items/${editingItem.value.id}`, form);
      Swal.fire({
        icon: 'success',
        title: trans('common.success'),
        text: trans('inventory.item_updated'),
        timer: 1500,
        showConfirmButton: false,
      });
    } else {
      await api.post('/items', form);
      Swal.fire({
        icon: 'success',
        title: trans('common.success'),
        text: trans('inventory.item_added'),
        timer: 1500,
        showConfirmButton: false,
      });
    }
    showItemModal.value = false;
    await fetchItems(pagination.value.current_page);
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: trans('common.error'),
      text: error.userMessage || trans('common.error'),
    });
  } finally {
    isSubmitting.value = false;
  }
};

const openAdjustModal = (item) => {
  targetItem.value = item;
  adjustForm.movement_type = 'stock_adjustment_in';
  adjustForm.quantity = '';
  adjustForm.notes = trans('inventory.movement_adjustment');
  showAdjustModal.value = true;
};

const saveAdjustment = async () => {
  if (!adjustForm.quantity || parseFloat(adjustForm.quantity) <= 0) {
    Swal.fire({
      icon: 'warning',
      title: trans('common.warning'),
      text: trans('inventory.enter_valid_adjustment_qty'),
    });
    return;
  }

  isSubmitting.value = true;
  try {
    await api.post(`/items/${targetItem.value.id}/adjust-stock`, {
      ...adjustForm,
      store_id: targetItem.value.store_stocks?.[0]?.store_id || 1,
    });
    Swal.fire({
      icon: 'success',
      title: trans('common.success'),
      text: trans('inventory.stock_adjusted_success'),
      timer: 1500,
      showConfirmButton: false,
    });
    showAdjustModal.value = false;
    await fetchItems(pagination.value.current_page);
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: trans('common.error'),
      text: error.userMessage || trans('inventory.stock_adjustment_failed'),
    });
  } finally {
    isSubmitting.value = false;
  }
};

const deleteItem = async (item) => {
  if (!item.can_be_deleted) {
    const blockers = item.deletion_blockers?.join('\n- ') || '';
    Swal.fire({
      icon: 'warning',
      title: trans('inventory.cannot_delete_item'),
      text: `${trans('contacts.deletion_blockers_found')}\n- ${blockers}`,
    });
    return;
  }

  const result = await Swal.fire({
    title: trans('inventory.delete_item_confirm_title', { name: item.name }) || `حذف الصنف (${item.name})؟`,
    text: trans('inventory.delete_item_confirm_text'),
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: trans('common.delete'),
    cancelButtonText: trans('common.cancel'),
    confirmButtonColor: '#f43f5e',
  });

  if (result.isConfirmed) {
    try {
      await api.delete(`/items/${item.id}`);
      Swal.fire({
        icon: 'success',
        title: trans('common.success'),
        text: trans('inventory.item_deleted'),
        timer: 1500,
        showConfirmButton: false,
      });
      await fetchItems(pagination.value.current_page);
    } catch (error) {
      Swal.fire({
        icon: 'error',
        title: trans('common.error'),
        text: error.userMessage || trans('common.error'),
      });
    }
  }
};

onMounted(() => {
  fetchItems(1);
});
</script>
