<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('contacts.suppliers_title')"
      :subtitle="$t('contacts.suppliers_subtitle')"
      icon="🏭"
    >
      <template #actions>
        <BaseButton
          type="button"
          variant="primary"
          size="md"
          @click="openCreateModal"
          class="font-black shadow-theme-primary shadow-lg flex items-center gap-2"
        >
          <Plus class="w-4 h-4" />
          <span>{{ $t('contacts.add_supplier') }}</span>
        </BaseButton>
      </template>
    </PageHeader>

    <!-- Summary Metrics Grid -->
    <SuppliersMetricsGrid
      :metrics="metrics"
      :loading="isLoading"
    />

    <!-- Filters & Search Bar -->
    <SuppliersFilterBar
      v-model:search-query="searchQuery"
      :debt-status="debtStatus"
      @search="debounceSearch"
      @set-debt-status="setDebtStatus"
    />

    <!-- Suppliers Table & Mobile Cards -->
    <SuppliersTable
      :suppliers="suppliers"
      :pagination="pagination"
      :loading="isLoading"
      @create="openCreateModal"
      @pay="openPaymentModal"
      @edit="openEditModal"
      @delete="deleteSupplier"
      @page-change="fetchSuppliers"
    />

    <!-- Add / Edit Supplier Modal -->
    <SupplierFormModal
      :show="showSupplierModal"
      :editing-supplier="editingSupplier"
      :form="form"
      :submitting="isSubmitting"
      @close="showSupplierModal = false"
      @save="saveSupplier"
      @update:field="updateFormField"
    />

    <!-- Pay Supplier Modal -->
    <SupplierPaymentModal
      :show="showPaymentModal"
      :target-supplier="targetSupplier"
      :payment-form="paymentForm"
      :submitting="isSubmittingPayment"
      @close="showPaymentModal = false"
      @save="savePayment"
      @update:field="updatePaymentField"
    />
  </div>
</template>

<script setup>
import { Plus } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import SuppliersMetricsGrid from '../../Components/Suppliers/SuppliersMetricsGrid.vue';
import SuppliersFilterBar from '../../Components/Suppliers/SuppliersFilterBar.vue';
import SuppliersTable from '../../Components/Suppliers/SuppliersTable.vue';
import SupplierFormModal from '../../Components/Suppliers/SupplierFormModal.vue';
import SupplierPaymentModal from '../../Components/Suppliers/SupplierPaymentModal.vue';
import { useSuppliers } from '../../Composables/useSuppliers';

const {
  suppliers,
  metrics,
  searchQuery,
  debtStatus,
  isLoading,
  isSubmitting,
  pagination,
  showSupplierModal,
  editingSupplier,
  form,
  showPaymentModal,
  targetSupplier,
  paymentForm,
  isSubmittingPayment,
  fetchSuppliers,
  debounceSearch,
  setDebtStatus,
  openCreateModal,
  openEditModal,
  updateFormField,
  updatePaymentField,
  saveSupplier,
  openPaymentModal,
  savePayment,
  deleteSupplier,
} = useSuppliers();
</script>
