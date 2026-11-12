<script setup>
defineOptions({
  name: 'pos.index',
});

import POSHeader from '../../Components/POS/POSHeader.vue';
import POSCartSection from '../../Components/POS/POSCartSection.vue';
import POSCatalogSection from '../../Components/POS/POSCatalogSection.vue';
import POSCustomerModal from '../../Components/POS/POSCustomerModal.vue';
import POSSuccessModal from '../../Components/POS/POSSuccessModal.vue';
import ErrorState from '../../Components/Common/ErrorState.vue';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import POSSkeleton from '../../Components/POS/POSSkeleton.vue';
import POSExpensesModal from '../../Components/POS/POSExpensesModal.vue';
import POSMultiPaymentModal from '../../Components/POS/POSMultiPaymentModal.vue';
import POSShortcutsModal from '../../Components/POS/POSShortcutsModal.vue';
import POSRecentSalesDrawer from '../../Components/POS/POSRecentSalesDrawer.vue';
import POSQuickReturnModal from '../../Components/POS/POSQuickReturnModal.vue';
import POSWeightPickerModal from '../../Components/POS/POSWeightPickerModal.vue';
import POSShiftGate from '../../Components/POS/POSShiftGate.vue';
import POSShiftPanel from '../../Components/POS/POSShiftPanel.vue';
import { usePosTerminal } from '../../Composables/POS/usePosTerminal';
import { useCompactViewport } from '../../Composables/useMediaQuery';

const PARTIAL_ERROR_KEYS = {
  categories: 'pos.load_failed_categories',
  customers: 'pos.load_failed_customers',
  shift: 'pos.load_failed_shift',
  category_items: 'pos.load_failed_category_items',
  catalog_more: 'pos.catalog_load_more_failed',
};

// POS-12 / RESP-02: below DESKTOP_MIN (phones and tablets) the catalog gets a sticky cart bar + sheets;
// from DESKTOP_MIN up the cart sits beside the catalog. The cart state lives in usePosTerminal, so it
// survives a rotation between the two layouts.
const isCompact = useCompactViewport();

const {
  authStore,
  headerRef,
  lastCreatedInvoice,
  isOnline,
  orders,
  activeOrderId,
  fields,
  customer,
  catalog,
  payment,
  cartOps,
  search,
  printLastInvoice,
  isSubmitting,
  showSuccessModal,
  lastChangeAmount,
  submitInvoice,
  cartEmpty,
  orderApi,
  showShortcutsHelp,
  recentSales,
  quickReturn,
  reprintInvoice,
  canReturn,
  canOverrideDiscount,
  shift,
} = usePosTerminal();
</script>

<template>
  <div
    class="h-full w-full max-h-full min-h-0 overflow-hidden flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950 select-none"
  >
    <POSSkeleton v-if="catalog.isLoading.value" />

    <div
      v-else
      class="h-full max-h-full min-h-0 overflow-hidden bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col"
    >
      <POSHeader
        ref="headerRef"
        v-model:search-query="search.searchQuery.value"
        v-model:is-search-focused="search.isSearchFocused.value"
        v-model:highlighted-index="search.highlightedIndex.value"
        v-model:active-price-tier="fields.activePriceTier.value"
        :active-store="catalog.activeStore.value"
        :stores="authStore.stores"
        :active-shift="catalog.activeShift.value"
        :search-results="search.dropdownResults.value"
        :selected-customer="customer.selectedCustomer.value"
        :cart-empty="cartEmpty"
        :is-searching="search.isSearchingRemote.value"
        @add-item="search.addItemFromDropdown"
        @navigate-dropdown="search.navigateDropdown"
        @select-highlighted="search.selectHighlightedOrFirstItem"
        @close-dropdown="search.escapeSearch"
        @open-customer-picker="customer.showCustomerPickerModal.value = true"
        @clear-cart="cartOps.clearCart"
        @switch-store="catalog.switchStore"
        @open-recent-sales="recentSales.open"
        @open-shift-menu="shift.openPanel"
      />

      <ErrorState
        v-if="catalog.loadFailed.value"
        class="flex-1"
        :title="$t('pos.load_failed_title')"
        :message="$t('pos.load_failed_message')"
        @retry="catalog.retryInitial"
      />

      <template v-else>
        <div v-if="catalog.partialErrors.value.length" class="px-3 pt-3 shrink-0">
          <InlineErrorBar
            v-for="key in catalog.partialErrors.value"
            :key="key"
            class="!mb-2"
            :message="$t(PARTIAL_ERROR_KEYS[key])"
            @retry="catalog.retryPartial"
          />
        </div>

        <div class="relative flex-1 flex flex-col min-h-0">
          <div class="flex-1 flex flex-col lg:flex-row min-h-0 overflow-hidden">
            <POSCartSection
              :compact="isCompact"
              v-model:payment-type="fields.paymentType.value"
              v-model:payment-method="fields.paymentMethod.value"
              v-model:cash-received="fields.cashReceived.value"
              :cart="fields.cart.value"
              :total-qty="payment.cartTotalQuantity.value"
              :orders="orders"
              :active-order-id="activeOrderId"
              :subtotal="payment.cartSubtotal.value"
              :discount-amount="payment.discountAmount.value"
              :discount-value="fields.discountValue.value"
              :discount-type="fields.discountType.value"
              :customer-expenses-total="payment.customerExpensesTotal.value"
              :net-total="payment.cartNetTotal.value"
              :change-due="payment.changeDue.value"
              :walk-in="customer.isWalkInCustomer.value"
              :is-submitting="isSubmitting"
              :offline="!isOnline"
              :expenses-count="fields.additionalExpenses.value.length"
              :discount-summary="payment.discountSummary.value"
              :max-discount-percent="catalog.maxDiscountPercent.value"
              :can-override-discount="canOverrideDiscount"
              @increase-qty="cartOps.increaseCartItemQty"
              @decrease-qty="cartOps.decreaseCartItemQty"
              @update-qty="cartOps.onCartQtyUpdate"
              @update-price="cartOps.onCartPriceUpdate"
              @update-discount="cartOps.onCartDiscountUpdate"
              @remove-item="cartOps.removeFromCart"
              @switch-order="orderApi.switchOrder"
              @create-order="cartOps.handleCreateOrder"
              @close-order="cartOps.handleCloseOrder"
              @apply-discount="payment.applyDiscountPreset"
              @submit="submitInvoice"
              @open-expenses="payment.showExpensesModal.value = true"
              @open-multi-payment="payment.openMultiPayment"
            />

            <POSCatalogSection
              v-if="catalog.showCatalog.value"
              :items="catalog.items.value"
              :categories="catalog.categories.value"
              :active-category-id="catalog.activeCategoryId.value"
              :active-price-tier="fields.activePriceTier.value"
              :search-query="search.searchQuery.value"
              :favorite-count="catalog.favoriteItemsCount.value"
              :total-items-count="catalog.totalItemsCount.value"
              :visible-count="catalog.visibleCount.value"
              :is-loading="catalog.isLoadingCategoryItems.value"
              :is-loading-more="catalog.isLoadingMore.value"
              :has-more="catalog.hasMore.value"
              :cards-per-row="catalog.cardsPerRow.value"
              :show-images="catalog.showItemImages.value"
              @add-item="cartOps.addToCart"
              @select-category="catalog.selectCategory"
              @load-more="catalog.loadMore"
            />
          </div>

          <!-- POS-10: the branch needs an open shift before it can sell -->
          <POSShiftGate
            v-if="shift.gateVisible.value"
            :opening-cash="shift.openingCash.value"
            :can-manage="shift.canManageShift.value"
            :is-submitting="shift.isSubmitting.value"
            :error-message="shift.errorMessage.value"
            @update:opening-cash="shift.setOpeningCash"
            @submit="shift.submitOpen"
          />
        </div>
      </template>
    </div>

    <POSCustomerModal
      v-model:search-query="customer.customerSearchQuery.value"
      :show="customer.showCustomerPickerModal.value"
      :customers="customer.filteredCustomerList.value"
      :selected-customer-id="fields.selectedCustomerId.value"
      :is-searching="customer.isSearchingCustomers.value"
      :is-submitting="customer.isSubmittingQuickCustomer.value"
      :offline="!isOnline"
      @close="customer.showCustomerPickerModal.value = false"
      @select-customer="customer.selectCustomer"
      @create-customer="customer.createQuickCustomer"
    />

    <POSSuccessModal
      :show="showSuccessModal"
      :invoice="lastCreatedInvoice"
      :change-amount="lastChangeAmount"
      @close="showSuccessModal = false"
      @print="printLastInvoice"
    />

    <POSExpensesModal
      :show="payment.showExpensesModal.value"
      :expenses="fields.additionalExpenses.value"
      @close="payment.showExpensesModal.value = false"
      @update:expenses="payment.applyExpenses"
    />

    <POSMultiPaymentModal
      :show="payment.showMultiPaymentModal.value"
      :net-total="payment.checkoutNet.value"
      :payments="payment.multiPayments.value"
      :payment-type="fields.paymentType.value === 'partial' ? 'partial' : 'cash'"
      @close="payment.showMultiPaymentModal.value = false"
      @confirm="payment.confirmMultiPayment"
    />

    <POSRecentSalesDrawer
      :show="recentSales.show.value"
      :sales="recentSales.sales.value"
      :search="recentSales.search.value"
      :is-loading="recentSales.isLoading.value"
      :error-message="recentSales.errorMessage.value"
      :can-return="canReturn"
      :offline="!isOnline"
      @close="recentSales.close"
      @update:search="recentSales.onSearch"
      @retry="recentSales.load"
      @reprint="(sale) => reprintInvoice(sale.id)"
      @return="quickReturn.open"
    />

    <POSQuickReturnModal
      :show="quickReturn.show.value"
      :invoice="quickReturn.source.selected.value"
      :lines="quickReturn.source.lines.value"
      :is-loading="quickReturn.source.isLoadingLines.value"
      :error-message="quickReturn.source.errorMessage.value"
      :total="quickReturn.source.total.value"
      :can-submit="quickReturn.canSubmit.value"
      :is-submitting="quickReturn.isSubmitting.value"
      :refund-method="quickReturn.refund.method.value"
      :refund-locked="quickReturn.refund.locked.value"
      :refund-error="quickReturn.refund.capError.value"
      :refund-hint="quickReturn.refund.hint.value"
      @update:refund-method="quickReturn.refund.setMethod"
      @close="quickReturn.close"
      @set-quantity="quickReturn.setQuantity"
      @submit="quickReturn.submit"
    />

    <POSWeightPickerModal
      :show="!!cartOps.weightPickerItem.value"
      :item="cartOps.weightPickerItem.value || null"
      :unit-price="cartOps.weightPickerPrice.value"
      @close="cartOps.cancelWeight"
      @confirm="cartOps.confirmWeight"
    />

    <POSShiftPanel
      :show="shift.showPanel.value"
      :shift="catalog.activeShift.value"
      :metrics="catalog.shiftMetrics.value"
      :opening-cash="shift.openingCash.value"
      :counted-cash="shift.countedCash.value"
      :can-manage="shift.canManageShift.value"
      :is-submitting="shift.isSubmitting.value"
      :is-report-loading="shift.isReportLoading.value"
      :report-failed="shift.reportFailed.value"
      :error-message="shift.errorMessage.value"
      @close="shift.closePanel"
      @refresh="shift.refreshReport"
      @update:opening-cash="shift.setOpeningCash"
      @update:counted-cash="shift.setCountedCash"
      @submit-open="shift.submitOpen"
      @submit-close="shift.submitClose"
    />

    <POSShortcutsModal :show="showShortcutsHelp" @close="showShortcutsHelp = false" />
  </div>
</template>
