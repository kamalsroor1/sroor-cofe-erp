<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal transition-colors duration-300">
    <!-- 1. 🔝 Page Header & Actions -->
    <PageHeader
      :title="$t('inventory.items_title')"
      :subtitle="$t('inventory.items_subtitle')"
      :icon="'coffee'"
      help-slug="items"
    >
      <template #actions>
        <BaseButton
          v-if="authStore.can('items.create')"
          :to="{ name: 'imports.index', query: { type: 'items' } }"
          variant="outline"
          size="md"
          :icon="FileUp"
          :label="$t('import.ui.import_button')"
        />
        <FeatureGate feature="items.manage">
          <BaseButton
            type="button"
            variant="gradient"
            size="md"
            :icon="Plus"
            :label="$t('inventory.add_item')"
            @click="openCreateModal"
          />
        </FeatureGate>
      </template>
    </PageHeader>

    <!-- 2. 📊 Summary KPIs Grid -->
    <ItemsMetricsGrid v-if="!hasBlockingError" :metrics="metrics" :is-loading="isLoading" />

    <!-- 3. 🔍 Search & Status Filters Bar -->
    <ItemsSearchFilterBar
      v-model:search-query="searchQuery"
      v-model:selected-category="selectedCategory"
      v-model:stock-status="stockStatus"
      :categories="categories"
      @search="fetchItems(1)"
    />

    <!-- 4. 📦 Items Table (Dual Responsive: Desktop Table + Mobile Cards Stack) -->
    <InlineErrorBar
      v-if="error && !hasBlockingError"
      :message="errorMessage"
      @retry="fetchItems(pagination.current_page)"
    />
    <ErrorState
      v-if="hasBlockingError"
      :message="errorMessage"
      :loading="isLoading"
      @retry="fetchItems(pagination.current_page)"
    />
    <ItemsTable
      v-else
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
      @update:form="Object.assign(form, $event)"
      :categories="categories"
      :units="unitOptionsFor(editingItem?.unit)"
      :is-submitting="isSubmitting"
      @close="showItemModal = false"
      @submit="saveItem"
    />

    <!-- 6. ⚖️ Quick Stock Adjustment Modal -->
    <ItemStockAdjustModal
      :show="showAdjustModal"
      :target-item="targetItem"
      :adjust-form="adjustForm"
      @update:adjust-form="Object.assign(adjustForm, $event)"
      :is-submitting="isSubmitting"
      @close="showAdjustModal = false"
      @submit="saveAdjustment"
    />
  </div>
</template>

<script setup>
import { isWeightUnit } from '../../helpers/weightUnits';
import { ref, reactive, computed, onMounted } from 'vue';
import { FileUp, Plus } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';
import FeatureGate from '../../Components/FeatureGate.vue';
import ErrorState from '../../Components/Common/ErrorState.vue';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import ItemsMetricsGrid from '../../Components/Items/ItemsMetricsGrid.vue';
import ItemsSearchFilterBar from '../../Components/Items/ItemsSearchFilterBar.vue';
import ItemsTable from '../../Components/Items/ItemsTable.vue';
import ItemFormModal from '../../Components/Items/ItemFormModal.vue';
import ItemStockAdjustModal from '../../Components/Items/ItemStockAdjustModal.vue';
import api from '../../Services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import { useUnits } from '../../Composables/useUnits';
import { useLoadError } from '../../Composables/useLoadError';

const authStore = useAuthStore();

const { defaultUnit, unitOptionsFor } = useUnits();
const { error, errorMessage, clearError, setError } = useLoadError();

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
const hasBlockingError = computed(() => error.value && items.value.length === 0);
const isSubmitting = ref(false);

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
  unit: defaultUnit.value,
  is_weighted: isWeightUnit(defaultUnit.value),
  cost_price: 0,
  selling_price: 0,
  wholesale_price: 0,
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
  clearError();
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
  } catch (err) {
    setError(err);
  } finally {
    isLoading.value = false;
  }
};

const openCreateModal = () => {
  editingItem.value = null;
  form.name = '';
  form.code = '';
  form.category = '';
  form.unit = defaultUnit.value;
  form.is_weighted = isWeightUnit(defaultUnit.value);
  form.cost_price = 0;
  form.selling_price = 0;
  form.wholesale_price = 0;
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
  form.unit = item.unit || defaultUnit.value;
  form.is_weighted = !!item.is_weighted;
  // null = the cost is hidden from this user (no items.view_cost): keep it null, never turn it into 0.
  form.cost_price = item.cost_price == null ? null : Number(item.cost_price) || 0;
  form.selling_price = Number(item.selling_price) || 0;
  form.wholesale_price = Number(item.wholesale_price) || 0;
  // null = the floor is hidden from this user (no items.view_cost): keep it null, never turn it into 0.
  form.min_selling_price = item.min_selling_price == null ? null : Number(item.min_selling_price) || 0;
  form.min_stock_level = Number(item.min_stock_level) || 0;
  form.notes = item.notes || '';
  showItemModal.value = true;
};

const saveItem = async () => {
  isSubmitting.value = true;
  try {
    // A hidden (null) floor is not sent at all, so the server keeps the stored value.
    const payload = { ...form };
    if (payload.min_selling_price == null) delete payload.min_selling_price;
    if (payload.cost_price == null && editingItem.value) delete payload.cost_price;
    if (editingItem.value) {
      await api.put(`/items/${editingItem.value.id}`, payload);
      Swal.fire({
        icon: 'success',
        title: trans('common.success'),
        text: trans('inventory.item_updated'),
        timer: 1500,
        showConfirmButton: false,
      });
    } else {
      await api.post('/items', payload);
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
    title: trans('inventory.delete_item_confirm_title', { name: item.name }),
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
