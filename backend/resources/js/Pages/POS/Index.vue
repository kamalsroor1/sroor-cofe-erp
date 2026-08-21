<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useMoney } from '@/Composables/useMoney';
import { usePOSCart } from '@/Composables/usePOSCart';
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts';
import { useNativeBridge } from '@/Composables/useNativeBridge';
import { posService } from '@/Services/posService';
import { trans } from '@/helpers/trans';

// Atomic POS Components (SOLID - SRP)
import POSHeader from '@/Components/POS/POSHeader.vue';
import POSCategoryBar from '@/Components/POS/POSCategoryBar.vue';
import POSCustomerBar from '@/Components/POS/POSCustomerBar.vue';
import POSNumpad from '@/Components/POS/POSNumpad.vue';
import POSItemCard from '@/Components/POS/POSItemCard.vue';
import POSCartItem from '@/Components/POS/POSCartItem.vue';
import POSWeightPickerModal from '@/Components/POS/POSWeightPickerModal.vue';
import POSCustomerPickerModal from '@/Components/POS/POSCustomerPickerModal.vue';
import POSQuickCustomerModal from '@/Components/POS/POSQuickCustomerModal.vue';
import POSSuccessModal from '@/Components/POS/POSSuccessModal.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    default_customer: { type: Object, default: () => ({}) },
    active_store: { type: Object, default: null },
    active_shift: { type: Object, default: null },
});

const page = usePage();
const tenant = computed(() => page.props.tenant);

// Composables
const { formatMoney } = useMoney();
const { triggerHaptic } = useNativeBridge();

// Search & Category filter
const searchQuery = ref('');
const selectedCategory = ref('all');
const searchInputRef = ref(null);

// Customer state
const selectedCustomer = ref(props.default_customer || props.customers[0] || null);
const showCustomerModal = ref(false);
const showNewCustomerModal = ref(false);

// Cart Composable
const {
    cart,
    discountType,
    discountValue,
    paymentType,
    paymentMethod,
    paidAmount,
    shippingCost,
    additionalExpenses,
    invoiceNotes,
    subtotal,
    discountAmount,
    expensesTotal,
    netTotal,
    remainingAmount,
    changeDue,
    autoSetCashPaid,
    addItem,
    removeItem,
    clearCart,
} = usePOSCart(selectedCustomer);

// UI States & Modals
const mobileTab = ref('catalog'); // 'catalog' | 'cart'
const showExpensesSection = ref(false);
const showNumpad = ref(false);
const numpadTarget = ref('paid_amount');
const isSubmitting = ref(false);
const errorMessage = ref('');

const showWeightModal = ref(false);
const activeWeightItem = ref(null);

const showSuccessModal = ref(false);
const completedInvoice = ref(null);

// Filtered Items
const filteredItems = computed(() => {
    return props.items.filter(item => {
        const matchesCategory = selectedCategory.value === 'all' || item.category === selectedCategory.value;
        const query = searchQuery.value.trim().toLowerCase();
        const matchesSearch = !query ||
            item.name.toLowerCase().includes(query) ||
            item.code?.toLowerCase().includes(query);
        return matchesCategory && matchesSearch;
    });
});

// Fetch Customer Last Price via Service Layer (DIP)
const fetchCustomerLastPrice = async (itemId) => {
    return await posService.getCustomerLastSoldPrice(
        selectedCustomer.value?.id,
        itemId,
        props.active_store?.id
    );
};

// Item Click Handler (Open weight modal for bulk, or add 1 unit)
const handleItemClick = async (item) => {
    const isWeightBased = item.unit === 'كجم' || item.unit === 'جم' || item.unit?.includes('كيلو');
    if (isWeightBased) {
        activeWeightItem.value = item;
        showWeightModal.value = true;
    } else {
        const lastPrice = await fetchCustomerLastPrice(item.id);
        addItem(item, 1, lastPrice);
    }
};

// 1-Tap Direct Weight Stepper on Card
const handleDirectWeightAdd = async ({ item, quantity }) => {
    const lastPrice = await fetchCustomerLastPrice(item.id);
    addItem(item, quantity, lastPrice);
};

// Weight Confirmation from Modal
const handleWeightConfirm = async ({ item, quantity }) => {
    const lastPrice = await fetchCustomerLastPrice(item.id);
    addItem(item, quantity, lastPrice);
    showWeightModal.value = false;
    activeWeightItem.value = null;
};

// Apply Last Price to Cart Line
const applyLastSoldPrice = (line) => {
    if (line.last_sold_price?.unit_price) {
        line.unit_price = Number(line.last_sold_price.unit_price);
        autoSetCashPaid();
    }
};

// Customer Selection & Pricing Update
const selectCustomer = (c) => {
    selectedCustomer.value = c;
    showCustomerModal.value = false;

    cart.value.forEach(async (line) => {
        const itemObj = props.items.find(i => i.id === line.item_id);
        if (itemObj) {
            line.unit_price = c.price_tier === 'wholesale' ? itemObj.price_wholesale : itemObj.price_retail;
            line.last_sold_price = await fetchCustomerLastPrice(line.item_id);
        }
    });

    autoSetCashPaid();
};

const handleCustomerCreated = (created) => {
    props.customers.unshift(created);
    selectCustomer(created);
    showNewCustomerModal.value = false;
};

// Quick Payment & Discount Helpers
const quickSetPaidExact = () => {
    paymentType.value = 'cash';
    paidAmount.value = netTotal.value;
};

const quickSetPaidAmount = (amt) => {
    paidAmount.value = amt;
    if (amt >= netTotal.value) {
        paymentType.value = 'cash';
    } else {
        paymentType.value = 'partial';
    }
};

const pressNumpad = (val) => {
    let currentVal = String(numpadTarget.value === 'paid_amount' ? paidAmount.value : discountValue.value || '0');
    if (currentVal === '0') currentVal = '';

    if (val === 'C') {
        currentVal = '0';
    } else if (val === 'backspace') {
        currentVal = currentVal.slice(0, -1) || '0';
    } else {
        if (val === '.' && currentVal.includes('.')) return;
        currentVal += val;
    }

    if (numpadTarget.value === 'paid_amount') {
        paidAmount.value = Number(currentVal);
    } else {
        discountValue.value = Number(currentVal);
    }
};

// Expenses Rows
const addExpenseRow = () => {
    additionalExpenses.value.push({ title: trans('invoices.shipping') || 'شحن وتوصيل', amount: 0 });
};
const removeExpenseRow = (idx) => {
    additionalExpenses.value.splice(idx, 1);
};

// Clear Cart with Confirmation
const handleClearCart = () => {
    if (cart.value.length === 0) return;
    if (confirm(trans('pos.clear_cart_confirm') || 'هل أنت متأكد من تفريغ السلة بالكامل؟')) {
        clearCart();
    }
};

// POS Checkout via Service Layer
const submitCheckout = async () => {
    if (cart.value.length === 0) {
        errorMessage.value = trans('pos.empty_cart_error') || 'يرجى إضافة أصناف إلى السلة أولاً!';
        return;
    }

    if (!props.active_store?.id) {
        errorMessage.value = trans('pos.no_active_store_error') || 'يرجى تحديد فرع نشط أولاً!';
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    const payload = {
        customer_id: selectedCustomer.value?.id || 1,
        store_id: props.active_store.id,
        invoice_date: new Date().toISOString().split('T')[0],
        payment_type: paymentType.value,
        payment_method: paymentMethod.value,
        discount_type: discountType.value,
        discount_value: Number(discountValue.value || 0),
        paid_amount: Number(paidAmount.value || 0),
        notes: invoiceNotes.value || null,
        items: cart.value.map(line => ({
            item_id: line.item_id,
            quantity: Number(line.quantity),
            unit_price: Number(line.unit_price),
        })),
        additional_expenses: additionalExpenses.value.map(exp => ({
            title: exp.title,
            amount: Number(exp.amount),
        })),
    };

    try {
        const response = await posService.processCheckout(payload);
        if (response.status === 'success') {
            completedInvoice.value = response.invoice;
            showSuccessModal.value = true;
            clearCart();
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'حدث خطأ أثناء حفظ الفاتورة، يرجى مراجعة المخزون والبيانات.';
    } finally {
        isSubmitting.value = false;
    }
};

// Keyboard Shortcuts (F2 search, F4 cash, F8 partial, F9 credit, Enter confirm)
useKeyboardShortcuts({
    'F2': (e) => {
        e.preventDefault();
        searchInputRef.value?.focus();
    },
    'F4': (e) => {
        e.preventDefault();
        quickSetPaidExact();
    },
    'F8': (e) => {
        e.preventDefault();
        paymentType.value = 'partial';
    },
    'F9': (e) => {
        e.preventDefault();
        paymentType.value = 'credit';
        paidAmount.value = 0;
    },
    'Enter': (e) => {
        if (searchQuery.value && filteredItems.value.length === 1) {
            e.preventDefault();
            handleItemClick(filteredItems.value[0]);
            searchQuery.value = '';
        } else if (cart.value.length > 0 && !isSubmitting.value) {
            e.preventDefault();
            submitCheckout();
        }
    }
});
</script>

<template>
    <Head :title="$t('pos.title')" />

    <AppLayout :default-collapsed="true">
        <div class="h-[calc(100vh-6.5rem)] flex flex-col font-tajawal select-none">
            <!-- Top POS Status & Touch Toolbar -->
            <POSHeader
                :active-store="active_store"
                :active-shift="active_shift"
                :show-numpad="showNumpad"
                @toggle-numpad="showNumpad = !showNumpad"
            />

            <!-- Error Banner -->
            <div v-if="errorMessage" class="p-3 mb-3 rounded-2xl bg-rose-500/20 border border-rose-500/40 text-rose-700 dark:text-rose-300 text-xs font-bold flex items-center justify-between shrink-0">
                <span>⚠️ {{ errorMessage }}</span>
                <button @click="errorMessage = ''" class="text-rose-500 font-bold text-sm">✕</button>
            </div>

            <!-- Mobile View Tab Switcher (Visible on small screens < lg) -->
            <div class="lg:hidden grid grid-cols-2 gap-2 mb-2.5 shrink-0 select-none">
                <button
                    @click="triggerHaptic('light'); mobileTab = 'catalog'"
                    type="button"
                    class="h-11 rounded-2xl font-black text-xs flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer shadow-xs"
                    :class="mobileTab === 'catalog' ? 'btn-primary-theme shadow-theme-primary' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300'"
                >
                    <span>🛍️</span>
                    <span>{{ $t('inventory.items_catalog') || 'الأصناف' }} ({{ filteredItems.length }})</span>
                </button>

                <button
                    @click="triggerHaptic('light'); mobileTab = 'cart'"
                    type="button"
                    class="h-11 rounded-2xl font-black text-xs flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer shadow-xs relative"
                    :class="mobileTab === 'cart' ? 'btn-primary-theme shadow-theme-primary' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300'"
                >
                    <span>🛒</span>
                    <span>{{ $t('invoices.invoices_log') || 'السلة' }} ({{ cart.length }})</span>
                    <span v-if="cart.length > 0" class="font-mono text-emerald-600 dark:text-emerald-400 font-black mr-1">
                        {{ formatMoney(netTotal) }}
                    </span>
                </button>
            </div>

            <!-- Main POS Split Workspace -->
            <div class="flex-1 flex flex-col lg:flex-row gap-4 overflow-hidden relative">
                <!-- Left Section: Catalog & Categories Grid (65% width) -->
                <div
                    class="flex-1 flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 space-y-3 overflow-hidden shadow-xs"
                    :class="mobileTab === 'catalog' ? 'flex' : 'hidden lg:flex'"
                >
                    <!-- Search Bar & Category Chips -->
                    <div class="space-y-2.5 shrink-0">
                        <div class="relative flex items-center">
                            <input
                                ref="searchInputRef"
                                v-model="searchQuery"
                                type="text"
                                :placeholder="$t('pos.search_placeholder')"
                                class="w-full h-11 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl pl-10 pr-4 text-xs sm:text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-amber-500 transition shadow-inner font-tajawal"
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                type="button"
                                class="absolute left-3 w-7 h-7 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs font-bold flex items-center justify-center cursor-pointer"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Category Chips -->
                        <POSCategoryBar
                            :categories="categories"
                            :selected-category="selectedCategory"
                            :total-items-count="items.length"
                            @select-category="(cat) => selectedCategory = cat"
                        />
                    </div>

                    <!-- Items Grid -->
                    <div class="flex-1 overflow-y-auto pr-0.5 pb-16 lg:pb-0">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-2 sm:gap-2.5">
                            <POSItemCard
                                v-for="item in filteredItems"
                                :key="item.id"
                                :item="item"
                                :customer-price-tier="selectedCustomer?.price_tier || 'retail'"
                                @select="handleItemClick"
                                @add-qty="handleDirectWeightAdd"
                            />
                        </div>

                        <div v-if="filteredItems.length === 0" class="py-20 text-center text-slate-500 dark:text-slate-400 text-xs font-bold">
                            {{ $t('inventory.no_items_found') }}
                        </div>
                    </div>

                    <!-- Floating Mobile Quick-Cart Bar (on Catalog tab) -->
                    <Transition name="sheet-slide">
                        <div v-if="mobileTab === 'catalog' && cart.length > 0" class="lg:hidden fixed bottom-22 inset-x-3 z-30">
                            <button
                                @click="triggerHaptic('medium'); mobileTab = 'cart'"
                                type="button"
                                class="w-full h-13 rounded-2xl btn-primary-theme shadow-2xl flex items-center justify-between px-4 font-black text-xs cursor-pointer border-2 border-white dark:border-slate-900 active:scale-95 transition-all"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="text-base">🛒</span>
                                    <span class="bg-black/25 px-2.5 py-1 rounded-xl text-white font-mono font-bold">{{ cart.length }} {{ $t('common.items') || 'أصناف' }}</span>
                                </span>
                                <span class="font-mono text-sm font-black text-white drop-shadow-xs">{{ formatMoney(netTotal) }} {{ $t('common.currency') }}</span>
                                <span class="flex items-center gap-1 text-white text-xs font-black">
                                    <span>{{ $t('invoices.invoices_log') || 'السلة' }}</span>
                                    <span>←</span>
                                </span>
                            </button>
                        </div>
                    </Transition>
                </div>

                <!-- Right Section: Smart Cart & Payment Engine (35% width) -->
                <div
                    class="w-full lg:w-[420px] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl flex-col h-full overflow-hidden shrink-0 shadow-xs"
                    :class="mobileTab === 'cart' ? 'flex' : 'hidden lg:flex'"
                >
                    <!-- Mobile Back to Catalog Bar -->
                    <div class="lg:hidden p-2.5 bg-slate-100 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <button
                            @click="triggerHaptic('light'); mobileTab = 'catalog'"
                            type="button"
                            class="h-8.5 px-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-black text-slate-800 dark:text-slate-200 flex items-center gap-1.5 active:scale-95 transition cursor-pointer shadow-xs"
                        >
                            <span>←</span>
                            <span>{{ $t('inventory.items_catalog') || 'العودة لقائمة الأصناف' }}</span>
                        </button>
                        <span class="text-xs font-bold font-mono text-slate-500">{{ cart.length }} {{ $t('common.items') || 'أصناف' }}</span>
                    </div>

                    <!-- Customer Selector Bar -->
                    <POSCustomerBar
                        :selected-customer="selectedCustomer"
                        @open-customer-modal="showCustomerModal = true"
                        @open-new-customer-modal="showNewCustomerModal = true"
                    />

                    <!-- Cart Lines -->
                    <div class="flex-1 overflow-y-auto p-3 space-y-2">
                        <POSCartItem
                            v-for="(line, idx) in cart"
                            :key="line.item_id"
                            :line="line"
                            :index="idx"
                            @remove="removeItem"
                            @apply-last-price="applyLastSoldPrice"
                            @change="autoSetCashPaid"
                        />

                        <div v-if="cart.length === 0" class="py-16 text-center text-slate-400 dark:text-slate-500 text-xs font-bold space-y-2">
                            <div class="text-3xl">🛒</div>
                            <div>{{ $t('pos.empty_cart_msg') }}</div>
                        </div>
                    </div>

                    <!-- Touch Numpad Popup / Panel -->
                    <POSNumpad
                        v-if="showNumpad"
                        :target="numpadTarget"
                        @press="pressNumpad"
                        @set-target="(t) => numpadTarget = t"
                        @quick-exact="quickSetPaidExact"
                    />

                    <!-- Financial Totals & Payment (Fixed Bottom Area) -->
                    <div class="p-3.5 sm:p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 space-y-2.5 shrink-0">
                        <!-- Subtotal & Discount -->
                        <div class="space-y-1.5 text-xs text-slate-500 dark:text-slate-400 font-tajawal">
                            <div class="flex items-center justify-between">
                                <span>{{ $t('common.subtotal') }}:</span>
                                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(subtotal) }} {{ $t('common.currency') }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <span>{{ $t('common.discount') }}:</span>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        @click="discountType = discountType === 'fixed' ? 'percentage' : 'fixed'"
                                        type="button"
                                        class="h-7 px-2 rounded-lg bg-slate-200 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs font-black text-amber-600 dark:text-amber-400 cursor-pointer"
                                    >
                                        {{ discountType === 'fixed' ? $t('common.currency') : '%' }}
                                    </button>
                                    <input
                                        v-model.number="discountValue"
                                        type="number"
                                        inputmode="decimal"
                                        min="0"
                                        class="w-16 h-7 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-center text-xs font-mono font-bold text-slate-900 dark:text-white"
                                    />
                                    <span class="font-mono font-bold text-rose-600 dark:text-rose-400">-{{ formatMoney(discountAmount) }}</span>
                                </div>
                            </div>

                            <!-- Net Total -->
                            <div class="flex items-center justify-between pt-1.5 border-t border-slate-200 dark:border-slate-800 text-sm">
                                <span class="font-black text-slate-900 dark:text-white">{{ $t('common.net') }}:</span>
                                <span class="font-mono font-black text-lg sm:text-xl text-emerald-600 dark:text-emerald-400">{{ formatMoney(netTotal) }} {{ $t('common.currency') }}</span>
                            </div>
                        </div>

                        <!-- Payment Type Selector (Finger Friendly Min 40px) -->
                        <div class="grid grid-cols-3 gap-1.5 text-xs">
                            <button
                                @click="paymentType = 'cash'; autoSetCashPaid()"
                                type="button"
                                class="h-10 sm:h-9 rounded-xl font-black transition text-center cursor-pointer flex items-center justify-center active:scale-95 shadow-xs"
                                :class="paymentType === 'cash' ? 'bg-emerald-500 text-slate-950 shadow-md' : 'bg-slate-200 dark:bg-slate-900 text-slate-700 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-800'"
                            >
                                {{ $t('pos.payment_cash') }} (F4)
                            </button>
                            <button
                                @click="paymentType = 'partial'; autoSetCashPaid()"
                                type="button"
                                class="h-10 sm:h-9 rounded-xl font-black transition text-center cursor-pointer flex items-center justify-center active:scale-95 shadow-xs"
                                :class="paymentType === 'partial' ? 'bg-amber-500 text-slate-950 shadow-md' : 'bg-slate-200 dark:bg-slate-900 text-slate-700 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-800'"
                            >
                                {{ $t('pos.payment_partial') }} (F8)
                            </button>
                            <button
                                @click="paymentType = 'credit'; paidAmount = 0"
                                type="button"
                                class="h-10 sm:h-9 rounded-xl font-black transition text-center cursor-pointer flex items-center justify-center active:scale-95 shadow-xs"
                                :class="paymentType === 'credit' ? 'bg-rose-500 text-white shadow-md' : 'bg-slate-200 dark:bg-slate-900 text-slate-700 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-800'"
                            >
                                {{ $t('pos.payment_credit') }} (F9)
                            </button>
                        </div>

                        <!-- Payment Method Selector (Cash, InstaPay, Visa, Wallet) -->
                        <div class="grid grid-cols-4 gap-1 text-[11px] font-bold">
                            <button
                                @click="paymentMethod = 'cash'"
                                type="button"
                                class="h-8.5 rounded-xl border transition text-center cursor-pointer flex items-center justify-center active:scale-95"
                                :class="paymentMethod === 'cash' ? 'bg-theme-light border-theme-primary text-theme-primary font-black' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'"
                            >
                                {{ $t('treasury.cash_drawer') }}
                            </button>
                            <button
                                @click="paymentMethod = 'instapay'"
                                type="button"
                                class="h-8.5 rounded-xl border transition text-center cursor-pointer flex items-center justify-center active:scale-95"
                                :class="paymentMethod === 'instapay' ? 'bg-purple-500/15 border-purple-500 text-purple-600 dark:text-purple-400 font-black' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'"
                            >
                                {{ $t('treasury.instapay') }}
                            </button>
                            <button
                                @click="paymentMethod = 'visa'"
                                type="button"
                                class="h-8.5 rounded-xl border transition text-center cursor-pointer flex items-center justify-center active:scale-95"
                                :class="paymentMethod === 'visa' ? 'bg-cyan-500/15 border-cyan-500 text-cyan-600 dark:text-cyan-400 font-black' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'"
                            >
                                {{ $t('treasury.visa') }}
                            </button>
                            <button
                                @click="paymentMethod = 'e_wallet'"
                                type="button"
                                class="h-8.5 rounded-xl border transition text-center cursor-pointer flex items-center justify-center active:scale-95"
                                :class="paymentMethod === 'e_wallet' ? 'bg-rose-500/15 border-rose-500 text-rose-600 dark:text-rose-400 font-black' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'"
                            >
                                {{ $t('treasury.e_wallet') }}
                            </button>
                        </div>

                        <!-- Paid Amount & Change Due -->
                        <div v-if="paymentType !== 'credit'" class="flex items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-1.5 flex-1">
                                <span class="text-slate-600 dark:text-slate-400 shrink-0">{{ $t('common.paid') }}:</span>
                                <input
                                    v-model.number="paidAmount"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    class="w-full h-10 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-2 text-center text-sm font-mono font-black text-slate-900 dark:text-white focus:outline-none focus:border-theme-primary"
                                />
                            </div>

                            <div v-if="changeDue > 0" class="px-3 py-1.5 rounded-xl bg-theme-light border border-theme-light text-theme-primary font-mono font-black text-xs shrink-0">
                                {{ $t('pos.change_due') }}: {{ formatMoney(changeDue) }} {{ $t('common.currency') }}
                            </div>
                        </div>

                        <!-- Quick Cash Amount Chips -->
                        <div v-if="paymentType !== 'credit'" class="flex items-center gap-1.5 overflow-x-auto text-xs font-mono">
                            <span class="text-slate-500 text-[10px] px-1 font-tajawal shrink-0">{{ $t('contacts.voucher_amount') }}:</span>
                            <button @click="quickSetPaidExact" type="button" class="h-7.5 px-2.5 rounded-lg bg-slate-200 hover:bg-theme-primary hover:text-white dark:bg-slate-800 text-theme-primary font-black transition active:scale-95 shrink-0">{{ $t('common.net') }}</button>
                            <button v-for="amt in [50, 100, 200, 500]" :key="amt" @click="quickSetPaidAmount(amt)" type="button" class="h-7.5 px-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold transition active:scale-95 shrink-0">{{ amt }}</button>
                        </div>

                        <!-- Checkout & Clear Buttons -->
                        <div class="flex items-center gap-2 pt-1">
                            <button
                                @click="handleClearCart"
                                type="button"
                                class="w-12 h-12 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-500 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 font-black text-base flex items-center justify-center transition border border-slate-200 dark:border-slate-800 shrink-0 cursor-pointer active:scale-95 shadow-xs"
                                :title="$t('pos.clear_cart')"
                            >
                                🗑️
                            </button>

                            <button
                                :disabled="isSubmitting || cart.length === 0"
                                @click="submitCheckout"
                                type="button"
                                class="flex-1 h-12 rounded-2xl btn-primary-theme disabled:opacity-50 font-black text-sm flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-primary"
                            >
                                <span>⚡</span>
                                <span>{{ isSubmitting ? $t('common.save') + '...' : $t('pos.confirm_invoice') + ' (Enter)' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modals -->
            <POSWeightPickerModal
                :show="showWeightModal"
                :item="activeWeightItem"
                :customer-price-tier="selectedCustomer?.price_tier || 'retail'"
                @close="showWeightModal = false"
                @confirm="handleWeightConfirm"
            />

            <POSCustomerPickerModal
                :show="showCustomerModal"
                :customers="customers"
                :selected-customer-id="selectedCustomer?.id"
                @close="showCustomerModal = false"
                @select="selectCustomer"
            />

            <POSQuickCustomerModal
                :show="showNewCustomerModal"
                @close="showNewCustomerModal = false"
                @created="handleCustomerCreated"
            />

            <POSSuccessModal
                :show="showSuccessModal"
                :invoice="completedInvoice"
                @close="showSuccessModal = false"
            />
        </div>
    </AppLayout>
</template>
