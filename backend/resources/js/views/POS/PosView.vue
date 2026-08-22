<template>
  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col font-tajawal selection:bg-amber-500 selection:text-slate-950" dir="rtl">
    <!-- POS Top Header -->
    <header class="h-14 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 flex items-center justify-between shrink-0 shadow-xs">
      <!-- Right: Back to Invoices & Brand -->
      <div class="flex items-center gap-3">
        <router-link
          to="/invoices"
          class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center transition border border-slate-200 dark:border-slate-700"
          :title="$t('pos.back_to_invoices')"
        >
          <ArrowRight class="w-4 h-4" />
        </router-link>

        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-bold">
            ⚡
          </div>
          <div>
            <h1 class="text-sm font-black text-slate-900 dark:text-white leading-none">{{ $t('pos.pos_fast_title') }}</h1>
            <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold font-mono">{{ activeStore?.name || $t('common.main_branch') }}</span>
          </div>
        </div>
      </div>

      <!-- Center: Active Shift Indicator -->
      <div class="hidden sm:flex items-center gap-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-1 rounded-xl text-xs">
        <div class="w-2 h-2 rounded-full" :class="activeShift ? 'bg-emerald-400 animate-pulse' : 'bg-rose-500'"></div>
        <span v-if="activeShift" class="font-bold text-slate-700 dark:text-slate-300">
          {{ $t('pos.shift_label') }} <span class="font-mono text-theme-primary">{{ activeShift.shift_number }}</span>
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
          class="px-3 py-1.5 bg-slate-100 hover:bg-rose-500/20 hover:text-rose-600 dark:bg-slate-800 dark:hover:bg-rose-500/20 dark:hover:text-rose-400 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold transition disabled:opacity-30 cursor-pointer flex items-center gap-1"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span>{{ $t('pos.clear_cart') }}</span>
        </button>
      </div>
    </header>

    <!-- POS Main Split Body -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-0 overflow-hidden">
      <!-- Right: Product Catalog Grid (col-span-7) -->
      <div class="lg:col-span-7 flex flex-col border-b lg:border-b-0 lg:border-e border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/40 p-4 space-y-3 overflow-y-auto">
        <!-- Search & Barcode Scan Input -->
        <div class="flex items-center gap-2">
          <div class="relative flex-1">
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              @keydown.enter="handleBarcodeScan"
              type="text"
              class="w-full h-11 pr-10 pl-4 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none shadow-xs"
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
            :class="selectedCategory === 'all' ? 'bg-theme-primary text-white font-black shadow-theme-primary' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 dark:border-transparent dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
          >
            {{ $t('common.all') }} ({{ items.length }})
          </button>
          <button
            v-for="cat in categories"
            :key="cat"
            type="button"
            @click="selectedCategory = cat"
            class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition cursor-pointer"
            :class="selectedCategory === cat ? 'bg-theme-primary text-white font-black shadow-theme-primary' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 dark:border-transparent dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
          >
            {{ cat }}
          </button>
        </div>

        <!-- Products Grid -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('pos.loading_items') }}</p>
        </div>

        <div v-else-if="filteredItems.length > 0" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 overflow-y-auto max-h-[calc(100vh-210px)] pr-0.5">
          <button
            v-for="item in filteredItems"
            :key="item.id"
            type="button"
            @click="addToCart(item)"
            class="p-3 bg-white dark:bg-slate-900/90 hover:bg-slate-50 dark:hover:bg-slate-800/90 border border-slate-200 dark:border-slate-800 hover:border-amber-500/50 rounded-2xl text-start transition active:scale-95 flex flex-col justify-between space-y-2 cursor-pointer group shadow-xs dark:shadow-md"
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
              <div class="font-bold text-slate-900 dark:text-white text-xs group-hover:text-theme-primary dark:group-hover:text-theme-primary transition-colors line-clamp-2">
                {{ item.name }}
              </div>
            </div>

            <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800/60">
              <span class="text-sm font-black text-theme-primary font-mono">
                {{ formatMoney(getItemPrice(item)) }} <span class="text-[10px] text-slate-400 font-normal">{{ $t('common.currency') }}</span>
              </span>
              <div class="w-6 h-6 rounded-lg bg-theme-light text-theme-primary flex items-center justify-center text-xs font-black">
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
      <div class="lg:col-span-5 flex flex-col bg-white dark:bg-slate-900 p-4 space-y-3 justify-between h-full border-s border-slate-200 dark:border-slate-800 shadow-sm">
        <!-- Customer & Price Tier Header -->
        <div class="space-y-2 pb-2 border-b border-slate-200 dark:border-slate-800">
          <div class="flex items-center gap-2">
            <!-- Clickable Customer Selector (Opens Modal) -->
            <button
              type="button"
              @click="openCustomerPicker"
              class="flex-1 h-11 px-3 bg-slate-50 hover:bg-slate-100 dark:bg-slate-900/80 dark:hover:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:border-theme-primary dark:hover:border-theme-primary rounded-xl flex items-center justify-between gap-2 text-xs transition cursor-pointer group shadow-2xs"
            >
              <div class="flex items-center gap-2 min-w-0 text-start">
                <div class="w-7 h-7 rounded-lg bg-theme-light text-theme-primary flex items-center justify-center font-bold text-xs shrink-0">
                  <Users class="w-3.5 h-3.5" />
                </div>
                <div class="min-w-0">
                  <div class="font-black text-slate-900 dark:text-white truncate text-xs group-hover:text-theme-primary transition-colors">
                    {{ selectedCustomer?.name || $t('pos.walk_in_customer') }}
                  </div>
                  <div v-if="selectedCustomer?.phone" class="text-[10px] text-slate-500 dark:text-slate-400 font-mono truncate">
                    {{ selectedCustomer.phone }}
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <span
                  v-if="selectedCustomer?.current_balance && Number(selectedCustomer.current_balance) > 0"
                  class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-rose-500/10 text-rose-500 border border-rose-500/20"
                >
                  {{ formatMoney(selectedCustomer.current_balance) }} {{ $t('common.currency') }}
                </span>
                <Search class="w-3.5 h-3.5 text-slate-400 group-hover:text-theme-primary" />
              </div>
            </button>

            <!-- Quick Add Customer Button -->
            <button
              type="button"
              @click="openQuickAddFromCart"
              class="px-2.5 h-11 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-theme-primary border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer shrink-0"
              :title="$t('pos.quick_add_customer')"
            >
              <UserPlus class="w-4 h-4" />
            </button>

            <!-- Price Tier Toggle -->
            <button
              type="button"
              @click="togglePriceTier"
              class="px-3 h-11 rounded-xl text-xs font-black transition cursor-pointer shrink-0 border"
              :class="activePriceTier === 'wholesale' ? 'bg-purple-500/20 text-purple-300 border-purple-500/40' : 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700'"
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
            class="p-3 bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl flex items-center justify-between gap-2.5 text-xs shadow-2xs group"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <span class="font-black text-slate-900 dark:text-white truncate">{{ item.name }}</span>
                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                  {{ item.unit }}
                </span>
              </div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                {{ formatMoney(item.unit_price) }} × {{ item.quantity }} = <span class="font-black text-theme-primary">{{ formatMoney(item.quantity * item.unit_price) }} {{ $t('common.currency') }}</span>
              </div>
              <div v-if="item.cost_price" class="text-[9px] text-slate-400 font-mono flex items-center gap-2 mt-0.5">
                <span>التكلفة: {{ formatMoney(item.cost_price) }}</span>
                <span v-if="item.min_selling_price && item.min_selling_price > item.cost_price">أقل بيع: {{ formatMoney(item.min_selling_price) }}</span>
              </div>
            </div>

            <!-- Quantity Stepper with Discrete Enforcement -->
            <div class="flex items-center gap-1 shrink-0">
              <button
                type="button"
                @click="decrementQty(idx)"
                class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 flex items-center justify-center font-black text-sm cursor-pointer transition active:scale-90"
              >
                -
              </button>
              <input
                :value="item.quantity"
                @input="onCartQtyInput(idx, $event)"
                @change="onCartQtyChange(idx, $event)"
                type="number"
                :step="isDiscreteUnit(item.unit) ? '1' : '0.001'"
                :min="isDiscreteUnit(item.unit) ? '1' : '0.001'"
                class="w-14 h-7 text-center bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-black text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-theme-primary"
              >
              <button
                type="button"
                @click="incrementQty(idx)"
                class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 flex items-center justify-center font-black text-sm cursor-pointer transition active:scale-90"
              >
                +
              </button>
            </div>

            <!-- Remove Button -->
            <button
              type="button"
              @click="removeFromCart(idx)"
              class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-lg transition cursor-pointer"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Checkout Bottom Area -->
        <div class="space-y-3 pt-2 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
          <!-- Discount & Totals -->
          <div class="space-y-1.5 font-mono text-xs">
            <div class="flex justify-between text-slate-400 font-sans">
              <span>{{ $t('common.total') }}:</span>
              <span class="font-mono text-slate-900 dark:text-white font-bold">{{ formatMoney(cartSubtotal) }} {{ $t('common.currency') }}</span>
            </div>

            <div class="flex items-center justify-between gap-2">
              <span class="text-slate-400 font-sans text-xs">{{ $t('invoices.discount') }}:</span>
              <div class="flex items-center gap-1">
                <input
                  v-model="discountValue"
                  type="number"
                  step="0.001"
                  min="0"
                  class="w-20 h-7 px-2 text-end bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-rose-400 font-mono font-bold focus:outline-none"
                  placeholder="0.00"
                >
                <select
                  v-model="discountType"
                  class="h-7 px-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-[10px] text-slate-900 dark:text-white focus:outline-none"
                >
                  <option value="fixed">{{ $t('common.currency') }}</option>
                  <option value="percentage">%</option>
                </select>
              </div>
            </div>

            <div class="flex justify-between text-sm font-black text-slate-900 dark:text-white pt-1 border-t border-slate-200 dark:border-slate-800 font-sans">
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
              :class="paymentType === 'cash' ? 'bg-emerald-600 text-white font-black border-emerald-500 shadow-md' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:border-slate-800 dark:hover:bg-slate-800'"
            >
              💵 {{ $t('invoices.cash') }}
            </button>
            <button
              type="button"
              @click="setPaymentType('bank_transfer')"
              class="py-1.5 rounded-xl text-xs font-bold transition border text-center cursor-pointer"
              :class="paymentType === 'bank_transfer' ? 'bg-amber-600 text-white font-black border-amber-500 shadow-md' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:border-slate-800 dark:hover:bg-slate-800'"
            >
              ⚡ {{ $t('contacts.instapay') }}
            </button>
            <button
              type="button"
              @click="setPaymentType('partial')"
              class="py-1.5 rounded-xl text-xs font-bold transition border text-center cursor-pointer"
              :class="paymentType === 'partial' ? 'bg-cyan-600 text-white font-black border-cyan-500 shadow-md' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:border-slate-800 dark:hover:bg-slate-800'"
            >
              ⚖️ {{ $t('invoices.partial') }}
            </button>
            <button
              type="button"
              @click="setPaymentType('credit')"
              class="py-1.5 rounded-xl text-xs font-bold transition border text-center cursor-pointer"
              :class="paymentType === 'credit' ? 'bg-rose-600 text-white font-black border-rose-500 shadow-md' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:border-slate-800 dark:hover:bg-slate-800'"
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
              class="w-28 h-8 px-2 text-end bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold text-emerald-400 focus:outline-none"
              placeholder="0.00"
            >
          </div>

          <!-- Big Submit Checkout Button -->
          <button
            type="button"
            @click="submitCheckout"
            :disabled="isSubmitting || cart.length === 0"
            class="w-full h-12 bg-theme-gradient text-white shadow-theme-primary rounded-2xl font-black text-sm shadow-xl shadow-theme-primary transition active:scale-[0.99] disabled:opacity-40 cursor-pointer flex items-center justify-center gap-2"
          >
            <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <Zap v-else class="w-5 h-5 fill-white text-white" />
            <span>{{ $t('pos.checkout_btn_f9') }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 👥 Comprehensive Customer Picker & Quick Add Modal -->
    <AppModal
      :show="showCustomerPickerModal"
      :title="isAddingNewCustomer ? '➕ تسجيل عميل جديد فوري' : '🔍 اختيار وتحديد العميل'"
      @close="showCustomerPickerModal = false"
      max-width="lg"
    >
      <!-- Modal Navigation Tabs -->
      <div class="flex items-center gap-2 pb-3 border-b border-slate-200 dark:border-slate-800 font-tajawal mb-3">
        <button
          type="button"
          @click="isAddingNewCustomer = false"
          class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
          :class="!isAddingNewCustomer ? 'bg-theme-light text-theme-primary font-black border border-theme-primary' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
        >
          <Search class="w-3.5 h-3.5" />
          <span>بحث واختيار عميل</span>
        </button>

        <button
          type="button"
          @click="isAddingNewCustomer = true"
          class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
          :class="isAddingNewCustomer ? 'bg-theme-light text-theme-primary font-black border border-theme-primary' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
        >
          <UserPlus class="w-3.5 h-3.5" />
          <span>تسجيل عميل جديد</span>
        </button>
      </div>

      <!-- Tab 1: Search & Pick Customer -->
      <div v-if="!isAddingNewCustomer" class="space-y-3 font-tajawal">
        <!-- Search input box -->
        <div class="relative">
          <input
            v-model="customerSearchQuery"
            type="text"
            autofocus
            class="w-full h-11 pr-10 pl-4 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-theme-primary focus:outline-none"
            placeholder="ابحث باسم العميل أو رقم الهاتف أو الكود..."
          />
          <Search class="w-4 h-4 text-slate-400 absolute right-3.5 top-1/2 -translate-y-1/2" />
        </div>

        <!-- 1-Click Walk-in / Cash Customer button -->
        <div
          @click="selectCustomerAndClose({ id: null, name: 'عميل نقدي (نقدي فوري)', price_tier: 'retail' })"
          class="p-3 bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 rounded-2xl flex items-center justify-between cursor-pointer transition active:scale-[0.99] group shadow-2xs"
        >
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-emerald-500 text-slate-950 flex items-center justify-center font-bold text-xs">
              💵
            </div>
            <div>
              <div class="font-black text-emerald-600 dark:text-emerald-400 text-xs">عميل نقدي (نقدي فوري)</div>
              <div class="text-[10px] text-slate-400">للبيع السريع المباشر دون تسجيل حساب</div>
            </div>
          </div>
          <span class="text-xs font-bold text-emerald-500 group-hover:translate-x-[-4px] transition-transform">اختيار ←</span>
        </div>

        <!-- Matching Customers List -->
        <div class="max-h-60 overflow-y-auto space-y-1.5 pr-1 custom-scrollbar">
          <div
            v-for="cust in filteredCustomerList"
            :key="cust.id"
            @click="selectCustomerAndClose(cust)"
            class="p-2.5 bg-slate-50 dark:bg-slate-900/60 hover:bg-slate-100 dark:hover:bg-slate-800/80 border border-slate-200 dark:border-slate-800 hover:border-theme-primary rounded-xl flex items-center justify-between cursor-pointer transition text-xs group"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-8 h-8 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-bold text-xs shrink-0">
                {{ cust.name?.charAt(0) || 'ع' }}
              </div>
              <div class="min-w-0">
                <div class="font-black text-slate-900 dark:text-white truncate group-hover:text-theme-primary">
                  {{ cust.name }}
                </div>
                <div class="text-[10px] text-slate-400 font-mono flex items-center gap-2">
                  <span v-if="cust.phone">{{ cust.phone }}</span>
                  <span class="px-1.5 py-0.5 rounded text-[9px] font-bold" :class="cust.price_tier === 'wholesale' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/30' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400'">
                    {{ cust.price_tier === 'wholesale' ? 'جملة' : 'قطاعي' }}
                  </span>
                </div>
              </div>
            </div>

            <div class="text-end shrink-0">
              <div
                v-if="cust.current_balance && Number(cust.current_balance) !== 0"
                class="text-[10px] font-mono font-bold"
                :class="Number(cust.current_balance) > 0 ? 'text-rose-500' : 'text-emerald-500'"
              >
                {{ Number(cust.current_balance) > 0 ? `عليه: ${formatMoney(cust.current_balance)}` : `له: ${formatMoney(Math.abs(cust.current_balance))}` }}
              </div>
              <span class="text-[11px] font-bold text-slate-400 group-hover:text-theme-primary transition">اختيار ←</span>
            </div>
          </div>

          <div v-if="filteredCustomerList.length === 0" class="p-8 text-center text-slate-400 text-xs space-y-2">
            <div>لا توجد نتائج مطابقة لبحثك</div>
            <button
              type="button"
              @click="switchToCreateCustomer"
              class="px-3 py-1.5 bg-theme-light text-theme-primary font-bold rounded-xl text-xs cursor-pointer"
            >
              + إضافة "{{ customerSearchQuery }}" كعميل جديد
            </button>
          </div>
        </div>
      </div>

      <!-- Tab 2: Quick Add Customer Form -->
      <form v-else @submit.prevent="submitQuickCustomer" class="space-y-3 font-tajawal">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            اسم العميل <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="quickCustomerForm.name"
            type="text"
            required
            autofocus
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
            placeholder="مثال: كمال سرور"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            رقم الهاتف
          </label>
          <input
            v-model="quickCustomerForm.phone"
            type="tel"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
            placeholder="01012345678"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            شريحة السعر الافتراضية
          </label>
          <select
            v-model="quickCustomerForm.price_tier"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none font-tajawal"
          >
            <option value="retail">🛍️ سعر البيع (قطاعي / تجزئة)</option>
            <option value="wholesale">📦 سعر الجملة (شركات وتجار)</option>
          </select>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
          <button
            type="button"
            @click="isAddingNewCustomer = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
          >
            إلغاء والعودة للبحث
          </button>
          <button
            type="submit"
            :disabled="isSubmittingQuickCustomer"
            class="px-5 py-2 text-slate-950 font-black rounded-xl text-xs shadow-md cursor-pointer flex items-center gap-1.5"
            :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
          >
            <span v-if="isSubmittingQuickCustomer" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
            <span>حفظ واختيار العميل</span>
          </button>
        </div>
      </form>
    </AppModal>

    <!-- Post-Checkout Success Modal -->
    <AppModal
      :show="showSuccessModal"
      :title="$t('pos.invoice_success_title')"
      @close="closeSuccessModal"
      max-width="md"
    >
      <div v-if="lastCreatedInvoice" class="space-y-4 font-tajawal text-center">
        <div class="w-14 h-14 rounded-full bg-emerald-500/15 text-emerald-500 border border-emerald-500/30 flex items-center justify-center mx-auto text-2xl font-black shadow-xs">
          ✓
        </div>

        <div>
          <div class="text-base font-black text-theme-primary font-mono tracking-wide">{{ lastCreatedInvoice.invoice_number }}</div>
          <div class="text-xs text-slate-600 dark:text-slate-400 mt-1 font-bold">
            {{ $t('invoices.customer') }}: <span class="text-slate-900 dark:text-slate-200">{{ lastCreatedInvoice.customer_name }}</span>
          </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2 text-xs">
          <div class="flex justify-between items-center text-slate-700 dark:text-slate-300 font-bold font-tajawal">
            <span>{{ $t('invoices.net_invoice') }}:</span>
            <span class="font-black text-emerald-600 dark:text-emerald-400 font-mono text-sm">{{ formatMoney(lastCreatedInvoice.net_total) }} {{ $t('common.currency') }}</span>
          </div>
          <div class="flex justify-between items-center text-slate-600 dark:text-slate-400 font-medium font-tajawal">
            <span>{{ $t('invoices.paid') }}:</span>
            <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ formatMoney(lastCreatedInvoice.paid_amount) }} {{ $t('common.currency') }}</span>
          </div>
          <div v-if="lastCreatedInvoice.remaining_amount > 0" class="flex justify-between items-center text-rose-600 dark:text-rose-400 font-bold font-tajawal pt-2 border-t border-slate-200 dark:border-slate-800">
            <span>{{ $t('invoices.remaining_due') }}:</span>
            <span class="font-mono font-black">{{ formatMoney(lastCreatedInvoice.remaining_amount) }} {{ $t('common.currency') }}</span>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
          <a
            v-if="lastWhatsAppData?.whatsapp_url"
            :href="lastWhatsAppData.whatsapp_url"
            target="_blank"
            class="px-4 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 shadow-md shadow-emerald-500/20 transition active:scale-95 cursor-pointer"
          >
            <Share2 class="w-4 h-4 text-white" />
            <span>{{ $t('pos.send_whatsapp') }}</span>
          </a>

          <button
            type="button"
            @click="window.print()"
            class="px-4 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer shadow-xs"
          >
            <Printer class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('pos.print_receipt') }}</span>
          </button>
        </div>

        <button
          type="button"
          @click="closeSuccessModal"
          class="w-full py-3 bg-theme-gradient text-white shadow-theme-primary font-black text-xs sm:text-sm rounded-xl shadow-lg transition active:scale-95 cursor-pointer flex items-center justify-center gap-1.5"
        >
          <span>{{ $t('pos.start_new_invoice') }}</span>
          <span class="text-[10px] opacity-80 font-mono">(Enter)</span>
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
const showCustomerPickerModal = ref(false);
const customerSearchQuery = ref('');
const isAddingNewCustomer = ref(false);
const isSubmittingQuickCustomer = ref(false);

const selectedCustomer = computed(() => {
    return customers.value.find(c => c.id === selectedCustomerId.value) || { id: null, name: trans('pos.walk_in_customer') || 'عميل نقدي' };
});

const filteredCustomerList = computed(() => {
    if (!customerSearchQuery.value) return customers.value;
    const q = customerSearchQuery.value.trim().toLowerCase();
    return customers.value.filter(c => {
        return (c.name && c.name.toLowerCase().includes(q)) ||
               (c.phone && c.phone.includes(q)) ||
               (c.code && c.code.toLowerCase().includes(q));
    });
});

const openCustomerPicker = () => {
    isAddingNewCustomer.value = false;
    customerSearchQuery.value = '';
    showCustomerPickerModal.value = true;
};

const openQuickAddFromCart = () => {
    isAddingNewCustomer.value = true;
    quickCustomerForm.name = '';
    quickCustomerForm.phone = '';
    quickCustomerForm.address = '';
    showCustomerPickerModal.value = true;
};

const switchToCreateCustomer = () => {
    isAddingNewCustomer.value = true;
    if (customerSearchQuery.value) {
        quickCustomerForm.name = customerSearchQuery.value.trim();
    }
};

const selectCustomerAndClose = (cust) => {
    selectedCustomerId.value = cust.id;
    if (cust.price_tier) {
        activePriceTier.value = cust.price_tier;
    }
    showCustomerPickerModal.value = false;
};

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

const isDiscreteUnit = (unit) => {
    if (!unit) return true;
    const u = unit.toString().trim().toLowerCase();
    const discrete = ['قطعة', 'حبة', 'علبة', 'باكت', 'كرتونة', 'شيكارة', 'طرد', 'دستة', 'جوال', 'piece', 'pcs', 'box', 'carton', 'pack', 'unit', 'item'];
    return discrete.includes(u);
};

const onCartQtyInput = (idx, event) => {
    const rawVal = event?.target?.value ?? event;
    let val = parseFloat(rawVal);
    if (isNaN(val) || val <= 0) val = 1;

    if (isDiscreteUnit(cart.value[idx].unit)) {
        val = Math.max(1, Math.round(val));
        if (event?.target && event.target.value != val) {
            event.target.value = val;
        }
    } else {
        val = parseFloat(val.toFixed(3));
    }
    cart.value[idx].quantity = val;
};

const onCartQtyChange = (idx, event) => {
    const rawVal = event?.target?.value;
    if (isDiscreteUnit(cart.value[idx].unit)) {
        if (rawVal && (rawVal.includes('.') || rawVal.includes(','))) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: `الوحدة (${cart.value[idx].unit}) تقبل أعداداً صحيحة فقط ولا يمكن بيع أجزاء أو كسور`,
                showConfirmButton: false,
                timer: 2500,
            });
        }
    }
};

const addToCart = (item) => {
    const existing = cart.value.find(c => c.item_id === item.id);
    const unitPrice = getItemPrice(item);
    const isDiscrete = isDiscreteUnit(item.unit);

    if (existing) {
        existing.quantity = isDiscrete
            ? Math.round(existing.quantity + 1)
            : parseFloat((existing.quantity + 1).toFixed(3));
    } else {
        cart.value.push({
            item_id: item.id,
            name: item.name,
            code: item.code,
            unit: item.unit,
            quantity: 1,
            unit_price: unitPrice,
            cost_price: parseFloat(item.cost_price) || 0,
            min_selling_price: parseFloat(item.min_selling_price || item.price_wholesale || item.cost_price) || 0,
        });
    }
};

const incrementQty = (idx) => {
    const isDiscrete = isDiscreteUnit(cart.value[idx].unit);
    const step = 1;
    cart.value[idx].quantity = isDiscrete
        ? Math.round(cart.value[idx].quantity + step)
        : parseFloat((cart.value[idx].quantity + step).toFixed(3));
};

const decrementQty = (idx) => {
    const isDiscrete = isDiscreteUnit(cart.value[idx].unit);
    const step = 1;
    if (cart.value[idx].quantity > step) {
        cart.value[idx].quantity = isDiscrete
            ? Math.round(cart.value[idx].quantity - step)
            : parseFloat((cart.value[idx].quantity - step).toFixed(3));
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
    isSubmittingQuickCustomer.value = true;
    try {
        const response = await api.post('/pos/quick-customer', quickCustomerForm);
        const newCust = response.data?.customer;
        if (newCust) {
            customers.value.unshift(newCust);
            selectedCustomerId.value = newCust.id;
            activePriceTier.value = newCust.price_tier || 'retail';
            showCustomerPickerModal.value = false;
            quickCustomerForm.name = '';
            quickCustomerForm.phone = '';
            quickCustomerForm.address = '';
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: trans('common.error'), text: error.userMessage || trans('pos.add_customer_failed') });
    } finally {
        isSubmittingQuickCustomer.value = false;
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
