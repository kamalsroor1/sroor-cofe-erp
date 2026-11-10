<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal transition-colors duration-300">
    <!-- 1. Page Header & Action Buttons -->
    <PageHeader
      :title="$t('inventory.stores_branches')"
      :subtitle="$t('inventory.stores_branches_subtitle')"
      :icon="StoreIcon"
    >
      <template #actions>
        <div class="flex items-center gap-2 flex-wrap">
          <BaseButton
            v-can="'items.view'"
            to="/stores/stocks"
            variant="secondary"
            :icon="Package"
            :label="$t('inventory.branch_stocks_balance')"
          />
          <BaseButton variant="gradient" :icon="Plus" :label="$t('inventory.add_store')" @click="openCreateModal" />
        </div>
      </template>
    </PageHeader>

    <!-- 2. 📊 Summary Metrics Grid -->
    <ErrorState v-if="error && stores.length === 0" :message="errorMessage" :loading="isLoading" @retry="fetchStores" />

    <template v-else>
      <InlineErrorBar v-if="error" :message="errorMessage" @retry="fetchStores" />
      <StoresMetricsGrid :stores="stores" :is-loading="isLoading" />

      <!-- 3. 🔍 Search & Filters Bar -->
      <StoresSearchFilterBar
        v-model:search="searchQuery"
        v-model:type="selectedType"
        v-model:status="selectedStatus"
        @reset="resetFilters"
      />

      <!-- 4. 🏬 Stores Cards Grid with Shimmer Skeletons -->
      <StoresGrid
        :stores="filteredStores"
        :is-loading="isLoading"
        @create="openCreateModal"
        @edit="openEditModal"
        @delete="deleteStore"
        @toggle-active="toggleActive"
        @manage-staff="openStaffModal"
      />
    </template>

    <!-- 5. Create / Edit Store Modal -->
    <StoreFormModal
      :show="showStoreModal"
      :editing-store="editingStore"
      :form="form"
      :show-shift-setting="shiftSetting.available.value"
      :require-shift="shiftSetting.value.value"
      @update:require-shift="shiftSetting.value.value = $event"
      @update:form="Object.assign(form, $event)"
      :submitting="isSubmitting"
      @close="showStoreModal = false"
      @submit="saveStore"
    />

    <!-- 6. Staff Assignment Modal -->
    <StoreStaffModal
      :show="showStaffModal"
      :target-store="targetStore"
      :all-users="allUsers"
      v-model="assignedUserIds"
      :submitting="isSubmitting"
      @close="showStaffModal = false"
      @submit="saveStaffAssignment"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Plus, Package, Store as StoreIcon } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import api from '../../Services/api';
import { useTrans } from '../../Composables/useTrans';
import { useLoadError } from '../../Composables/useLoadError';
import ErrorState from '../../Components/Common/ErrorState.vue';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import StoresMetricsGrid from '../../Components/Stores/StoresMetricsGrid.vue';
import StoresSearchFilterBar from '../../Components/Stores/StoresSearchFilterBar.vue';
import StoresGrid from '../../Components/Stores/StoresGrid.vue';
import { useStoreShiftSetting } from '../../Composables/useStoreShiftSetting';
import StoreFormModal from '../../Components/Stores/StoreFormModal.vue';
import StoreStaffModal from '../../Components/Stores/StoreStaffModal.vue';

const { t } = useTrans();
const stores = ref([]);
const allUsers = ref([]);
const isLoading = ref(true);
const isSubmitting = ref(false);

const searchQuery = ref('');
const selectedType = ref('all');
const selectedStatus = ref('all');

const showStoreModal = ref(false);
const editingStore = ref(null);
const form = reactive({
  name: '',
  code: '',
  type: 'retail_shop',
  address: '',
  phone: '',
  is_active: true,
  is_main: false,
});

const shiftSetting = useStoreShiftSetting();

const showStaffModal = ref(false);
const targetStore = ref(null);
const assignedUserIds = ref([]);

const filteredStores = computed(() => {
  return stores.value.filter((s) => {
    if (selectedType.value !== 'all' && s.type !== selectedType.value) return false;
    if (selectedStatus.value === 'active' && !s.is_active) return false;
    if (selectedStatus.value === 'inactive' && s.is_active) return false;
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase();
      const matchName = s.name?.toLowerCase().includes(q);
      const matchCode = s.code?.toLowerCase().includes(q);
      const matchAddress = s.address?.toLowerCase().includes(q);
      const matchPhone = s.phone?.toLowerCase().includes(q);
      if (!matchName && !matchCode && !matchAddress && !matchPhone) return false;
    }
    return true;
  });
});

const resetFilters = () => {
  searchQuery.value = '';
  selectedType.value = 'all';
  selectedStatus.value = 'all';
};

const { error, errorMessage, clearError, setError } = useLoadError();

const fetchStores = async () => {
  isLoading.value = true;
  clearError();
  try {
    const res = await api.get('/stores');
    stores.value = res.data?.stores || [];
    allUsers.value = res.data?.all_users || [];
  } catch (err) {
    setError(err);
  } finally {
    isLoading.value = false;
  }
};

const openCreateModal = () => {
  editingStore.value = null;
  shiftSetting.reset();
  Object.assign(form, {
    name: '',
    code: '',
    type: 'retail_shop',
    address: '',
    phone: '',
    is_active: true,
    is_main: false,
  });
  showStoreModal.value = true;
};

const openEditModal = (store) => {
  editingStore.value = store;
  shiftSetting.load(store.id);
  Object.assign(form, {
    name: store.name,
    code: store.code,
    type: store.type,
    address: store.address || '',
    phone: store.phone || '',
    is_active: store.is_active,
    is_main: store.is_main,
  });
  showStoreModal.value = true;
};

const saveStore = async () => {
  isSubmitting.value = true;
  try {
    if (editingStore.value) {
      await api.put(`/stores/${editingStore.value.id}`, form);
      const shiftSaved = await shiftSetting.save(editingStore.value.id);
      Swal.fire({
        icon: shiftSaved ? 'success' : 'warning',
        title: t('common.success'),
        text: shiftSaved ? t('inventory.store_updated_success') : t('pos.shift_setting_save_failed'),
        timer: shiftSaved ? 1500 : undefined,
        showConfirmButton: !shiftSaved,
      });
    } else {
      await api.post('/stores', form);
      Swal.fire({
        icon: 'success',
        title: t('common.success'),
        text: t('inventory.store_added_success'),
        timer: 1500,
        showConfirmButton: false,
      });
    }
    showStoreModal.value = false;
    await fetchStores();
  } catch (error) {
    Swal.fire({ icon: 'error', title: t('common.error'), text: error.userMessage || t('inventory.store_save_failed') });
  } finally {
    isSubmitting.value = false;
  }
};

const toggleActive = async (store) => {
  if (store.is_main && store.is_active) {
    Swal.fire({ icon: 'warning', title: t('common.warning'), text: t('inventory.cannot_disable_main_alert') });
    return;
  }
  try {
    await api.patch(`/stores/${store.id}/toggle-active`);
    await fetchStores();
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: t('common.error'),
      text: error.userMessage || t('inventory.store_toggle_active_failed'),
    });
  }
};

const deleteStore = async (store) => {
  if (!store.can_be_deleted) {
    const blockers = store.deletion_blockers?.join('\n- ') || '';
    Swal.fire({
      icon: 'warning',
      title: t('inventory.cannot_delete_store'),
      text: t('inventory.store_delete_blockers', { blockers }),
    });
    return;
  }
  const result = await Swal.fire({
    title: t('inventory.store_delete_confirm_title', { name: store.name }),
    text: t('inventory.store_delete_confirm_text'),
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: t('common.yes'),
    cancelButtonText: t('common.cancel'),
    confirmButtonColor: '#f43f5e',
  });
  if (result.isConfirmed) {
    try {
      await api.delete(`/stores/${store.id}`);
      Swal.fire({
        icon: 'success',
        title: t('common.success'),
        text: t('inventory.store_deleted_success'),
        timer: 1500,
        showConfirmButton: false,
      });
      await fetchStores();
    } catch (error) {
      Swal.fire({
        icon: 'error',
        title: t('common.error'),
        text: error.userMessage || t('inventory.store_delete_failed'),
      });
    }
  }
};

const openStaffModal = (store) => {
  targetStore.value = store;
  assignedUserIds.value = store.assigned_user_ids ? [...store.assigned_user_ids] : [];
  showStaffModal.value = true;
};

const saveStaffAssignment = async () => {
  isSubmitting.value = true;
  try {
    await api.post(`/stores/${targetStore.value.id}/assign-users`, { user_ids: assignedUserIds.value });
    Swal.fire({
      icon: 'success',
      title: t('common.success'),
      text: t('inventory.staff_assigned_success'),
      timer: 1500,
      showConfirmButton: false,
    });
    showStaffModal.value = false;
    await fetchStores();
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: t('common.error'),
      text: error.userMessage || t('inventory.staff_assign_failed'),
    });
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(fetchStores);
</script>
