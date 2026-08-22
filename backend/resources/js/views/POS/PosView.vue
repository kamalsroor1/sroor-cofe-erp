<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-tajawal selection:bg-amber-500 selection:text-slate-950" dir="rtl">
    <!-- POS Top Header -->
    <header class="h-14 bg-slate-900 border-b border-slate-800 px-4 flex items-center justify-between shrink-0">
      <!-- Right: Back to Invoices & Brand -->
      <div class="flex items-center gap-3">
        <router-link
          to="/invoices"
          class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center transition border border-slate-700"
          :title="$t('pos.back_to_invoices')"
        >
          <ArrowRight class="w-4 h-4" />
        </router-link>

        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-bold">
            ⚡
          </div>
          <div>
            <h1 class="text-sm font-black text-white leading-none">{{ $t('pos.pos_fast_title') }}</h1>
            <span class="text-[10px] text-slate-400 font-bold font-mono">{{ activeStore?.name || $t('common.main_branch') }}</span>
          </div>
        </div>
      </div>

      <!-- Center: Active Shift Indicator -->
      <div class="hidden sm:flex items-center gap-2 bg-slate-950 border border-slate-800 px-3 py-1 rounded-xl text-xs">
        <div class="w-2 h-2 rounded-full" :class="activeShift ? 'bg-emerald-400 animate-pulse' : 'bg-rose-500'"></div>
        <span v-if="activeShift" class="font-bold text-slate-300">
          {{ $t('pos.shift_label') }} <span class="font-mono text-amber-400">{{ activeShift.shift_number }}</span>
        </span>
        <span v-else class="text-rose-400 font-bold">
          {{ $t('pos.no_open_shift_alert') }}
        </span>
      </div>

      <!-- Left: Fullscreen & Clear Cart -->
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="clearCart"
          :disabled="cart.length === 0"
          class="px-3 py-1.5 bg-slate-800 hover:bg-rose-500/20 hover:text-rose-400 text-slate-400 border border-slate-700 rounded-xl text-xs font-bold transition disabled:opacity-30 cursor-pointer flex items-center gap-1"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span>{{ $t('pos.clear_cart') }}</span>
        </button>
      </div>
    </header>

    <!-- POS Main Split Body -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-0 overflow-hidden">
      <!-- Right: Product Catalog Grid (col-span-7) -->
      <div class="lg:col-span-7 flex flex-col border-b lg:border-b-0 lg:border-e border-slate-800 bg-slate-900/40 p-4 space-y-3 overflow-y-auto">
        <!-- Search & Barcode Scan Input -->
        <div class="flex items-center gap-2">
          <div class="relative flex-1">
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              @keydown.enter="handleBarcodeScan"
              type="text"
              class="w-full h-11 pr-10 pl-4 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none"
              :placeholder="$t('pos.search_scan_placeholder')"
              autofocus
            >
            <Search class="w-4 h-4 text-slate-500 absolute right-3.5 top-3.5 pointer-events-none" />
          </div>
        </div>

        <!-- Category Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar">
          <button
            type="button"
            @click="selectedCategory = 'all'"
            class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition cursor-pointer"
            :class="selectedCategory === 'all' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
          >
            {{ $t('common.all') }} ({{ items.length }})
          </button>
          <button
            v-for="cat in categories"
            :key="cat"
            type="button"
            @click="selectedCategory = cat"
            class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition cursor-pointer"
            :class="selectedCategory === cat ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
          >
            {{ cat }}
          </button>
        </div>

        <!-- Products Grid -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('pos.loading_items') }}</p>
        </div>

        <div v-else-if="filteredItems.length > 0" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 overflow-y-auto max-h-[calc(100vh-210px)] pr-0.5">
          <button
            v-for="item in filteredItems"
            :key="item.id"
            type="button"
            @click="addToCart(item)"
            class="p-3 bg-slate-950/80 hover:bg-slate-800/90 border border-slate-800 hover:border-amber-500/50 rounded-2xl text-start transition active:scale-95 flex flex-col justify-between space-y-2 cursor-pointer group shadow-md"
          >
            <div>
              <div class="flex items-center justify-between text-[10px] text-slate-500 mb-1">
                <span class="font-mono">{{ item.code || '—' }}</span>
                <span
                  class="px-1.5 py-0.2 rounded font-mono font-bold"
                  :class="item.current_stock > 0 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                >
                  {{ item.current_stock }} {{ item.unit }}
                </span>
              </div>
              <div class="font-bold text-white text-xs group-hover:text-amber-400 transition-colors line-clamp-2">
                {{ item.name }}
              </div>
            </div>

            <div class="flex items-center justify-between pt-1 border-t border-slate-800/60">
              <span class="text-sm font-black text-amber-400 font-mono">
                {{ formatMoney(getItemPrice(item)) }} <span class="text-[10px] text-slate-400 font-normal">{{ $t('common.currency') }}</span>
              </span>
              <div class="w-6 h-6 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-xs font-black">
                +
              </div>
            </div>
          </button>
        </div>

        <div v-else class="p-12 text-center text-slate-500 text-xs font-bold">
          {{ $t('pos.no_matching_items') }}
        </div>
      </div>

      <!-- Left: Active Cart Drawer (col-span-5) -->
      <div class="lg:col-span-5 flex flex-col bg-slate-950 p-4 space-y-3 justify-between h-full">
        <!-- Customer & Price Tier Header -->
        <div class="space-y-2 pb-2 border-b border-slate-800">
          <div class="flex items-center gap-2">
            <!-- Customer Select -->
            <div class="flex-1">
              <select
                v-model="selectedCustomerId"
                @change="onCustomerChange"
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
                <option v-for="c in customers" :key="c.id" :value="c.id">
                  {{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}
                </option>
              </select>
            </div>

            <!-- Quick Add Customer Button -->
            <button
              type="button"
              @click="showQuickCustomerModal = true"
              class="px-2.5 h-10 bg-slate-800 hover:bg-slate-700 text-amber-400 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1 cursor-pointer shrink-0"
              :title="$t('pos.quick_add_customer')"
            >
              <UserPlus class="w-4 h-4" />
            </button>

            <!-- Price Tier Toggle -->
            <button
              type="button"
              @click="togglePriceTier"
              class="px-3 h-10 rounded-xl text-xs font-black transition cursor-pointer shrink-0 border"
              :class="activePriceTier === 'wholesale' ? 'bg-purple-500/20 text-purple-300 border-purple-500/40' : 'bg-slate-900 text-slate-300 border-slate-700'"
            >
              {{ activePriceTier === 'wholesale' ? `📦 ${$t('pos.wholesale')}` : `🛍️ ${$t('pos.retail')}` }}
            </button>
          </div>
        </div>

        <!-- Cart Items List (Scrollable) -->
        <div class="flex-1 overflow-y-auto space-y-2 max-h-[calc(100vh-420px)] pr-1">
          <div v-if="cart.length === 0" class="h-48 flex flex-col items-center justify-center text-slate-600 text-xs space-y-2">
            <ShoppingCart class="w-8 h-8 opacity-30" />
            <span>{{ $t('pos.empty_cart_prompt') }}</span>
          </div>

          <div
            v-for="(item, idx) in cart"
            :key="item.item_id"
            class="p-2.5 bg-slate-900/80 border border-slate-800 rounded-xl flex items-center justify-between gap-2 text-xs"
          >
            <div class="flex-1 min-w-0">
              <div class="font-bold text-white truncate">{{ item.name }}</div>
              <div class="text-[10px] text-slate-400 font-mono">
                {{ formatMoney(item.unit_price) }} {{ $t('common.currency') }} × {{ item.quantity }} = <span class="font-bold text-amber-400">{{ formatMoney(item.quantity * item.unit_price) }} {{ $t('common.currency') }}</span>
              </div>
            </div>

            <!-- Quantity Stepper -->
            <div class="flex items-center gap-1 shrink-0">
              <button
                type="button"
                @click="decrementQty(idx)"
                class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 flex items-center justify-center font-bold text-sm cursor-pointer"
              >
                -
              </button>
              <input
                v-model="item.quantity"
                type="number"
                step="0.1"
                min="0.001"
                class="w-12 h-7 text-center bg-slate-950 border border-slate-700 rounded-lg text-xs font-mono font-bold text-white focus:outline-none"
              >
              <button
                type="button"
                @click="incrementQty(idx)"
                class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 flex items-center justify-center font-bold text-sm cursor-pointer"
              >
                +
              </button>
            </div>

            <!-- Remove Button -->
            <button
              type="button"
              @click="removeFromCart(idx)"
              class="p-1.5 text-slate-500 hover:text-rose-400 rounded-lg transition cursor-pointer"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Checkout Bottom Area -->
        <div class="space-y-3 pt-2 border-t border-slate-800 bg-slate-950">
          <!-- Discount & Totals -->
          <div class="space-y-1.5 font-mono text-xs">
            <div class="flex justify-between text-slate-400 font-sans">
              <span>{{ $t('common.total') }}:</span>
              <span class="font-mono text-white">{{ formatMoney(cartSubtotal) }} {{ $t('common.currency') }}</span>
            </div>

            <div class="flex items-center justify-between gap-2">
              <span class="text-slate-400 font-sans text-xs">{{ $t('invoices.discount') }}:</span>
              <div class="flex items-center gap-1">
                <input
                  v-model="discountValue"
                  type="number"
                  step="0.001"
                  min="0"
                  class="w-20 h-7 px-2 text-end bg-slate-900 border border-slate-700 rounded-lg text-xs text-rose-400 font-mono font-bold focus:outline-none"
                  placeholder="0.00"
                >
                <select
                  v-model="discountType"
                  class="h-7 px-1.5 bg-slate-900 border border-slate-700 rounded-lg text-[10px] text-white focus:outline-none"
                >
                  <option value="fixed">{{ $t('common.currency') }}</option>
                  <option value="percentage">%</option>
                </select>
              </div>
            </div>

            <div class="flex justify-between text-sm font-black text-white pt-1 border-t border-slate-800 font-sans">
              <span>{{ $t('invoices.net_total') }}:</span>
              <span class="font-mono text-emerald-400 text-base">{{ formatMoney(cartNetTotal) }} {{ $t('common.currency') }}</span>
            </div>
          </div>

          <!-- Payment Type & Method Selectors -->
          <div class="grid grid-cols-4 gap-1.5">
            <button
              type="button"
              @click="setPaymentType('cash')"
              class="py-1.5 rounded-xl text-xs font-bold transition border text-center cursor-pointer"
              :class="paymentType === 'cash' ? 'bg-emerald-500 text-slate-950 font-black border-emerald-400 shadow-md' : 'bg-slate-900 text-slate-400 border-slate-800'"
            >
              💵 {{ $t('invoices.cash') }}
            </button>
            <button
              type="button"
              @click="setPaymentType('bank_transfer')"
              class="py-1.5 rounded-xl text-xs font-bold transition border text-center cursor-pointer"
              :class="paymentType === 'bank_transfer' ? 'bg-amber-500 text-slate-950 font-black border-amber-400 shadow-md' : 'bg-slate-900 text-slate-400 border-slate-800'"
            >
              ⚡ {{ $t('contacts.instapay') }}
            </button>
            <button
              type="button"
              @click="setPaymentType('partial')"
              class="py-1.5 rounded-xl text-xs font-bold transition border text-center cursor-pointer"
              :class="paymentType === 'partial' ? 'bg-cyan-500 text-slate-950 font-black border-cyan-400 shadow-md' : 'bg-slate-900 text-slate-400 border-slate-800'"
            >
              ⚖️ {{ $t('invoices.partial') }}
            </button>
            <button
              type="button"
              @click="setPaymentType('credit')"
              class="py-1.5 rounded-xl text-xs font-bold transition border text-center cursor-pointer"
              :class="paymentType === 'credit' ? 'bg-rose-500 text-white font-black border-rose-400 shadow-md' : 'bg-slate-900 text-slate-400 border-slate-800'"
            >
              📝 {{ $t('invoices.credit') }}
            </button>
          </div>

          <!-- Paid Amount Field (for partial) -->
          <div v-if="paymentType === 'partial'" class="flex items-center justify-between gap-2">
            <span class="text-xs font-bold text-slate-400 font-sans">{{ $t('pos.paid_cash_now') }}</span>
            <input
              v-model="paidAmount"
              type="number"
              step="0.001"
              min="0"
              class="w-28 h-8 px-2 text-end bg-slate-900 border border-slate-700 rounded-lg text-xs font-mono font-bold text-emerald-400 focus:outline-none"
              placeholder="0.00"
            >
          </div>

          <!-- Big Submit Checkout Button -->
          <button
            type="button"
            @click="submitCheckout"
            :disabled="isSubmitting || cart.length === 0"
            class="w-full h-12 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 rounded-2xl font-black text-sm shadow-xl shadow-emerald-500/20 transition active:scale-[0.99] disabled:opacity-40 cursor-pointer flex items-center justify-center gap-2"
          >
            <span v-if="isSubmitting" class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
            <Zap v-else class="w-5 h-5 fill-slate-950" />
            <span>{{ $t('pos.checkout_btn_f9') }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Quick Add Customer Modal -->
    <AppModal
      :show="showQuickCustomerModal"
      :title="$t('pos.quick_add_customer')"
      @close="showQuickCustomerModal = false"
    >
      <form @submit.prevent="submitQuickCustomer" class="space-y-4 font-tajawal">
        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1">
            {{ $t('contacts.customer_name') }} <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="quickCustomerForm.name"
            type="text"
            required
            autofocus
            class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
            :placeholder="$t('pos.customer_name_placeholder')"
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1">
            {{ $t('contacts.phone') }}
          </label>
          <input
            v-model="quickCustomerForm.phone"
            type="tel"
            class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
            :placeholder="$t('contacts.phone_placeholder')"
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1">
            {{ $t('pos.price_tier') }}
          </label>
          <select
            v-model="quickCustomerForm.price_tier"
            class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
            <option value="retail">🛍️ {{ $t('pos.retail') }}</option>
            <option value="wholesale">📦 {{ $t('pos.wholesale') }}</option>
          </select>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
          <button
            type="button"
            @click="showQuickCustomerModal = false"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            type="submit"
            class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black shadow-md cursor-pointer"
          >
            {{ $t('pos.save_and_select') }}
          </button>
        </div>
      </form>
    </AppModal>

    <!-- Post-Checkout Success Modal -->
    <AppModal
      :show="showSuccessModal"
      :title="$t('pos.invoice_success_title')"
      @close="closeSuccessModal"
    >
      <div v-if="lastCreatedInvoice" class="space-y-4 font-tajawal text-center">
        <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 flex items-center justify-center mx-auto text-xl font-black">
          ✓
        </div>

        <div>
          <div class="text-sm font-black text-amber-400 font-mono">{{ lastCreatedInvoice.invoice_number }}</div>
          <div class="text-xs text-slate-400 mt-0.5">{{ $t('invoices.customer') }}: {{ lastCreatedInvoice.customer_name }}</div>
        </div>

        <div class="p-3.5 bg-slate-900 border border-slate-800 rounded-2xl space-y-1 font-mono text-xs">
          <div class="flex justify-between text-slate-300 font-tajawal">
            <span>{{ $t('invoices.net_invoice') }}</span>
            <span class="font-black text-emerald-400 font-mono">{{ formatMoney(lastCreatedInvoice.net_total) }} {{ $t('common.currency') }}</span>
          </div>
          <div class="flex justify-between text-slate-400 font-tajawal">
            <span>{{ $t('invoices.paid') }}:</span>
            <span class="font-mono">{{ formatMoney(lastCreatedInvoice.paid_amount) }} {{ $t('common.currency') }}</span>
          </div>
          <div v-if="lastCreatedInvoice.remaining_amount > 0" class="flex justify-between text-rose-400 font-bold font-tajawal">
            <span>{{ $t('invoices.remaining_due') }}</span>
            <span class="font-mono">{{ formatMoney(lastCreatedInvoice.remaining_amount) }} {{ $t('common.currency') }}</span>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2">
          <a
            v-if="lastWhatsAppData?.whatsapp_url"
            :href="lastWhatsAppData.whatsapp_url"
            target="_blank"
            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 shadow-md"
          >
            <Share2 class="w-4 h-4" />
            <span>{{ $t('pos.send_whatsapp') }}</span>
          </a>

          <button
            type="button"
            @click="window.print()"
            class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 cursor-pointer"
          >
            <Printer class="w-4 h-4 text-amber-400" />
            <span>{{ $t('pos.print_receipt') }}</span>
          </button>
        </div>

        <button
          type="button"
          @click="closeSuccessModal"
          class="w-full py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs rounded-xl shadow-md cursor-pointer"
        >
          {{ $t('pos.start_new_invoice') }}
        </button>
      </div>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import api from '../../services/api';
import AppModal from '../../Components/Common/AppModal.vue';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    ArrowRight,
    RotateCcw,
    Search,
    UserPlus,
    ShoppingCart,
    Trash2,
    Zap,
    Share2,
    Printer
} from 'lucide-vue-next';

const items = ref([]);
const categories = ref([]);
const customers = ref([]);
const activeStore = ref(null);
const activeShift = ref(null);

const isLoading = ref(false);
const isSubmitting = ref(false);
const searchQuery = ref('');
const selectedCategory = ref('all');
const activePriceTier = ref('retail');
const selectedCustomerId = ref(1);

const cart = ref([]);
const discountType = ref('fixed');
const discountValue = ref('0.000');
const paymentType = ref('cash');
const paidAmount = ref('0.000');

const searchInputRef = ref(null);
const showQuickCustomerModal = ref(false);
const quickCustomerForm = reactive({
    name: '',
    phone: '',
    price_tier: 'retail',
});

const showSuccessModal = ref(false);
const lastCreatedInvoice = ref(null);
const lastWhatsAppData = ref(null);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getItemPrice = (item) => {
    return activePriceTier.value === 'wholesale' ? item.price_wholesale : item.price_retail;
};

const filteredItems = computed(() => {
    return items.value.filter(it => {
        const matchesCategory = selectedCategory.value === 'all' || it.category === selectedCategory.value;
        const matchesSearch = !searchQuery.value || 
            it.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
            (it.code && it.code.toLowerCase().includes(searchQuery.value.toLowerCase()));
        return matchesCategory && matchesSearch;
    });
});

const cartSubtotal = computed(() => {
    return cart.value.reduce((sum, it) => sum + (parseFloat(it.quantity) || 0) * (parseFloat(it.unit_price) || 0), 0);
});

const cartNetTotal = computed(() => {
    const sub = cartSubtotal.value;
    const disc = parseFloat(discountValue.value) || 0;
    if (discountType.value === 'percentage') {
        const discAmount = (sub * disc) / 100;
        return Math.max(0, sub - discAmount);
    }
    return Math.max(0, sub - disc);
});

const loadPOSBootstrap = async () => {
    isLoading.value = true;
    try {
        const response = await api.get('/pos/bootstrap');
        const data = response.data?.data;
        if (data) {
            items.value = data.items || [];
            categories.value = data.categories || [];
            customers.value = data.customers || [];
            activeStore.value = data.active_store;
            activeShift.value = data.active_shift;

            if (data.default_customer) {
                selectedCustomerId.value = data.default_customer.id;
            }
        }
    } catch (error) {
        console.error('Failed to bootstrap POS:', error);
    } finally {
        isLoading.value = false;
    }
};

const addToCart = (item) => {
    const existing = cart.value.find(c => c.item_id === item.id);
    const unitPrice = getItemPrice(item);

    if (existing) {
        existing.quantity = parseFloat((existing.quantity + 1).toFixed(3));
    } else {
        cart.value.push({
            item_id: item.id,
            name: item.name,
            code: item.code,
            unit: item.unit,
            quantity: 1,
            unit_price: unitPrice,
        });
    }
};

const incrementQty = (idx) => {
    cart.value[idx].quantity = parseFloat((cart.value[idx].quantity + 1).toFixed(3));
};

const decrementQty = (idx) => {
    if (cart.value[idx].quantity > 1) {
        cart.value[idx].quantity = parseFloat((cart.value[idx].quantity - 1).toFixed(3));
    } else {
        removeFromCart(idx);
    }
};

const removeFromCart = (idx) => {
    cart.value.splice(idx, 1);
};

const clearCart = () => {
    cart.value = [];
    discountValue.value = '0.000';
    paidAmount.value = '0.000';
    paymentType.value = 'cash';
};

const togglePriceTier = () => {
    activePriceTier.value = activePriceTier.value === 'retail' ? 'wholesale' : 'retail';
    cart.value.forEach(line => {
        const it = items.value.find(i => i.id === line.item_id);
        if (it) {
            line.unit_price = getItemPrice(it);
        }
    });
};

const setPaymentType = (type) => {
    paymentType.value = type;
    if (type === 'cash') {
        paidAmount.value = cartNetTotal.value.toString();
    } else if (type === 'credit') {
        paidAmount.value = '0.000';
    }
};

const handleBarcodeScan = () => {
    if (!searchQuery.value) return;
    const matched = items.value.find(i => i.code && i.code.toLowerCase() === searchQuery.value.trim().toLowerCase());
    if (matched) {
        addToCart(matched);
        searchQuery.value = '';
    }
};

const submitQuickCustomer = async () => {
    try {
        const response = await api.post('/pos/quick-customer', quickCustomerForm);
        const newCust = response.data?.customer;
        if (newCust) {
            customers.value.unshift(newCust);
            selectedCustomerId.value = newCust.id;
            activePriceTier.value = newCust.price_tier || 'retail';
            showQuickCustomerModal.value = false;
            quickCustomerForm.name = '';
            quickCustomerForm.phone = '';
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: trans('common.error'), text: error.userMessage || trans('pos.add_customer_failed') });
    }
};

const submitCheckout = async () => {
    if (cart.value.length === 0) return;

    isSubmitting.value = true;
    try {
        const payload = {
            customer_id: selectedCustomerId.value,
            store_id: activeStore.value?.id || 1,
            invoice_date: new Date().toISOString().split('T')[0],
            payment_type: paymentType.value,
            payment_method: paymentType.value === 'bank_transfer' ? 'instapay' : 'cash',
            discount_type: discountType.value,
            discount_value: parseFloat(discountValue.value) || 0,
            paid_amount: paymentType.value === 'cash' ? cartNetTotal.value : (parseFloat(paidAmount.value) || 0),
            items: cart.value.map(c => ({
                item_id: c.item_id,
                quantity: parseFloat(c.quantity),
                unit_price: parseFloat(c.unit_price),
            })),
        };

        const response = await api.post('/pos/checkout', payload);
        lastCreatedInvoice.value = response.data?.data;
        lastWhatsAppData.value = response.data?.whatsapp;
        showSuccessModal.value = true;
        clearCart();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('pos.checkout_failed'),
            text: error.userMessage || trans('pos.checkout_failed_desc'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

const closeSuccessModal = () => {
    showSuccessModal.value = false;
    searchInputRef.value?.focus();
};

const handleKeyDown = (e) => {
    if (e.key === 'F9') {
        e.preventDefault();
        submitCheckout();
    } else if (e.key === 'F2') {
        e.preventDefault();
        searchInputRef.value?.focus();
    }
};

onMounted(() => {
    loadPOSBootstrap();
    window.addEventListener('keydown', handleKeyDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>
