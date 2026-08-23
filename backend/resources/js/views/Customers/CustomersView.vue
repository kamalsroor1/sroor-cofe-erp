<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('contacts.customers_title')"
      :subtitle="$t('contacts.customers_subtitle')"
      icon="👥"
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
          <span>{{ $t('contacts.add_customer') }}</span>
        </BaseButton>
      </template>
    </PageHeader>

    <!-- Summary Metrics Grid -->
    <CustomersMetricsGrid
      :metrics="metrics"
      :loading="isLoading"
    />

    <!-- Filters & Search Bar -->
    <CustomersFilterBar
      v-model:search-query="searchQuery"
      :debt-status="debtStatus"
      @search="debounceSearch"
      @set-debt-status="setDebtStatus"
    />

    <!-- Customers Table & Mobile Cards -->
    <CustomersTable
      :customers="customers"
      :pagination="pagination"
      :loading="isLoading"
      @create="openCreateModal"
      @pay="openPaymentModal"
      @edit="openEditModal"
      @delete="deleteCustomer"
      @page-change="fetchCustomers"
    />

    <!-- Add / Edit Customer Modal -->
    <CustomerFormModal
      :show="showCustomerModal"
      :editing-customer="editingCustomer"
      :form="form"
      :submitting="isSubmitting"
      @close="showCustomerModal = false"
      @save="saveCustomer"
      @update:field="updateFormField"
    />

    <!-- Collect Payment Modal -->
    <CustomerPaymentModal
      :show="showPaymentModal"
      :target-customer="targetCustomer"
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
import CustomersMetricsGrid from '../../Components/Customers/CustomersMetricsGrid.vue';
import CustomersFilterBar from '../../Components/Customers/CustomersFilterBar.vue';
import CustomersTable from '../../Components/Customers/CustomersTable.vue';
import CustomerFormModal from '../../Components/Customers/CustomerFormModal.vue';
import CustomerPaymentModal from '../../Components/Customers/CustomerPaymentModal.vue';
import { useCustomers } from '../../Composables/useCustomers';

const {
  customers,
  metrics,
  searchQuery,
  debtStatus,
  isLoading,
  isSubmitting,
  pagination,
  showCustomerModal,
  editingCustomer,
  form,
  showPaymentModal,
  targetCustomer,
  paymentForm,
  isSubmittingPayment,
  fetchCustomers,
  debounceSearch,
  setDebtStatus,
  openCreateModal,
  openEditModal,
  updateFormField,
  updatePaymentField,
  saveCustomer,
  openPaymentModal,
  savePayment,
  deleteCustomer,
} = useCustomers();
</script>
