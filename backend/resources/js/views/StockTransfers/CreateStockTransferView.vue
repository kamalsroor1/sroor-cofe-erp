<template>
  <div class="max-w-4xl mx-auto space-y-6 font-tajawal">
    <!-- Page Header -->
    <PageHeader :title="$t('inventory.new_transfer')" :subtitle="$t('inventory.transfers_subtitle')" icon="truck">
      <template #actions>
        <router-link
          to="/stock-transfers"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
        >
          <ArrowRight class="ltr:rotate-180 w-4 h-4" />
          <span>{{ $t('inventory.back_to_transfers') }}</span>
        </router-link>
      </template>
    </PageHeader>

    <InlineErrorBar v-if="error" :message="errorMessage" @retry="retry" />

    <form @submit.prevent="submitTransfer" class="space-y-6">
      <!-- Branches & Date Selection Card -->
      <CreateStockTransferHeaderCard
        :form="form"
        @update:form="Object.assign(form, $event)"
        :from-store-options="fromStoreOptions"
        :to-store-options="toStoreOptions"
      />

      <!-- Transferred Items Card -->
      <CreateStockTransferItemsCard
        :items="form.items"
        v-model:selected-item="selectedItem"
        :search-fn="searchItems"
        @add-item="addItemRow"
        @remove-item="removeItemRow"
      />

      <!-- Submit Action Button -->
      <BaseButton
        variant="primary"
        size="lg"
        type="submit"
        :loading="isSubmitting"
        :disabled="isSubmitting || form.items.length === 0"
        class="w-full shadow-xl shadow-theme-primary font-black text-sm"
      >
        <Truck class="w-5 h-5" />
        <span>{{ $t('inventory.execute_transfer_now_btn') }}</span>
      </BaseButton>
    </form>
  </div>
</template>

<script setup>
import { ArrowRight, Truck } from 'lucide-vue-next';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import CreateStockTransferHeaderCard from '../../Components/StockTransfers/CreateStockTransferHeaderCard.vue';
import CreateStockTransferItemsCard from '../../Components/StockTransfers/CreateStockTransferItemsCard.vue';
import { useCreateStockTransfer } from '../../Composables/useCreateStockTransfer';

const {
  error,
  errorMessage,
  retry,
  form,
  isSubmitting,
  selectedItem,
  fromStoreOptions,
  toStoreOptions,
  searchItems,
  addItemRow,
  removeItemRow,
  submitTransfer,
} = useCreateStockTransfer();
</script>
