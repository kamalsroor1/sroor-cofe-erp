<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal transition-colors duration-300">
    <!-- 🚚 Page Header -->
    <PageHeader
      :title="$t('inventory.transfers_title')"
      :subtitle="$t('inventory.transfers_subtitle')"
      :icon="'🚚'"
    >
      <template #actions>
        <BaseButton
          variant="gradient"
          size="md"
          :icon="Plus"
          :label="$t('inventory.new_transfer_order')"
          :to="'/stock-transfers/create'"
        />
      </template>
    </PageHeader>

    <!-- 📊 Summary Metrics Grid -->
    <StockTransfersMetricsGrid :summary="summary" :is-loading="isLoading" />

    <!-- 🔍 Search & Filters Bar -->
    <StockTransfersSearchFilterBar
      v-model:search="searchQuery"
      v-model:from-store-id="fromStoreId"
      v-model:to-store-id="toStoreId"
      v-model:date-from="dateFrom"
      v-model:date-to="dateTo"
      :stores="stores"
    />

    <!-- 📋 Transfers Ledger Table -->
    <StockTransfersTable
      :transfers="transfersList"
      :pagination="pagination"
      :is-loading="isLoading"
      @preview="openDetailsModal"
      @cancel="cancelTransferDoc"
      @page-change="fetchTransfers"
    />

    <!-- 👁️ Transfer Details Modal -->
    <StockTransferDetailsModal
      :show="showDetailsModal"
      :transfer="selectedTransferDetails"
      @close="showDetailsModal = false"
    />
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { Plus } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import api from '../../services/api';
import { trans } from '../../helpers/trans';

import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import StockTransfersMetricsGrid from '../../Components/StockTransfers/StockTransfersMetricsGrid.vue';
import StockTransfersSearchFilterBar from '../../Components/StockTransfers/StockTransfersSearchFilterBar.vue';
import StockTransfersTable from '../../Components/StockTransfers/StockTransfersTable.vue';
import StockTransferDetailsModal from '../../Components/StockTransfers/StockTransferDetailsModal.vue';

const transfersList = ref([]);
const stores = ref([]);
const summary = ref({ total_count: 0, confirmed_count: 0, cancelled_count: 0 });

const searchQuery = ref('');
const fromStoreId = ref('all');
const toStoreId = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const isLoading = ref(true);

const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

const showDetailsModal = ref(false);
const selectedTransferDetails = ref(null);

const loadStores = async () => {
  try {
    const res = await api.get('/stores');
    stores.value = res.data?.data || [];
  } catch (err) {
    console.error('Failed to load stores:', err);
  }
};

const fetchTransfers = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await api.get('/transfers', {
      params: {
        search: searchQuery.value || undefined,
        from_store_id: fromStoreId.value !== 'all' ? fromStoreId.value : undefined,
        to_store_id: toStoreId.value !== 'all' ? toStoreId.value : undefined,
        from_date: dateFrom.value || undefined,
        to_date: dateTo.value || undefined,
        page,
        per_page: 15,
      },
    });
    transfersList.value = res.data?.data || [];
    summary.value = res.data?.summary || { total_count: 0, confirmed_count: 0, cancelled_count: 0 };
    pagination.value = res.data?.meta || {
      current_page: page,
      last_page: 1,
      per_page: 15,
      total: transfersList.value.length,
    };
  } catch (err) {
    console.error('Failed to load transfers:', err);
  } finally {
    isLoading.value = false;
  }
};

const openDetailsModal = async (trf) => {
  try {
    const res = await api.get(`/transfers/${trf.id}`);
    selectedTransferDetails.value = res.data?.data;
    showDetailsModal.value = true;
  } catch (err) {
    console.error('Failed to load transfer details:', err);
  }
};

const cancelTransferDoc = async (trf) => {
  const { value: reason } = await Swal.fire({
    title: trans('inventory.cancel_transfer_confirm_title', { number: trf.transfer_number }),
    text: trans('inventory.cancel_transfer_confirm_text'),
    input: 'text',
    inputPlaceholder: trans('inventory.cancel_reason_placeholder'),
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: trans('inventory.cancel_transfer_btn'),
    cancelButtonText: trans('common.cancel'),
    confirmButtonColor: '#f43f5e',
  });

  if (reason !== undefined) {
    try {
      await api.post(`/transfers/${trf.id}/cancel`, { reason: reason || 'إلغاء من النظام' });
      Swal.fire({
        icon: 'success',
        title: trans('common.success'),
        text: trans('inventory.transfer_cancelled_success'),
        timer: 1500,
        showConfirmButton: false,
      });
      await fetchTransfers(pagination.value.current_page);
    } catch (err) {
      Swal.fire({
        icon: 'error',
        title: trans('common.error'),
        text: err.userMessage || trans('inventory.transfer_cancel_failed'),
      });
    }
  }
};

watch([searchQuery, fromStoreId, toStoreId, dateFrom, dateTo], () => {
  fetchTransfers(1);
});

onMounted(() => {
  loadStores();
  fetchTransfers(1);
});
</script>
