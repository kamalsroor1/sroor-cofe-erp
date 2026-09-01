<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('purchases.new_purchase')"
      :subtitle="$t('purchases.create_subtitle')"
      icon="🚛"
      back-href="/purchases"
    />

    <form @submit.prevent="submitPurchase" class="space-y-6">
      <!-- Supplier & Metadata Card -->
      <CreatePurchaseSupplierCard
        v-model:supplier-id="form.supplier_id"
        v-model:purchase-date="form.purchase_date"
        v-model:supplier-invoice-ref="form.supplier_invoice_ref"
        :supplier-options="supplierOptions"
      />

      <!-- Items Table Card -->
      <CreatePurchaseItemsCard
        :items="form.items"
        :available-items="availableItems"
        @add-line="addItemLine"
        @remove-line="removeItemLine"
        @item-select="onItemSelect"
      />

      <!-- Financial Summary Card -->
      <CreatePurchaseSummaryCard
        v-model:notes="form.notes"
        v-model:paid-amount="form.paid_amount"
        v-model:discount-amount="form.discount_amount"
        :subtotal="subtotal"
        :discount="discount"
        :net-total="netTotal"
        :remaining="remaining"
      />

      <!-- Submit Actions -->
      <div class="flex items-center justify-end gap-3">
        <router-link
          to="/purchases"
          class="min-h-[44px] px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold cursor-pointer flex items-center justify-center"
        >
          {{ $t('common.cancel') }}
        </router-link>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="isSubmitting"
          class="font-black shadow-theme-primary shadow-lg min-h-[44px]"
        >
          {{ $t('purchases.confirm_and_supply_btn') }}
        </BaseButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import CreatePurchaseSupplierCard from '../../Components/Purchases/CreatePurchaseSupplierCard.vue';
import CreatePurchaseItemsCard from '../../Components/Purchases/CreatePurchaseItemsCard.vue';
import CreatePurchaseSummaryCard from '../../Components/Purchases/CreatePurchaseSummaryCard.vue';
import { useCreatePurchase } from '../../Composables/useCreatePurchase';

const {
  form,
  availableItems,
  supplierOptions,
  isSubmitting,
  subtotal,
  discount,
  netTotal,
  remaining,
  addItemLine,
  removeItemLine,
  onItemSelect,
  submitPurchase,
} = useCreatePurchase();
</script>
