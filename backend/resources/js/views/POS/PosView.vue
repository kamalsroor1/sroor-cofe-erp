<template>
  <div
    class="h-full w-full max-h-full min-h-0 overflow-hidden flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950 select-none"
    dir="rtl"
  >
    <!-- 🔄 POS Skeleton Loading State -->
    <POSSkeleton v-if="isLoading" />

    <div
      v-else
      class="h-full max-h-full min-h-0 overflow-y-auto lg:overflow-hidden bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col"
    >
      <!-- 🔝 1. Header & Search Command Bar -->
      <POSHeader
        ref="headerRef"
        :app-version="appVersion"
        :active-store="activeStore"
        :stores="authStore.stores"
        :active-shift="activeShift"
        :show-catalog="showCatalog"
        v-model:search-query="searchQuery"
        v-model:is-search-focused="isSearchFocused"
        v-model:highlighted-index="highlightedIndex"
        v-model:active-price-tier="activePriceTier"
        :search-results="searchDropdownResults"
        :selected-customer="selectedCustomer"
        :cart-empty="cart.length === 0"
        :is-searching="isSearchingRemote"
        @toggle-catalog="toggleCatalog"
        @add-item="addItemFromDropdown"
        @navigate-dropdown="navigateDropdown"
        @select-highlighted="selectHighlightedOrFirstItem"
        @close-dropdown="isSearchFocused = false"
        @open-customer-picker="showCustomerPickerModal = true"
        @clear-cart="clearCart"
        @switch-store="handleSwitchStore"
      />

      <!-- 🖥️ 2. Main Workspace: Hybrid Layout (Cart [DOMINANT HERO - flex-1] + Compact 3-Column Best Sellers) -->
      <div class="flex-1 flex flex-col lg:flex-row overflow-y-auto lg:overflow-hidden min-h-0">
        <!-- 🛒 Invoice Cart & Payment Checkout Panel (DOMINANT HERO - Takes maximum width) -->
        <section
          class="flex-1 flex flex-col justify-between p-3.5 bg-slate-50 dark:bg-slate-950 border-e border-slate-200 dark:border-slate-800 overflow-visible lg:overflow-hidden order-2 lg:order-1 min-w-0 transition-all duration-200 min-h-[380px] lg:min-h-0"
        >
          <!-- Top Section: Cart Items Table with Integrated Order Tabs Header -->
          <div class="flex-1 overflow-visible lg:overflow-hidden flex flex-col min-h-[160px] lg:min-h-[220px]">
            <POSCartTable
              :cart="cart"
              :total-qty="cartTotalQuantity"
              @increase-qty="increaseCartItemQty"
              @decrease-qty="decreaseCartItemQty"
              @update-qty="onCartQtyUpdate"
              @update-price="onCartPriceUpdate"
              @remove-item="removeFromCart"
            >
              <template #header>
                <POSOrderTabs
                  :orders="orders"
                  :active-order-id="activeOrderId"
                  @switch-order="switchOrder"
                  @create-order="handleCreateOrder"
                  @close-order="handleCloseOrder"
                />
              </template>
            </POSCartTable>
          </div>

          <!-- Bottom Section: Checkout & Financial Panel -->
          <div class="shrink-0 pt-2 border-t border-slate-200 dark:border-slate-800">
            <POSCheckoutPanel
              :cart-count="cart.length"
              :subtotal="cartSubtotal"
              :discount-amount="discountAmount"
              :discount-value="discountValue"
              :discount-type="discountType"
              :customer-expenses-total="customerExpensesTotal"
              :net-total="cartNetTotal"
              v-model:payment-type="paymentType"
              v-model:payment-method="paymentMethod"
              v-model:cash-received="cashReceived"
              :change-due="changeDue"
              :cart-empty="cart.length === 0"
              :is-submitting="isSubmitting"
              :offline="!isOnline"
              :expenses-count="additionalExpenses.length"
              @apply-discount="applyDiscountPreset"
              @submit="submitInvoice"
              @open-expenses="showExpensesModal = true"
              @open-multi-payment="openMultiPayment"
            />
          </div>
        </section>

        <!-- 🍕 Side: Visual Product Grid Catalog (Compact 3-Column Width) -->
        <main
          v-if="showCatalog"
          class="w-full lg:w-[350px] xl:w-[400px] 2xl:w-[440px] flex flex-col overflow-visible lg:overflow-hidden shrink-0 bg-slate-100/60 dark:bg-slate-900/60 border-b lg:border-b-0 lg:border-e border-slate-200 dark:border-slate-800 order-1 lg:order-2 animate-in fade-in duration-150 min-h-[300px] lg:min-h-0"
        >
          <POSProductGrid
            :items="items"
            :categories="categories"
            :active-category-id="activeCategoryId"
            :active-price-tier="activePriceTier"
            :search-query="searchQuery"
            @add-item="addToCart"
          />
        </main>

        <!-- 📂 Right (in RTL): Compact Vertical Category Sidebar -->
        <POSCategorySidebar
          v-if="showCatalog"
          class="order-3 animate-in fade-in duration-150 shrink-0"
          :categories="categories"
          :active-category-id="activeCategoryId"
          :favorite-count="favoriteItemsCount"
          :total-items-count="totalItemsCount"
          @select-category="handleCategorySelect"
        />
      </div>
    </div>

    <!-- 👥 Customer Picker Modal -->
    <POSCustomerModal
      :show="showCustomerPickerModal"
      v-model:search-query="customerSearchQuery"
      :customers="filteredCustomerList"
      :selected-customer-id="selectedCustomerId"
      :is-searching="isSearchingCustomers"
      :is-submitting="isSubmittingQuickCustomer"
      :offline="!isOnline"
      @close="showCustomerPickerModal = false"
      @select-customer="selectCustomer"
      @create-customer="handleQuickCustomerSubmit"
    />

    <!-- 🎉 Success Modal -->
    <POSSuccessModal
      :show="showSuccessModal"
      :invoice="lastCreatedInvoice"
      :change-amount="lastChangeAmount"
      @close="showSuccessModal = false"
      @print="printLastInvoice"
    />

    <!-- 🚛 Expenses Modal (Shipping & Additional Services) -->
    <POSExpensesModal
      :show="showExpensesModal"
      :expenses="additionalExpenses"
      @close="showExpensesModal = false"
      @update:expenses="
        (val) => {
          additionalExpenses = val;
          showExpensesModal = false;
        }
      "
    />

    <!-- 💳 Multi-Payment Split Modal -->
    <POSMultiPaymentModal
      :show="showMultiPaymentModal"
      :net-total="checkoutNet"
      :payments="multiPayments"
      :payment-type="paymentType === 'partial' ? 'partial' : 'cash'"
      @close="showMultiPaymentModal = false"
      @confirm="handleMultiPaymentConfirm"
    />
  </div>
</template>

<script setup>
defineOptions({
  name: 'pos.index',
});

import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import { newUuid } from '../../helpers/uuid';
import versionData from '../../version.json';

import POSHeader from '../../Components/POS/POSHeader.vue';
import POSCartTable from '../../Components/POS/POSCartTable.vue';
import POSOrderTabs from '../../Components/POS/POSOrderTabs.vue';
import POSCheckoutPanel from '../../Components/POS/POSCheckoutPanel.vue';
import POSCustomerModal from '../../Components/POS/POSCustomerModal.vue';
import POSSuccessModal from '../../Components/POS/POSSuccessModal.vue';
import POSSkeleton from '../../Components/POS/POSSkeleton.vue';
import POSCategorySidebar from '../../Components/POS/POSCategorySidebar.vue';
import POSProductGrid from '../../Components/POS/POSProductGrid.vue';
import POSExpensesModal from '../../Components/POS/POSExpensesModal.vue';
import POSMultiPaymentModal from '../../Components/POS/POSMultiPaymentModal.vue';
import { useAppConfigStore } from '../../stores/appConfig';
import { useAuthStore } from '../../stores/auth';
import { useDesktopHardware } from '../../Composables/useDesktopHardware';
import { useAudioFeedback } from '../../Composables/useAudioFeedback';
import { useFormatters } from '../../Composables/useFormatters';
import { usePosOrders } from '../../Composables/usePosOrders';
import { usePosCheckout } from '../../Composables/usePosCheckout';
import { useConnectivity } from '../../Composables/useConnectivity';
import { normalize, dSum, isPositive } from '../../helpers/decimal';
import {
  increaseLineQty,
  decreaseLineQty,
  updateLineQty,
  updateLinePrice,
  removeLine,
} from '../../helpers/posCartLines';

const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const { isDesktop, printThermalReceipt, openCashDrawer } = useDesktopHardware();
const { playScanBeep, playSuccessChime, playDrawerSound, playErrorTone } = useAudioFeedback();
const { formatMoney } = useFormatters();
// OFFL-1: no offline queue yet (end of Phase 2) — every server write is blocked while offline.
const { isOnline } = useConnectivity();

const {
  orders,
  activeOrderId,
  activeOrder,
  loadOrders,
  saveOrders,
  createNewOrder,
  switchOrder,
  closeOrder,
  clearActiveOrder,
} = usePosOrders();

const appVersion = ref(versionData?.version || '1.0.10');
const headerRef = ref(null);

const items = ref([]);
const categories = ref([]);
const customers = ref([]);
const totalItemsCount = ref(0);
const isLoadingCategoryItems = ref(false);
const activeStore = ref(null);
const activeShift = ref(null);
const activeCategoryId = ref('favorites');

const favoriteItemsCount = computed(() => {
  return (
    items.value.filter((i) => (i.pos_sales_count || 0) > 0 || i.is_pos_pinned).length ||
    Math.min(items.value.length, 20)
  );
});

const showCatalog = ref(localStorage.getItem('pos_show_catalog') !== 'false');
const toggleCatalog = () => {
  showCatalog.value = !showCatalog.value;
  localStorage.setItem('pos_show_catalog', showCatalog.value ? 'true' : 'false');
};

const isLoading = ref(true);
const isSubmitting = ref(false);

const cart = computed({
  get: () => activeOrder.value?.cart || [],
  set: (val) => {
    if (activeOrder.value) activeOrder.value.cart = val;
  },
});

const selectedCustomerId = computed({
  get: () => activeOrder.value?.selectedCustomerId ?? null,
  set: (val) => {
    if (activeOrder.value) activeOrder.value.selectedCustomerId = val;
  },
});

const activePriceTier = computed({
  get: () => activeOrder.value?.activePriceTier || 'retail',
  set: (val) => {
    if (activeOrder.value) activeOrder.value.activePriceTier = val;
  },
});

const discountType = computed({
  get: () => activeOrder.value?.discountType || 'percentage',
  set: (val) => {
    if (activeOrder.value) activeOrder.value.discountType = val;
  },
});

const discountValue = computed({
  get: () => activeOrder.value?.discountValue ?? '0',
  set: (val) => {
    if (activeOrder.value) activeOrder.value.discountValue = val;
  },
});

const paymentType = computed({
  get: () => activeOrder.value?.paymentType || 'cash',
  set: (val) => {
    if (activeOrder.value) activeOrder.value.paymentType = val;
  },
});

const paymentMethod = computed({
  get: () => activeOrder.value?.paymentMethod || 'cash',
  set: (val) => {
    if (activeOrder.value) activeOrder.value.paymentMethod = val;
  },
});

const paidAmount = computed({
  get: () => activeOrder.value?.paidAmount ?? '0.000',
  set: (val) => {
    if (activeOrder.value) activeOrder.value.paidAmount = val;
  },
});

const cashReceived = computed({
  get: () => activeOrder.value?.cashReceived ?? '0.000',
  set: (val) => {
    if (activeOrder.value) activeOrder.value.cashReceived = val;
  },
});

const additionalExpenses = computed({
  get: () => activeOrder.value?.additionalExpenses || [],
  set: (val) => {
    if (activeOrder.value) activeOrder.value.additionalExpenses = val;
  },
});

const searchQuery = ref('');
const isSearchFocused = ref(false);
const highlightedIndex = ref(0);

const showCustomerPickerModal = ref(false);
const customerSearchQuery = ref('');
const isSubmittingQuickCustomer = ref(false);

const showSuccessModal = ref(false);
const lastCreatedInvoice = ref(null);
const lastChangeAmount = ref('0.000');
const showExpensesModal = ref(false);
const showMultiPaymentModal = ref(false);
const multiPayments = ref([]);

const getItemPrice = (item) => {
  if (!item) return 0;
  const retail = parseFloat(item.selling_price ?? item.price_retail ?? item.price ?? 0);
  const wholesale = parseFloat(item.min_selling_price ?? item.price_wholesale ?? retail);
  return activePriceTier.value === 'wholesale' ? (wholesale > 0 ? wholesale : retail) : retail > 0 ? retail : wholesale;
};

const isSearchingRemote = ref(false);
const remoteSearchResults = ref([]);
let searchDebounceTimer = null;
let searchAbortController = null;

const performRemoteSearch = (query) => {
  // 1. Cancel any pending debounce timer
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = null;
  }

  // 2. Cancel any active in-flight HTTP request
  if (searchAbortController) {
    searchAbortController.abort();
    searchAbortController = null;
  }

  const q = query.trim();
  if (!q) {
    remoteSearchResults.value = [];
    isSearchingRemote.value = false;
    return;
  }

  // 3. Debounce by 250ms so fast typing doesn't spam the server
  searchDebounceTimer = setTimeout(async () => {
    searchAbortController = new AbortController();
    isSearchingRemote.value = true;

    try {
      const res = await api.get('/items', {
        params: { search: q, per_page: 30 },
        signal: searchAbortController.signal,
      });
      remoteSearchResults.value = res.data?.data || [];
      isSearchingRemote.value = false;
    } catch (err) {
      // Gracefully ignore cancellation when user types new character
      if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED' || err.message === 'canceled') {
        return;
      }
      console.error('Remote item search error:', err);
      isSearchingRemote.value = false;
    } finally {
      if (searchAbortController && !searchAbortController.signal.aborted) {
        searchAbortController = null;
      }
    }
  }, 250);
};

watch(searchQuery, (newVal) => {
  highlightedIndex.value = 0;
  performRemoteSearch(newVal);
});

const isSearchingCustomers = ref(false);
const remoteCustomerResults = ref([]);
let customerSearchDebounceTimer = null;
let customerSearchAbortController = null;

const performRemoteCustomerSearch = (query) => {
  if (customerSearchDebounceTimer) {
    clearTimeout(customerSearchDebounceTimer);
    customerSearchDebounceTimer = null;
  }
  if (customerSearchAbortController) {
    customerSearchAbortController.abort();
    customerSearchAbortController = null;
  }

  const q = query.trim();
  if (!q) {
    remoteCustomerResults.value = [];
    isSearchingCustomers.value = false;
    return;
  }

  customerSearchDebounceTimer = setTimeout(async () => {
    customerSearchAbortController = new AbortController();
    isSearchingCustomers.value = true;
    try {
      const res = await api.get('/customers', {
        params: { search: q, per_page: 30 },
        signal: customerSearchAbortController.signal,
      });
      remoteCustomerResults.value = res.data?.data || res.data?.customers || [];
      isSearchingCustomers.value = false;
    } catch (err) {
      if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED' || err.message === 'canceled') {
        return;
      }
      console.error('Remote customer search error:', err);
      isSearchingCustomers.value = false;
    } finally {
      if (customerSearchAbortController && !customerSearchAbortController.signal.aborted) {
        customerSearchAbortController = null;
      }
    }
  }, 250);
};

watch(customerSearchQuery, (newVal) => {
  performRemoteCustomerSearch(newVal);
});

const searchDropdownResults = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return [];

  // 1. Instant local filter
  const localMatches = items.value.filter(
    (i) => (i.name && i.name.toLowerCase().includes(q)) || (i.code && i.code.toLowerCase().includes(q))
  );

  // 2. Merge with remote 10,000-items database matches (deduplicated by ID)
  const mergedMap = new Map();
  localMatches.forEach((item) => mergedMap.set(item.id, item));
  remoteSearchResults.value.forEach((item) => mergedMap.set(item.id, item));

  return Array.from(mergedMap.values()).slice(0, 15);
});

const selectedCustomer = computed(() => {
  if (!selectedCustomerId.value) return { id: null, name: trans('pos.general_cash_customer'), phone: '' };
  return (
    customers.value.find((c) => c.id === selectedCustomerId.value) || {
      id: null,
      name: trans('pos.general_cash_customer'),
      phone: '',
    }
  );
});

const filteredCustomerList = computed(() => {
  const q = customerSearchQuery.value.trim().toLowerCase();
  if (!q) return customers.value;

  const localMatches = customers.value.filter(
    (c) => (c.name && c.name.toLowerCase().includes(q)) || (c.phone && c.phone.includes(q))
  );

  const mergedMap = new Map();
  localMatches.forEach((c) => mergedMap.set(c.id, c));
  remoteCustomerResults.value.forEach((c) => mergedMap.set(c.id, c));

  return Array.from(mergedMap.values());
});

const cartSubtotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + parseFloat(item.quantity) * parseFloat(item.unit_price), 0);
});

const cartTotalQuantity = computed(() => {
  return cart.value.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0);
});

const discountAmount = computed(() => {
  const val = parseFloat(discountValue.value) || 0;
  if (val <= 0) return 0;
  if (discountType.value === 'percentage') return (cartSubtotal.value * val) / 100;
  return Math.min(val, cartSubtotal.value);
});

const customerExpensesTotal = computed(() => {
  return additionalExpenses.value
    .filter((exp) => (exp.paid_by || 'customer_account') === 'customer_account')
    .reduce((sum, exp) => sum + (parseFloat(exp.amount) || 0), 0);
});

// Exact (scale 3) checkout math lives in usePosCheckout; the numbers below are display previews only.
const { checkoutNet, changePreview, buildPayment, buildPayloadLines } = usePosCheckout({
  cart,
  discountType,
  discountValue,
  additionalExpenses,
  paymentType,
  paymentMethod,
  cashReceived,
  multiPayments,
});

const cartNetTotal = computed(() => Number(checkoutNet.value));
const changeDue = computed(() => Number(changePreview.value));

watch(checkoutNet, (newNet) => {
  // A split built for the previous net must never be sent; the server rejects mismatches.
  multiPayments.value = [];
  if (paymentType.value === 'cash') {
    paidAmount.value = normalize(newNet);
    cashReceived.value = normalize(newNet);
  } else if (paymentType.value === 'credit') {
    paidAmount.value = '0.000';
    cashReceived.value = '0.000';
  }
});

// A split belongs to the payment type it was built for.
watch(paymentType, () => {
  multiPayments.value = [];
});

// Idempotency key lives per checkout attempt: any change to what is being sold starts a new one.
const checkoutFingerprint = computed(() =>
  JSON.stringify([
    cart.value.map((i) => [i.id, i.quantity, i.unit_price]),
    discountType.value,
    discountValue.value,
    paymentType.value,
    paymentMethod.value,
    multiPayments.value,
    additionalExpenses.value,
  ])
);

watch([activeOrderId, checkoutFingerprint], ([orderId, fingerprint], [prevOrderId, prevFingerprint]) => {
  if (orderId !== prevOrderId || fingerprint === prevFingerprint) return;
  if (activeOrder.value?.checkoutUuid) activeOrder.value.checkoutUuid = null;
});

const addToCart = (item, qty = 1) => {
  playScanBeep();
  const existing = cart.value.find((c) => c.id === item.id);
  const price = getItemPrice(item);
  if (existing) {
    existing.quantity = parseFloat(existing.quantity) + qty;
  } else {
    cart.value.push({
      id: item.id,
      name: item.name,
      code: item.code,
      unit: item.unit,
      unit_price: price,
      price_retail: item.price_retail,
      price_wholesale: item.price_wholesale,
      min_selling_price: item.min_selling_price,
      quantity: qty,
    });
  }
};

const addItemFromDropdown = (item) => {
  addToCart(item);
  searchQuery.value = '';
  isSearchFocused.value = false;
  highlightedIndex.value = 0;
  headerRef.value?.focusSearch();
};

const navigateDropdown = (direction) => {
  if (searchDropdownResults.value.length === 0) return;
  if (direction === 'down') {
    highlightedIndex.value = (highlightedIndex.value + 1) % searchDropdownResults.value.length;
  } else if (direction === 'up') {
    highlightedIndex.value =
      (highlightedIndex.value - 1 + searchDropdownResults.value.length) % searchDropdownResults.value.length;
  }
};

const selectHighlightedOrFirstItem = () => {
  if (searchDropdownResults.value.length > 0) {
    const item = searchDropdownResults.value[highlightedIndex.value] || searchDropdownResults.value[0];
    addItemFromDropdown(item);
  }
};

const increaseCartItemQty = (idx) => increaseLineQty(cart.value, idx);
const decreaseCartItemQty = (idx) => decreaseLineQty(cart.value, idx);
const onCartQtyUpdate = (change) => updateLineQty(cart.value, change);
const onCartPriceUpdate = (change) => updateLinePrice(cart.value, change);
const removeFromCart = (idx) => removeLine(cart.value, idx);

const clearCart = () => {
  if (cart.value.length === 0) return;
  cart.value = [];
  discountValue.value = '0';
  additionalExpenses.value = [];
  multiPayments.value = [];
  cashReceived.value = '0.000';
  saveOrders();
  headerRef.value?.focusSearch();
};

const handleCreateOrder = () => {
  createNewOrder();
  nextTick(() => {
    headerRef.value?.focusSearch();
  });
};

const handleCloseOrder = async (order) => {
  if (order.cart && order.cart.length > 0) {
    const subtotal = order.cart.reduce(
      (s, i) => s + (parseFloat(i.quantity) || 0) * (parseFloat(i.unit_price) || 0),
      0
    );
    const result = await Swal.fire({
      title: trans('pos.confirm_close_order_title'),
      text: trans('pos.confirm_close_order_text', {
        count: order.cart.length,
        total: `${formatMoney(subtotal)} ج.م`,
      }),
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: trans('pos.confirm_close_order_btn'),
      cancelButtonText: trans('common.cancel'),
      confirmButtonColor: '#e11d48',
    });
    if (!result.isConfirmed) return;
  }
  closeOrder(order.id);
  headerRef.value?.focusSearch();
};

const applyDiscountPreset = ({ value, type }) => {
  discountValue.value = value.toString();
  discountType.value = type;
};

const openMultiPayment = () => {
  // A credit order has no payments; switch to cash visibly instead of splitting behind the cashier's back.
  if (paymentType.value === 'credit') paymentType.value = 'cash';
  showMultiPaymentModal.value = true;
};

// The payment type stays what the cashier chose; the modal already validated the split for it.
const handleMultiPaymentConfirm = (payments) => {
  const lines = payments.map((p) => ({ method: p.method, amount: normalize(p.amount) }));
  const totalPaid = dSum(lines.map((p) => p.amount));
  multiPayments.value = lines;
  showMultiPaymentModal.value = false;
  paidAmount.value = paymentType.value === 'partial' ? totalPaid : checkoutNet.value;
  cashReceived.value = totalPaid;
};

const selectCustomer = (cust) => {
  selectedCustomerId.value = cust.id;
  showCustomerPickerModal.value = false;
};

const handleQuickCustomerSubmit = async ({ name, phone }) => {
  if (!isOnline.value) return;
  isSubmittingQuickCustomer.value = true;
  try {
    const res = await api.post('/customers', { name, phone });
    const newCust = res.data?.data;
    if (newCust) {
      customers.value.unshift(newCust);
      selectedCustomerId.value = newCust.id;
      showCustomerPickerModal.value = false;
    }
  } catch (e) {
    Swal.fire({ icon: 'error', title: trans('common.error'), text: e.userMessage || trans('pos.add_customer_failed') });
  } finally {
    isSubmittingQuickCustomer.value = false;
  }
};

const fetchPOSInitialData = async () => {
  activeStore.value = authStore.currentStore;
  isLoading.value = true;
  try {
    const [itemsRes, customersRes, shiftRes, categoriesRes] = await Promise.all([
      api.get('/items', { params: { per_page: 300 } }),
      api.get('/customers', { params: { per_page: 200 } }),
      api.get('/shifts/current').catch(() => ({ data: { data: null } })),
      api.get('/categories', { params: { active_only: true } }).catch(() => ({ data: { data: [] } })),
    ]);
    items.value = itemsRes.data?.data || [];
    customers.value = customersRes.data?.data || [];
    activeShift.value = shiftRes.data?.data || null;
    categories.value = categoriesRes.data?.data || [];
    totalItemsCount.value =
      categoriesRes.data?.total_items_count ||
      itemsRes.data?.meta?.total ||
      itemsRes.data?.summary?.total_items ||
      items.value.length;
  } catch (e) {
    console.error('Failed to load POS data:', e);
  } finally {
    isLoading.value = false;
  }
};

const handleCategorySelect = async (catId) => {
  activeCategoryId.value = catId;
  if (!catId) return;

  isLoadingCategoryItems.value = true;
  try {
    const params = { per_page: 200 };
    params.category_id = catId;
    const res = await api.get('/items', { params });
    if (res.data?.data) {
      const newItems = res.data.data;
      const existingIds = new Set(items.value.map((i) => i.id));
      const toAdd = newItems.filter((i) => !existingIds.has(i.id));
      if (toAdd.length > 0) {
        items.value = [...items.value, ...toAdd];
      }
    }
  } catch (err) {
    console.error('Failed to load category items:', err);
  } finally {
    isLoadingCategoryItems.value = false;
  }
};

const handleSwitchStore = async (storeId) => {
  const store = authStore.stores?.find((s) => String(s.id) === String(storeId));
  if (store) {
    authStore.switchStore(store);
    activeStore.value = store;
    try {
      const [itemsRes, categoriesRes] = await Promise.all([
        api.get('/items', { params: { per_page: 300 } }),
        api.get('/categories', { params: { active_only: true } }).catch(() => ({ data: { data: [] } })),
      ]);
      items.value = itemsRes.data?.data || [];
      categories.value = categoriesRes.data?.data || [];
      totalItemsCount.value =
        categoriesRes.data?.total_items_count ||
        itemsRes.data?.meta?.total ||
        itemsRes.data?.summary?.total_items ||
        items.value.length;
    } catch (e) {
      console.error('Failed to refresh items for switched store:', e);
    }
  }
};

const submitInvoice = async (printImmediately = false) => {
  if (isSubmitting.value) return;
  if (!isOnline.value) {
    playErrorTone();
    Swal.fire({
      icon: 'warning',
      title: trans('connectivity.checkout_blocked'),
      text: trans('connectivity.checkout_blocked_desc'),
    });
    return;
  }
  if (cart.value.length === 0) {
    Swal.fire({ icon: 'warning', title: trans('pos.empty_cart_error'), timer: 1500, showConfirmButton: false });
    return;
  }

  const payment = buildPayment();
  if (!payment.valid) {
    Swal.fire({ icon: 'warning', title: trans('pos.partial_amount_invalid') });
    return;
  }

  // Reused on retry after an error/timeout: the first request may already have committed.
  if (!activeOrder.value.checkoutUuid) activeOrder.value.checkoutUuid = newUuid();
  const checkoutUuid = activeOrder.value.checkoutUuid;
  saveOrders();

  isSubmitting.value = true;
  try {
    const payload = {
      client_uuid: checkoutUuid,
      store_id: activeStore.value?.id || 1,
      customer_id: selectedCustomerId.value,
      payment_type: paymentType.value,
      payment_method: paymentType.value === 'credit' ? null : paymentMethod.value,
      discount_type: discountType.value,
      ...buildPayloadLines(),
      paid_amount: payment.paidAmount,
      payments: payment.payments,
    };

    const res = await api.post('/invoices', payload, { headers: { 'Idempotency-Key': checkoutUuid } });
    lastCreatedInvoice.value = res.data?.data;
    // The server owns the change; the panel value was only a preview.
    lastChangeAmount.value = normalize(res.data?.data?.change_amount ?? '0');
    playSuccessChime();

    if (printImmediately && lastCreatedInvoice.value?.id) {
      printLastInvoice();
      // The cashier still has to see the change to hand back.
      if (isPositive(lastChangeAmount.value)) showSuccessModal.value = true;
    } else {
      showSuccessModal.value = true;
    }

    // Clear completed order & switch to remaining or fresh order
    if (activeOrder.value) activeOrder.value.checkoutUuid = null;
    clearActiveOrder();
    headerRef.value?.focusSearch();
  } catch (e) {
    playErrorTone();
    // 401 and 403 are already handled (redirect / alert) by the api interceptor.
    const handledByInterceptor = [401, 403].includes(e.response?.status);
    if (!handledByInterceptor) {
      Swal.fire({
        icon: 'error',
        title: trans('pos.checkout_failed'),
        text: e.userMessage || trans('pos.checkout_failed_desc'),
      });
    }
  } finally {
    isSubmitting.value = false;
  }
};

const printLastInvoice = async () => {
  if (!lastCreatedInvoice.value?.id) return;

  if (isDesktop.value) {
    try {
      const res = await api.get(`/invoices/${lastCreatedInvoice.value.id}`);
      const inv = res.data?.data || lastCreatedInvoice.value;

      const itemsRows = (inv.items || [])
        .map(
          (item) => `
        <tr>
          <td style="text-align: right; padding: 2px 0;">${item.item_name || item.name}</td>
          <td style="text-align: center; padding: 2px 0;">${parseFloat(item.quantity)}</td>
          <td style="text-align: left; padding: 2px 0;">${parseFloat(item.total_price || 0).toFixed(2)}</td>
        </tr>
      `
        )
        .join('');

      const thermalHtml = `
        <div style="font-family: sans-serif; font-size: 11px; text-align: center;">
          <h2 style="margin: 0 0 4px 0; font-size: 14px;">${appConfigStore.companyName || appConfigStore.platformName}</h2>
          <p style="margin: 0; font-size: 10px;">فاتورة مبيعات رقم: #${inv.invoice_number}</p>
          <p style="margin: 2px 0; font-size: 9px; color: #555;">${inv.invoice_date || new Date().toLocaleString('ar-EG')}</p>
          <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
          <table style="width: 100%; font-size: 10px; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 1px solid #000;">
                <th style="text-align: right; padding-bottom: 2px;">الصنف</th>
                <th style="text-align: center; padding-bottom: 2px;">الكمية</th>
                <th style="text-align: left; padding-bottom: 2px;">الإجمالي</th>
              </tr>
            </thead>
            <tbody>
              ${itemsRows}
            </tbody>
          </table>
          <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
          <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; margin: 4px 0;">
            <span>الصافي النهائي:</span>
            <span>${parseFloat(inv.net_total || inv.net_amount || 0).toFixed(2)} ج.م</span>
          </div>
          <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
          <p style="margin: 4px 0; font-size: 9px;">شكراً لزيارتكم! ☕</p>
        </div>
      `;

      await printThermalReceipt(thermalHtml);
      if (inv.payment_type === 'cash' || paymentType.value === 'cash') {
        playDrawerSound();
        await openCashDrawer();
      }
      return;
    } catch (err) {
      console.warn('[DesktopPOS] Silent print fallback to browser popup:', err);
    }
  }

  // Fallback for Web Browser
  window.open(`/invoices/${lastCreatedInvoice.value.id}/print?autoprint=true`, '_blank', 'width=800,height=600');
};

const handleGlobalKeydown = (e) => {
  if (e.key === 'F2') {
    e.preventDefault();
    headerRef.value?.focusSearch();
  } else if (e.key === 'F4') {
    e.preventDefault();
    handleCreateOrder();
  } else if (e.key === 'F10') {
    e.preventDefault();
    toggleCatalog();
  } else if (e.key === 'F9' || (e.ctrlKey && e.key === 'Enter')) {
    e.preventDefault();
    if (!isSubmitting.value && !showSuccessModal.value && cart.value.length > 0) {
      submitInvoice(false);
    }
  } else if (e.key === 'Enter' && showSuccessModal.value) {
    showSuccessModal.value = false;
    headerRef.value?.focusSearch();
  }
};

onMounted(() => {
  loadOrders();
  fetchPOSInitialData();
  window.addEventListener('keydown', handleGlobalKeydown);
  nextTick(() => headerRef.value?.focusSearch());
});

onBeforeUnmount(() => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  if (searchAbortController) searchAbortController.abort();
  if (customerSearchDebounceTimer) clearTimeout(customerSearchDebounceTimer);
  if (customerSearchAbortController) customerSearchAbortController.abort();
  window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>
