<template>
  <!-- 🔄 POS Skeleton Loading State -->
  <POSSkeleton v-if="isLoading" />

  <div v-else class="h-full max-h-full min-h-0 overflow-y-auto lg:overflow-hidden bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950 select-none" dir="rtl">
    
    <!-- 🔝 1. Header & Search Command Bar -->
    <POSHeader
      ref="headerRef"
      :app-version="appVersion"
      :active-store="activeStore"
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
    />

    <!-- 🖥️ 2. Main Workspace: Hybrid Layout (Cart [DOMINANT HERO - flex-1] + Compact 3-Column Best Sellers) -->
    <div class="flex-1 flex flex-col lg:flex-row overflow-y-auto lg:overflow-hidden min-h-0">
      
      <!-- 🛒 Invoice Cart & Payment Checkout Panel (DOMINANT HERO - Takes maximum width) -->
      <section
        class="flex-1 flex flex-col justify-between p-3.5 bg-slate-50 dark:bg-slate-950 border-e border-slate-200 dark:border-slate-800 overflow-visible lg:overflow-hidden order-2 lg:order-1 min-w-0 transition-all duration-200 min-h-[380px] lg:min-h-0"
      >
        <!-- Top Section: Cart Items Table -->
        <div class="flex-1 overflow-visible lg:overflow-hidden flex flex-col min-h-[160px] lg:min-h-[220px]">
          <POSCartTable
            :cart="cart"
            :total-qty="cartTotalQuantity"
            @increase-qty="increaseCartItemQty"
            @decrease-qty="decreaseCartItemQty"
            @update-qty="onCartQtyUpdate"
            @update-price="onCartPriceUpdate"
            @remove-item="removeFromCart"
          />
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
            @apply-discount="applyDiscountPreset"
            @submit="submitInvoice"
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
        @select-category="activeCategoryId = $event"
      />

    </div>

    <!-- 👥 Customer Picker Modal -->
    <POSCustomerModal
      :show="showCustomerPickerModal"
      v-model:search-query="customerSearchQuery"
      :customers="filteredCustomerList"
      :selected-customer-id="selectedCustomerId"
      :is-searching="isSearchingCustomers"
      :is-submitting="isSubmittingQuickCustomer"
      @close="showCustomerPickerModal = false"
      @select-customer="selectCustomer"
      @create-customer="handleQuickCustomerSubmit"
    />

    <!-- 🎉 Success Modal -->
    <POSSuccessModal
      :show="showSuccessModal"
      :invoice="lastCreatedInvoice"
      @close="showSuccessModal = false"
      @print="printLastInvoice"
    />

  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import versionData from '../../version.json';

import POSHeader           from '../../Components/POS/POSHeader.vue';
import POSCartTable        from '../../Components/POS/POSCartTable.vue';
import POSQuickPinnedItems from '../../Components/POS/POSQuickPinnedItems.vue';
import POSCheckoutPanel    from '../../Components/POS/POSCheckoutPanel.vue';
import POSCustomerModal    from '../../Components/POS/POSCustomerModal.vue';
import POSSuccessModal     from '../../Components/POS/POSSuccessModal.vue';
import POSSkeleton         from '../../Components/POS/POSSkeleton.vue';
import POSCategorySidebar  from '../../Components/POS/POSCategorySidebar.vue';
import POSProductGrid      from '../../Components/POS/POSProductGrid.vue';
import { useAppConfigStore } from '../../stores/appConfig';
import { useDesktopHardware } from '../../Composables/useDesktopHardware';
import { useAudioFeedback } from '../../Composables/useAudioFeedback';

const appConfigStore = useAppConfigStore();
const { isDesktop, printThermalReceipt, openCashDrawer } = useDesktopHardware();
const { playScanBeep, playSuccessChime, playDrawerSound, playErrorTone } = useAudioFeedback();

const appVersion = ref(versionData?.version || '1.0.10');
const headerRef = ref(null);

const items = ref([]);
const categories = ref([]);
const customers = ref([]);
const activeStore = ref(null);
const activeShift = ref(null);
const activeCategoryId = ref('favorites');

const favoriteItemsCount = computed(() => {
  return items.value.filter(i => (i.pos_sales_count || 0) > 0 || i.is_pos_pinned).length || Math.min(items.value.length, 20);
});

const showCatalog = ref(localStorage.getItem('pos_show_catalog') !== 'false');
const toggleCatalog = () => {
  showCatalog.value = !showCatalog.value;
  localStorage.setItem('pos_show_catalog', showCatalog.value ? 'true' : 'false');
};

const isLoading = ref(true);
const isSubmitting = ref(false);

const cart = ref([]);
const searchQuery = ref('');
const isSearchFocused = ref(false);
const highlightedIndex = ref(0);

const selectedCustomerId = ref(null);
const activePriceTier = ref('retail');

const discountType = ref('percentage');
const discountValue = ref('0');

const paymentType = ref('cash');
const paymentMethod = ref('cash');
const paidAmount = ref('0.000');
const cashReceived = ref('0.000');
const additionalExpenses = ref([]);

const showCustomerPickerModal = ref(false);
const customerSearchQuery = ref('');
const isSubmittingQuickCustomer = ref(false);

const showSuccessModal = ref(false);
const lastCreatedInvoice = ref(null);

const getItemPrice = (item) => {
  if (!item) return 0;
  const retail = parseFloat(item.selling_price ?? item.price_retail ?? item.price ?? 0);
  const wholesale = parseFloat(item.min_selling_price ?? item.price_wholesale ?? retail);
  return activePriceTier.value === 'wholesale' ? (wholesale > 0 ? wholesale : retail) : (retail > 0 ? retail : wholesale);
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
  const localMatches = items.value.filter(i => 
    (i.name && i.name.toLowerCase().includes(q)) || 
    (i.code && i.code.toLowerCase().includes(q))
  );

  // 2. Merge with remote 10,000-items database matches (deduplicated by ID)
  const mergedMap = new Map();
  localMatches.forEach(item => mergedMap.set(item.id, item));
  remoteSearchResults.value.forEach(item => mergedMap.set(item.id, item));

  return Array.from(mergedMap.values()).slice(0, 15);
});

const quickPinnedItems = computed(() => items.value.slice(0, 10));

const selectedCustomer = computed(() => {
  if (!selectedCustomerId.value) return { id: null, name: trans('pos.general_cash_customer'), phone: '' };
  return customers.value.find(c => c.id === selectedCustomerId.value) || { id: null, name: trans('pos.general_cash_customer'), phone: '' };
});

const filteredCustomerList = computed(() => {
  const q = customerSearchQuery.value.trim().toLowerCase();
  if (!q) return customers.value;

  const localMatches = customers.value.filter(c => 
    (c.name && c.name.toLowerCase().includes(q)) || 
    (c.phone && c.phone.includes(q))
  );

  const mergedMap = new Map();
  localMatches.forEach(c => mergedMap.set(c.id, c));
  remoteCustomerResults.value.forEach(c => mergedMap.set(c.id, c));

  return Array.from(mergedMap.values());
});

const cartSubtotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + (parseFloat(item.quantity) * parseFloat(item.unit_price)), 0);
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
  return additionalExpenses.value.reduce((sum, exp) => sum + (parseFloat(exp.amount) || 0), 0);
});

const cartNetTotal = computed(() => {
  return Math.max(0, cartSubtotal.value - discountAmount.value + customerExpensesTotal.value);
});

const changeDue = computed(() => {
  if (paymentType.value === 'credit') return 0;
  const received = parseFloat(cashReceived.value) || 0;
  return received - cartNetTotal.value;
});

watch(cartNetTotal, (newNet) => {
  const roundedNet = Math.round(newNet);
  if (paymentType.value === 'cash') {
    paidAmount.value = roundedNet.toString();
    cashReceived.value = roundedNet.toString();
  } else if (paymentType.value === 'credit') {
    paidAmount.value = '0';
    cashReceived.value = '0';
  }
});

const addToCart = (item, qty = 1) => {
  playScanBeep();
  const existing = cart.value.find(c => c.id === item.id);
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
    highlightedIndex.value = (highlightedIndex.value - 1 + searchDropdownResults.value.length) % searchDropdownResults.value.length;
  }
};

const selectHighlightedOrFirstItem = () => {
  if (searchDropdownResults.value.length > 0) {
    const item = searchDropdownResults.value[highlightedIndex.value] || searchDropdownResults.value[0];
    addItemFromDropdown(item);
  }
};

const increaseCartItemQty = (idx) => { cart.value[idx].quantity = parseFloat(cart.value[idx].quantity) + 1; };
const decreaseCartItemQty = (idx) => {
  if (parseFloat(cart.value[idx].quantity) > 1) {
    cart.value[idx].quantity = parseFloat(cart.value[idx].quantity) - 1;
  } else {
    removeFromCart(idx);
  }
};
const onCartQtyUpdate = ({ index, value }) => {
  const parsed = parseFloat(value);
  if (!isNaN(parsed) && parsed > 0) cart.value[index].quantity = parsed;
};
const onCartPriceUpdate = ({ index, value }) => {
  const parsed = parseFloat(value);
  if (!isNaN(parsed) && parsed >= 0) cart.value[index].unit_price = parsed;
};
const removeFromCart = (idx) => { cart.value.splice(idx, 1); };

const clearCart = () => {
  if (cart.value.length === 0) return;
  cart.value = [];
  discountValue.value = '0';
  additionalExpenses.value = [];
  cashReceived.value = '0.000';
  headerRef.value?.focusSearch();
};

const applyDiscountPreset = ({ value, type }) => {
  discountValue.value = value.toString();
  discountType.value = type;
};

const selectCustomer = (cust) => {
  selectedCustomerId.value = cust.id;
  showCustomerPickerModal.value = false;
};

const handleQuickCustomerSubmit = async ({ name, phone }) => {
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
  isLoading.value = true;
  try {
    const [itemsRes, customersRes, shiftRes] = await Promise.all([
      api.get('/items', { params: { per_page: 500 } }),
      api.get('/customers', { params: { per_page: 200 } }),
      api.get('/shifts/current').catch(() => ({ data: { data: null } })),
    ]);
    items.value = itemsRes.data?.data || [];
    customers.value = customersRes.data?.data || [];
    activeShift.value = shiftRes.data?.data || null;
  } catch (e) {
    console.error('Failed to load POS data:', e);
  } finally {
    isLoading.value = false;
  }
};

const submitInvoice = async (printImmediately = false) => {
  if (cart.value.length === 0) {
    Swal.fire({ icon: 'warning', title: trans('pos.empty_cart_error'), timer: 1500, showConfirmButton: false });
    return;
  }
  isSubmitting.value = true;
  try {
    const payload = {
      store_id: activeStore.value?.id || 1,
      customer_id: selectedCustomerId.value,
      payment_type: paymentType.value,
      payment_method: paymentType.value === 'credit' ? null : paymentMethod.value,
      discount_type: discountType.value,
      discount_value: parseFloat(discountValue.value) || 0,
      paid_amount: paymentType.value === 'credit' ? 0 : (paymentType.value === 'cash' ? cartNetTotal.value : (parseFloat(paidAmount.value) || 0)),
      items: cart.value.map(i => ({
        item_id: i.id,
        quantity: i.quantity,
        unit_price: i.unit_price,
      })),
      expenses: additionalExpenses.value,
    };

    const res = await api.post('/invoices', payload);
    lastCreatedInvoice.value = res.data?.data;
    playSuccessChime();
    
    if (printImmediately && lastCreatedInvoice.value?.id) {
      printLastInvoice();
    } else {
      showSuccessModal.value = true;
    }
    
    // Reset Cart
    cart.value = [];
    discountValue.value = '0';
    additionalExpenses.value = [];
    cashReceived.value = '0.000';
    headerRef.value?.focusSearch();
  } catch (e) {
    playErrorTone();
    Swal.fire({ icon: 'error', title: trans('pos.checkout_failed'), text: e.userMessage || trans('pos.checkout_failed_desc') });
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

      const itemsRows = (inv.items || []).map(item => `
        <tr>
          <td style="text-align: right; padding: 2px 0;">${item.item_name || item.name}</td>
          <td style="text-align: center; padding: 2px 0;">${parseFloat(item.quantity)}</td>
          <td style="text-align: left; padding: 2px 0;">${parseFloat(item.total_price || 0).toFixed(2)}</td>
        </tr>
      `).join('');

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
  window.open(`/invoices/${lastCreatedInvoice.value.id}/print`, '_blank', 'width=800,height=600');
};

const handleGlobalKeydown = (e) => {
  if (e.key === 'F2') {
    e.preventDefault();
    headerRef.value?.focusSearch();
  } else if (e.key === 'F10') {
    e.preventDefault();
    toggleCatalog();
  } else if (e.key === 'F9' || (e.ctrlKey && e.key === 'Enter')) {
    e.preventDefault();
    if (!showSuccessModal.value && cart.value.length > 0) {
      submitInvoice(false);
    }
  } else if (e.key === 'Enter' && showSuccessModal.value) {
    showSuccessModal.value = false;
    headerRef.value?.focusSearch();
  }
};

onMounted(() => {
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
