<template>
  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950" dir="rtl">
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

        <!-- 📂 Category Bar (Horizontal Touch-Scrollable Filter Bar) -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1.5 no-scrollbar touch-pan-x select-none shrink-0">
          <!-- All Items Button -->
          <button
            type="button"
            @click="selectedCategory = 'all'"
            class="h-10 px-4 rounded-2xl text-xs font-black whitespace-nowrap transition-all duration-200 cursor-pointer flex items-center gap-2 shrink-0"
            :class="selectedCategory === 'all'
              ? 'bg-theme-primary text-slate-950 shadow-md shadow-theme-primary/30 scale-105'
              : 'bg-white dark:bg-slate-800/90 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700/60 hover:border-theme-primary/40 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
          >
            <span class="text-sm">📦</span>
            <span>{{ $t('common.all_items') || 'كل الأصناف' }}</span>
            <span
              class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="selectedCategory === 'all' ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'"
            >
              {{ items.length }}
            </span>
          </button>

          <!-- Category Item Buttons -->
          <button
            v-for="cat in categories"
            :key="typeof cat === 'object' ? cat.id : cat"
            type="button"
            @click="selectedCategory = (typeof cat === 'object' ? (cat.name || cat.id) : cat)"
            class="h-10 px-4 rounded-2xl text-xs font-black whitespace-nowrap transition-all duration-200 cursor-pointer flex items-center gap-2 shrink-0"
            :class="(selectedCategory === (typeof cat === 'object' ? (cat.name || cat.id) : cat))
              ? 'bg-theme-primary text-slate-950 shadow-md shadow-theme-primary/30 scale-105'
              : 'bg-white dark:bg-slate-800/90 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700/60 hover:border-theme-primary/40 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
          >
            <span class="text-base">{{ typeof cat === 'object' ? (cat.icon || '☕') : '☕' }}</span>
            <span>{{ typeof cat === 'object' ? cat.name : cat }}</span>
            <span
              v-if="typeof cat === 'object' && cat.items_count !== undefined"
              class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="(selectedCategory === (typeof cat === 'object' ? (cat.name || cat.id) : cat)) ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'"
            >
              {{ cat.items_count }}
            </span>
          </button>
        </div>

        <!-- Products Grid -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('pos.loading_items') }}</p>
        </div>

        <div v-else-if="filteredItems.length > 0" class="overflow-y-auto max-h-[calc(100vh-210px)] pr-0.5 custom-scrollbar space-y-4" @scroll="onGridScroll">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <button
              v-for="item in visibleItems"
              :key="item.id"
              type="button"
              @click="addToCart(item)"
              class="p-4 sm:p-5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/90 border-2 border-slate-200 dark:border-slate-700/80 hover:border-theme-primary rounded-2xl text-start transition-all duration-200 active:scale-[0.98] flex flex-col justify-between space-y-4 cursor-pointer group shadow-sm hover:shadow-xl min-h-[160px]"
            >
              <!-- Top Row: Code & Stock Badge -->
              <div class="space-y-2 w-full">
                <div class="flex items-center justify-between">
                  <span class="font-mono font-bold text-xs text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-lg border border-slate-200 dark:border-slate-700">
                    {{ item.code || '—' }}
                  </span>
                  <span
                    class="px-2.5 py-1 rounded-xl font-mono font-black text-xs flex items-center gap-1.5 shadow-2xs"
                    :class="item.current_stock > 0
                      ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/40'
                      : 'bg-rose-500/20 text-rose-700 dark:text-rose-300 border border-rose-500/40'"
                  >
                    <span>{{ item.current_stock > 0 ? '📦' : '⚠️' }}</span>
                    <span>{{ formatMoney(item.current_stock) }} {{ item.unit }}</span>
                  </span>
                </div>

                <!-- Product Name (Large & Bright) -->
                <div class="font-black text-slate-950 dark:text-white text-base group-hover:text-theme-primary transition-colors line-clamp-2 leading-relaxed tracking-tight">
                  {{ item.name }}
                </div>
              </div>

              <!-- Price Breakdown & Add Button -->
              <div class="w-full pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2">
                <!-- Secondary Prices (Cost & Min Selling / Wholesale) -->
                <div class="flex items-center justify-between text-xs font-mono">
                  <span v-if="item.price_wholesale || item.min_selling_price" class="px-2 py-1 rounded-lg bg-purple-500/20 text-purple-700 dark:text-purple-300 font-black border border-purple-500/40" title="أقل سعر بيع (الجملة)">
                    أقل بيع: {{ formatMoney(item.min_selling_price || item.price_wholesale) }}
                  </span>
                  <span v-if="item.cost_price" class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold border border-slate-300 dark:border-slate-700" title="سعر التكلفة">
                    التكلفة: {{ formatMoney(item.cost_price) }}
                  </span>
                </div>

                <!-- Primary Selling Price Row + Big Touch Add Button -->
                <div class="flex items-center justify-between pt-1">
                  <div>
                    <div class="text-xs text-slate-600 dark:text-slate-300 font-bold font-tajawal">
                      سعر البيع ({{ activePriceTier === 'wholesale' ? 'جملة' : 'قطاعي' }}):
                    </div>
                    <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono tracking-tight">
                      {{ formatMoney(getItemPrice(item)) }} <span class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
                    </div>
                  </div>

                  <div class="w-10 h-10 rounded-2xl bg-theme-light text-theme-primary flex items-center justify-center text-lg font-black group-hover:scale-110 group-hover:bg-theme-primary group-hover:text-slate-950 transition-all shadow-md">
                    +
                  </div>
                </div>
              </div>
            </button>
          </div>

          <!-- Pagination Indicator for 10k items -->
          <div v-if="visibleDisplayLimit < filteredItems.length" class="text-center py-2">
            <button
              type="button"
              @click="visibleDisplayLimit += 60"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-300 dark:border-slate-700 transition active:scale-95 shadow-xs cursor-pointer font-tajawal"
            >
              عرض المزيد (+60 صنف) • متبقي {{ (filteredItems.length - visibleDisplayLimit).toLocaleString() }} صنف من أصل {{ filteredItems.length.toLocaleString() }}
            </button>
          </div>
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
            <!-- Customer Selector Button -->
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
                <span v-if="selectedCustomer?.current_balance && Number(selectedCustomer.current_balance) > 0" class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-rose-500/10 text-rose-500 border border-rose-500/20">
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

            <!-- Retail / Wholesale Switcher -->
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

        <!-- Cart Items List -->
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

        <!-- Cart Quick Summary & Open Payment Modal Button -->
        <div class="space-y-3 pt-2 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
          <div class="space-y-1.5 font-mono text-xs">
            <div class="flex justify-between text-slate-500 font-sans">
              <span>{{ $t('common.total') }}:</span>
              <span class="font-mono text-slate-900 dark:text-white font-bold">{{ formatMoney(cartSubtotal) }} {{ $t('common.currency') }}</span>
            </div>

            <div v-if="parseFloat(discountAmount) > 0" class="flex justify-between text-rose-500 font-sans">
              <span>{{ $t('invoices.discount') }}:</span>
              <span class="font-mono font-bold">-{{ formatMoney(discountAmount) }} {{ $t('common.currency') }}</span>
            </div>

            <div v-if="customerExpensesTotal > 0" class="flex justify-between text-cyan-500 font-sans">
              <span>{{ $t('pos.shipping_and_services') || 'مصاريف إضافية / شحن' }}:</span>
              <span class="font-mono font-bold">+{{ formatMoney(customerExpensesTotal) }} {{ $t('common.currency') }}</span>
            </div>

            <div class="flex justify-between text-sm font-black text-slate-900 dark:text-white pt-1 border-t border-slate-200 dark:border-slate-800 font-sans">
              <span>{{ $t('invoices.net_total') }}:</span>
              <span class="font-mono text-emerald-500 dark:text-emerald-400 text-lg font-black">{{ formatMoney(cartNetTotal) }} {{ $t('common.currency') }}</span>
            </div>
          </div>

          <!-- Big Action Button: Open Redesigned Payment Modal -->
          <button
            type="button"
            @click="openPaymentModal"
            :disabled="cart.length === 0"
            class="w-full h-13 bg-theme-gradient text-white shadow-theme-primary rounded-2xl font-black text-sm sm:text-base shadow-xl shadow-theme-primary transition active:scale-[0.99] disabled:opacity-40 cursor-pointer flex items-center justify-center gap-2"
          >
            <Zap class="w-5 h-5 fill-white text-white" />
            <span>{{ $t('pos.proceed_to_payment') || 'سداد وإنهاء الفاتورة' }}</span>
            <span class="text-xs opacity-80 font-mono">(F9)</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- 💳 REDESIGNED COMPREHENSIVE POS PAYMENT MODAL (Photos 2,3,4) -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <AppModal
      :show="showPaymentModal"
      :title="$t('pos.payment_screen_title') || '💳 تفاصيل وسداد الفاتورة'"
      @close="showPaymentModal = false"
      max-width="2xl"
    >
      <div class="space-y-5 font-tajawal text-slate-900 dark:text-slate-100 max-h-[80vh] overflow-y-auto pr-1 custom-scrollbar">
        <!-- Customer & Order Summary Pill -->
        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl flex items-center justify-between gap-3 text-xs">
          <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-xl bg-theme-light text-theme-primary flex items-center justify-center font-bold">👤</span>
            <div>
              <span class="text-slate-500 text-[10px]">{{ $t('invoices.customer') }}:</span>
              <div class="font-black text-slate-900 dark:text-white">{{ selectedCustomer?.name || $t('pos.walk_in_customer') }}</div>
            </div>
          </div>
          <div class="flex items-center gap-2 font-mono">
            <span class="px-2.5 py-1 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">
              {{ cart.length }} {{ $t('inventory.items_unit') || 'أصناف' }}
            </span>
            <span class="px-3 py-1 rounded-xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 font-black text-sm">
              {{ formatMoney(cartNetTotal) }} {{ $t('common.currency') }}
            </span>
          </div>
        </div>

        <!-- 📌 1. قسم نوع الفاتورة والسداد (Photo 2 - Top) -->
        <div class="space-y-2">
          <label class="block text-xs font-black text-slate-700 dark:text-slate-300">
            1. {{ $t('pos.payment_type_section') || 'نوع الفاتورة والسداد' }}
          </label>
          <div class="grid grid-cols-3 gap-2">
            <!-- كاش فوري كامل -->
            <button
              type="button"
              @click="setPaymentType('cash')"
              class="p-3 rounded-2xl border text-center transition-all cursor-pointer flex flex-col items-center gap-1.5 shadow-xs"
              :class="paymentType === 'cash'
                ? 'bg-emerald-600 text-white border-emerald-500 shadow-md shadow-emerald-600/20 ring-2 ring-emerald-500/30'
                : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-emerald-500/50'"
            >
              <span class="text-lg">💵</span>
              <span class="text-xs font-black">{{ $t('pos.full_cash') || 'كاش فوري كامل' }}</span>
              <span class="text-[10px] opacity-80">{{ $t('pos.full_cash_desc') || 'سداد كامل المبلغ الآن' }}</span>
            </button>

            <!-- آجل (ذمم) بالكامل -->
            <button
              type="button"
              @click="setPaymentType('credit')"
              class="p-3 rounded-2xl border text-center transition-all cursor-pointer flex flex-col items-center gap-1.5 shadow-xs"
              :class="paymentType === 'credit'
                ? 'bg-rose-600 text-white border-rose-500 shadow-md shadow-rose-600/20 ring-2 ring-rose-500/30'
                : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-rose-500/50'"
            >
              <span class="text-lg">📝</span>
              <span class="text-xs font-black">{{ $t('pos.full_credit') || 'آجل (ذمم بالكامل)' }}</span>
              <span class="text-[10px] opacity-80">{{ $t('pos.full_credit_desc') || 'تسجيل على حساب العميل' }}</span>
            </button>

            <!-- دفع جزئي -->
            <button
              type="button"
              @click="setPaymentType('partial')"
              class="p-3 rounded-2xl border text-center transition-all cursor-pointer flex flex-col items-center gap-1.5 shadow-xs"
              :class="paymentType === 'partial'
                ? 'bg-cyan-600 text-white border-cyan-500 shadow-md shadow-cyan-600/20 ring-2 ring-cyan-500/30'
                : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-cyan-500/50'"
            >
              <span class="text-lg">⚖️</span>
              <span class="text-xs font-black">{{ $t('pos.partial_payment') || 'دفع جزئي' }}</span>
              <span class="text-[10px] opacity-80">{{ $t('pos.partial_payment_desc') || 'مقدم + متبقي آجل' }}</span>
            </button>
          </div>

          <!-- Partial Payment Details -->
          <div v-if="paymentType === 'partial'" class="p-3 bg-cyan-500/10 border border-cyan-500/30 rounded-2xl space-y-2">
            <div class="grid grid-cols-2 gap-3">
              <BaseNumberInput
                v-model="paidAmount"
                :label="$t('pos.paid_now_amount') || 'المبلغ المسدد الآن'"
                step="0.001"
                :min="0"
                :suffix="$t('common.currency')"
              />
              <div class="flex flex-col justify-center">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold mb-1">{{ $t('invoices.remaining_due') }}:</span>
                <span class="text-base font-black text-rose-500 font-mono">
                  {{ formatMoney(Math.max(0, cartNetTotal - (parseFloat(paidAmount) || 0))) }} {{ $t('common.currency') }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- 📌 2. قسم وسيلة التحصيل والدفع الفعلية -->
        <div v-if="paymentType !== 'credit'" class="space-y-2">
          <label class="block text-xs font-black text-slate-700 dark:text-slate-300">
            2. {{ $t('pos.payment_method_section') || 'وسيلة التحصيل والدفع' }}
          </label>
          <div class="grid grid-cols-3 gap-2">
            <!-- كاش نقدي -->
            <button
              type="button"
              @click="paymentMethod = 'cash'"
              class="p-3 rounded-2xl border text-center transition-all cursor-pointer flex items-center justify-center gap-2 shadow-xs"
              :class="paymentMethod === 'cash'
                ? 'bg-theme-primary text-slate-950 font-black border-theme-primary shadow-md shadow-theme-primary/20'
                : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800'"
            >
              <span>💵</span>
              <span class="text-xs font-bold">{{ $t('invoices.cash') }}</span>
            </button>

            <!-- إنستاباي -->
            <button
              type="button"
              @click="paymentMethod = 'instapay'"
              class="p-3 rounded-2xl border text-center transition-all cursor-pointer flex items-center justify-center gap-2 shadow-xs"
              :class="paymentMethod === 'instapay'
                ? 'bg-theme-primary text-slate-950 font-black border-theme-primary shadow-md shadow-theme-primary/20'
                : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800'"
            >
              <span>⚡</span>
              <span class="text-xs font-bold">{{ $t('contacts.instapay') || 'إنستاباي' }}</span>
            </button>

            <!-- محفظة ذكية / فودافون كاش -->
            <button
              type="button"
              @click="paymentMethod = 'smart_wallet'"
              class="p-3 rounded-2xl border text-center transition-all cursor-pointer flex items-center justify-center gap-2 shadow-xs"
              :class="paymentMethod === 'smart_wallet'
                ? 'bg-theme-primary text-slate-950 font-black border-theme-primary shadow-md shadow-theme-primary/20'
                : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800'"
            >
              <span>📱</span>
              <span class="text-xs font-bold">{{ $t('pos.smart_wallet') || 'محفظة ذكية' }}</span>
            </button>
          </div>
        </div>

        <!-- 📌 3. سداد نقدي سريع وحساب الباقي (Quick Cash & Change Due) -->
        <div v-if="paymentType === 'cash' && paymentMethod === 'cash'" class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="text-xs font-black text-slate-700 dark:text-slate-300">
              3. {{ $t('pos.quick_cash_section') || 'سداد نقدي سريع وحساب الباقي' }}
            </label>
            <span v-if="changeDue > 0" class="text-xs font-black text-emerald-500 font-mono">
              {{ $t('pos.change_due') || 'الباقي للعميل' }}: {{ formatMoney(changeDue) }} {{ $t('common.currency') }}
            </span>
          </div>

          <!-- Quick Cash Amount Buttons -->
          <div class="flex flex-wrap gap-1.5">
            <button
              type="button"
              @click="setQuickCash(cartNetTotal)"
              class="px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 text-xs font-black transition active:scale-95 cursor-pointer"
            >
              🎯 {{ $t('pos.exact_amount') || 'المبلغ بالظبط' }} ({{ formatMoney(cartNetTotal) }})
            </button>
            <button
              v-for="amt in [50, 100, 200, 500, 1000]"
              :key="amt"
              type="button"
              @click="setQuickCash(amt)"
              class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 text-xs font-mono font-bold transition active:scale-95 cursor-pointer shadow-2xs"
            >
              {{ amt }} {{ $t('common.currency') }}
            </button>
          </div>

          <!-- Cash Received Input & Change Box -->
          <div class="grid grid-cols-2 gap-3 pt-1">
            <BaseNumberInput
              v-model="cashReceived"
              :label="$t('pos.received_from_customer') || 'المبلغ المستلم من العميل'"
              step="0.001"
              :min="0"
              :suffix="$t('common.currency')"
            />
            <div class="p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col justify-center">
              <span class="text-[10px] text-slate-500 font-bold">{{ $t('pos.change_due_label') || 'المتبقي إرجاعه للعميل' }}</span>
              <span
                class="text-base font-black font-mono"
                :class="changeDue > 0 ? 'text-emerald-500' : (changeDue < 0 ? 'text-rose-500' : 'text-slate-400')"
              >
                {{ formatMoney(Math.max(0, changeDue)) }} {{ $t('common.currency') }}
              </span>
            </div>
          </div>
        </div>

        <!-- 📌 4. خصم سريع (Quick Discount Presets) -->
        <div class="space-y-2">
          <label class="block text-xs font-black text-slate-700 dark:text-slate-300">
            4. {{ $t('pos.quick_discount_section') || 'خصم سريع على الفاتورة' }}
          </label>
          <div class="flex flex-wrap gap-1.5">
            <button
              type="button"
              @click="applyDiscountPreset(0, 'percentage')"
              class="px-3 py-1.5 rounded-xl text-xs font-bold transition active:scale-95 cursor-pointer border"
              :class="parseFloat(discountValue) === 0 ? 'bg-theme-primary text-slate-950 font-black border-theme-primary shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700'"
            >
              {{ $t('pos.no_discount') || 'بدون خصم' }}
            </button>
            <button
              v-for="rate in [5, 10, 15, 20]"
              :key="rate"
              type="button"
              @click="applyDiscountPreset(rate, 'percentage')"
              class="px-3 py-1.5 rounded-xl text-xs font-mono font-bold transition active:scale-95 cursor-pointer border"
              :class="(discountType === 'percentage' && parseFloat(discountValue) === rate) ? 'bg-theme-primary text-slate-950 font-black border-theme-primary shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700'"
            >
              {{ rate }}%
            </button>
          </div>

          <!-- Custom Discount Inputs -->
          <div class="grid grid-cols-2 gap-3 pt-1">
            <BaseNumberInput
              v-model="discountValue"
              :label="$t('invoices.discount')"
              step="0.001"
              :min="0"
            />
            <div class="space-y-1">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.discount_type') || 'نوع الخصم' }}</label>
              <select
                v-model="discountType"
                class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary font-tajawal focus:outline-none"
              >
                <option value="fixed">{{ $t('common.currency') }} (مبلغ ثابت)</option>
                <option value="percentage">% (نسبة مئوية)</option>
              </select>
            </div>
          </div>
        </div>

        <!-- 📌 5. مصاريف الشحن والخدمات الإضافية (Photos 2,3) -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="text-xs font-black text-slate-700 dark:text-slate-300">
              5. {{ $t('pos.additional_expenses_section') || 'مصاريف الشحن والخدمات الإضافية' }}
            </label>
            <span class="text-[10px] text-slate-500 font-bold">
              {{ additionalExpenses.length }} {{ $t('pos.expenses_added') || 'بنود مضافة' }}
            </span>
          </div>

          <!-- Quick Add Expense Chips -->
          <div class="flex flex-wrap gap-1.5">
            <button
              type="button"
              @click="addQuickExpense('🚚 مصاريف شحن وتوصيل', 20, 'customer_account')"
              class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold border border-slate-300 dark:border-slate-700 transition active:scale-95 cursor-pointer shadow-2xs"
            >
              🚚 شحن
            </button>
            <button
              type="button"
              @click="addQuickExpense('🎁 مصاريف تغليف وهدايا', 15, 'customer_account')"
              class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold border border-slate-300 dark:border-slate-700 transition active:scale-95 cursor-pointer shadow-2xs"
            >
              🎁 تغليف
            </button>
            <button
              type="button"
              @click="addQuickExpense('☕ خدمة / إكرامية', 10, 'customer_account')"
              class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold border border-slate-300 dark:border-slate-700 transition active:scale-95 cursor-pointer shadow-2xs"
            >
              ☕ إكرامية
            </button>
            <button
              type="button"
              @click="addCustomExpense"
              class="px-3 py-1.5 rounded-xl bg-theme-light text-theme-primary font-black text-xs border border-theme-primary/40 transition active:scale-95 cursor-pointer"
            >
              + {{ $t('pos.add_custom_expense') || 'بند مخصص' }}
            </button>
          </div>

          <!-- List of Added Additional Expenses -->
          <div v-if="additionalExpenses.length > 0" class="space-y-2 pt-2">
            <div
              v-for="(exp, expIdx) in additionalExpenses"
              :key="expIdx"
              class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2 shadow-2xs"
            >
              <div class="flex items-center gap-2">
                <input
                  v-model="exp.title"
                  type="text"
                  class="flex-1 h-9 px-3 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none"
                  :placeholder="$t('pos.expense_title_placeholder') || 'اسم البند (مثال: شحن سريع)'"
                >
                <div class="w-28">
                  <input
                    v-model.number="exp.amount"
                    type="number"
                    step="0.001"
                    min="0"
                    class="w-full h-9 px-2 text-end bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-theme-primary focus:outline-none"
                    placeholder="0.00"
                  >
                </div>
                <button
                  type="button"
                  @click="removeExpense(expIdx)"
                  class="p-2 text-slate-400 hover:text-rose-500 rounded-xl transition cursor-pointer"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>

              <!-- Cost Allocation Dropdown (Photo 3) -->
              <div class="flex items-center gap-2 text-xs">
                <span class="text-slate-500 dark:text-slate-400 text-[11px] font-bold shrink-0">
                  {{ $t('pos.who_bears_cost') || 'من يتحمل التكلفة؟' }}:
                </span>
                <select
                  v-model="exp.paid_by"
                  class="flex-1 h-8 px-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-[11px] font-bold text-slate-900 dark:text-white focus:outline-none font-tajawal"
                >
                  <option value="customer_account">👤 {{ $t('pos.add_to_customer_invoice') || 'مضاف على حساب العميل بالفاتورة (الزبون يدفعه)' }}</option>
                  <option value="treasury_cash">🏛️ {{ $t('pos.voucher_treasury_cash') || 'سند صرف: مسدد كاش من الخزينة الآن (مصروف على المحل)' }}</option>
                  <option value="treasury_instapay">⚡ {{ $t('pos.voucher_treasury_instapay') || 'سند صرف: مسدد عبر إنستاباي من الحساب (مصروف على المحل)' }}</option>
                  <option value="treasury_smart_wallet">📱 {{ $t('pos.voucher_treasury_wallet') || 'سند صرف: مسدد من المحفظة الذكية (مصروف على المحل)' }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 📌 6. شريط الإجمالي النهائي والأزرار الثابتة (Photo 4 - Sticky Bottom) -->
      <div class="pt-4 mt-4 border-t border-slate-200 dark:border-slate-800 space-y-3 font-tajawal">
        <!-- Financial Summary Row -->
        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
          <div class="space-y-0.5 text-xs text-slate-500">
            <div>{{ $t('common.total') }}: <span class="font-mono text-slate-900 dark:text-white font-bold">{{ formatMoney(cartSubtotal) }}</span></div>
            <div v-if="parseFloat(discountAmount) > 0" class="text-rose-500">{{ $t('invoices.discount') }}: <span class="font-mono">-{{ formatMoney(discountAmount) }}</span></div>
            <div v-if="customerExpensesTotal > 0" class="text-cyan-500">شحن/إضافي: <span class="font-mono">+{{ formatMoney(customerExpensesTotal) }}</span></div>
          </div>

          <div class="text-end">
            <span class="text-[10px] text-slate-400 font-bold">{{ $t('invoices.net_total') }}</span>
            <div class="text-xl font-black text-emerald-500 dark:text-emerald-400 font-mono">
              {{ formatMoney(cartNetTotal) }} <span class="text-xs font-normal font-tajawal">{{ $t('common.currency') }}</span>
            </div>
          </div>
        </div>

        <!-- Action Buttons: Save & Print vs Save & Confirm -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <!-- Save & Print Button -->
          <button
            type="button"
            @click="submitCheckoutAndPrint"
            :disabled="isSubmitting || cart.length === 0"
            class="h-12 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 font-bold text-xs sm:text-sm flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer shadow-xs"
          >
            <Printer class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('pos.save_and_print') || 'حفظ وطباعة الفاتورة' }}</span>
          </button>

          <!-- Save & Confirm Button (Enter) -->
          <button
            type="button"
            @click="submitCheckout"
            :disabled="isSubmitting || cart.length === 0"
            class="h-12 px-5 rounded-2xl bg-theme-gradient text-white shadow-theme-primary font-black text-xs sm:text-sm shadow-lg transition active:scale-95 cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50"
          >
            <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <Zap v-else class="w-4 h-4 fill-white text-white" />
            <span>{{ $t('pos.save_and_confirm') || 'حفظ واعتماد' }}</span>
            <span class="text-[10px] opacity-80 font-mono">(Enter)</span>
          </button>
        </div>
      </div>
    </AppModal>

    <!-- 👥 Customer Picker & Quick Add Modal -->
    <AppModal
      :show="showCustomerPickerModal"
      :title="isAddingNewCustomer ? '➕ تسجيل عميل جديد فوري' : '🔍 اختيار وتحديد العميل'"
      @close="showCustomerPickerModal = false"
      max-width="lg"
    >
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

      <!-- Search Existing Customer Tab -->
      <div v-if="!isAddingNewCustomer" class="space-y-3 font-tajawal">
        <div class="relative">
          <input
            v-model="customerSearchQuery"
            type="text"
            autofocus
            class="w-full h-11 pr-10 pl-4 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-theme-primary focus:outline-none"
            placeholder="ابحث باسم العميل أو رقم الهاتف أو الكود..."
          >
          <Search class="w-4 h-4 text-slate-400 absolute right-3.5 top-1/2 -translate-y-1/2" />
        </div>

        <!-- Default Cash Customer Pill -->
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

        <div class="max-h-60 overflow-y-auto space-y-1.5 pr-1 custom-scrollbar">
          <div
            v-for="c in filteredCustomerList"
            :key="c.id"
            @click="selectCustomerAndClose(c)"
            class="p-2.5 bg-slate-50 dark:bg-slate-900/60 hover:bg-slate-100 dark:hover:bg-slate-800/80 border border-slate-200 dark:border-slate-800 hover:border-theme-primary rounded-xl flex items-center justify-between cursor-pointer transition text-xs group"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-8 h-8 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-bold text-xs shrink-0">
                {{ c.name?.charAt(0) || 'ع' }}
              </div>
              <div class="min-w-0">
                <div class="font-black text-slate-900 dark:text-white truncate group-hover:text-theme-primary">{{ c.name }}</div>
                <div class="text-[10px] text-slate-400 font-mono flex items-center gap-2">
                  <span v-if="c.phone">{{ c.phone }}</span>
                  <span class="px-1.5 py-0.5 rounded text-[9px] font-bold" :class="c.price_tier === 'wholesale' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/30' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400'">
                    {{ c.price_tier === 'wholesale' ? 'جملة' : 'قطاعي' }}
                  </span>
                </div>
              </div>
            </div>

            <div class="text-end shrink-0">
              <div v-if="c.current_balance && Number(c.current_balance) !== 0" class="text-[10px] font-mono font-bold" :class="Number(c.current_balance) > 0 ? 'text-rose-500' : 'text-emerald-500'">
                {{ Number(c.current_balance) > 0 ? `عليه: ${formatMoney(c.current_balance)}` : `له: ${formatMoney(Math.abs(c.current_balance))}` }}
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

      <!-- Quick Add New Customer Form -->
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
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">رقم الهاتف</label>
          <input
            v-model="quickCustomerForm.phone"
            type="tel"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
            placeholder="01012345678"
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">شريحة السعر الافتراضية</label>
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

    <!-- 🎉 Success Invoice Modal -->
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
          <div class="text-base font-black text-theme-primary font-mono tracking-wide">
            {{ lastCreatedInvoice.invoice_number }}
          </div>
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
import { ref, reactive, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import api from '../../services/api';
import AppModal from '../../Components/Common/AppModal.vue';
import BaseNumberInput from '../../Components/Form/BaseNumberInput.vue';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    ArrowRight,
    RotateCcw,
    Search,
    UserPlus,
    Users,
    ShoppingCart,
    Trash2,
    Zap,
    Share2,
    Printer,
    Plus
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
const paymentMethod = ref('cash');
const paidAmount = ref('0.000');
const cashReceived = ref('0.000');
const additionalExpenses = ref([]);

const searchInputRef = ref(null);
const showPaymentModal = ref(false);
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
    quickCustomerForm.price_tier = 'retail';
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

const visibleDisplayLimit = ref(60);

const filteredItems = computed(() => {
    return items.value.filter(it => {
        const matchesCategory = selectedCategory.value === 'all' || 
            it.category === selectedCategory.value || 
            (it.category_id && it.category_id === selectedCategory.value);
        const matchesSearch = !searchQuery.value || 
            it.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
            (it.code && it.code.toLowerCase().includes(searchQuery.value.toLowerCase()));
        return matchesCategory && matchesSearch;
    });
});

const visibleItems = computed(() => {
    return filteredItems.value.slice(0, visibleDisplayLimit.value);
});

watch([searchQuery, selectedCategory], () => {
    visibleDisplayLimit.value = 60;
});

const onGridScroll = (e) => {
    const el = e.target;
    if (el && el.scrollHeight - el.scrollTop - el.clientHeight < 250) {
        if (visibleDisplayLimit.value < filteredItems.value.length) {
            visibleDisplayLimit.value += 60;
        }
    }
};

const cartSubtotal = computed(() => {
    return cart.value.reduce((sum, it) => sum + (parseFloat(it.quantity) || 0) * (parseFloat(it.unit_price) || 0), 0);
});

const discountAmount = computed(() => {
    const sub = cartSubtotal.value;
    const disc = parseFloat(discountValue.value) || 0;
    if (discountType.value === 'percentage') {
        return ((sub * disc) / 100).toFixed(3);
    }
    return Math.min(sub, disc).toFixed(3);
});

const customerExpensesTotal = computed(() => {
    return additionalExpenses.value
        .filter(exp => exp.paid_by === 'customer_account' || !exp.paid_by)
        .reduce((sum, exp) => sum + (parseFloat(exp.amount) || 0), 0);
});

const cartNetTotal = computed(() => {
    const sub = cartSubtotal.value;
    const disc = parseFloat(discountAmount.value) || 0;
    const extra = customerExpensesTotal.value;
    return Math.max(0, sub - disc + extra);
});

const changeDue = computed(() => {
    const received = parseFloat(cashReceived.value) || 0;
    return received - cartNetTotal.value;
});

const openPaymentModal = () => {
    if (cart.value.length === 0) return;
    cashReceived.value = cartNetTotal.value.toString();
    paidAmount.value = cartNetTotal.value.toString();
    showPaymentModal.value = true;
};

const setPaymentType = (type) => {
    paymentType.value = type;
    if (type === 'cash') {
        paidAmount.value = cartNetTotal.value.toString();
        cashReceived.value = cartNetTotal.value.toString();
    } else if (type === 'credit') {
        paidAmount.value = '0.000';
        cashReceived.value = '0.000';
    } else if (type === 'partial') {
        if (parseFloat(paidAmount.value) <= 0 || parseFloat(paidAmount.value) >= cartNetTotal.value) {
            paidAmount.value = (cartNetTotal.value / 2).toFixed(3);
        }
    }
};

const setQuickCash = (amt) => {
    cashReceived.value = parseFloat(amt).toFixed(3);
    if (paymentType.value === 'partial') {
        paidAmount.value = Math.min(cartNetTotal.value, parseFloat(amt)).toFixed(3);
    }
};

const applyDiscountPreset = (rate, type = 'percentage') => {
    discountType.value = type;
    discountValue.value = rate.toString();
};

const addQuickExpense = (title, defaultAmt = 10, paidBy = 'customer_account') => {
    additionalExpenses.value.push({
        title,
        amount: defaultAmt,
        allocation_method: 'by_quantity',
        paid_by: paidBy,
        notes: '',
    });
};

const addCustomExpense = () => {
    additionalExpenses.value.push({
        title: '',
        amount: 0,
        allocation_method: 'by_quantity',
        paid_by: 'customer_account',
        notes: '',
    });
};

const removeExpense = (idx) => {
    additionalExpenses.value.splice(idx, 1);
};

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
    cart.value[idx].quantity = isDiscrete
        ? Math.round(cart.value[idx].quantity + 1)
        : parseFloat((cart.value[idx].quantity + 1).toFixed(3));
};

const decrementQty = (idx) => {
    const isDiscrete = isDiscreteUnit(cart.value[idx].unit);
    if (cart.value[idx].quantity > 1) {
        cart.value[idx].quantity = isDiscrete
            ? Math.round(cart.value[idx].quantity - 1)
            : parseFloat((cart.value[idx].quantity - 1).toFixed(3));
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
    cashReceived.value = '0.000';
    additionalExpenses.value = [];
    paymentType.value = 'cash';
    paymentMethod.value = 'cash';
};

const togglePriceTier = () => {
    activePriceTier.value = activePriceTier.value === 'retail' ? 'wholesale' : 'retail';
    cart.value.forEach(line => {
        const product = items.value.find(p => p.id === line.item_id);
        if (product) {
            line.unit_price = getItemPrice(product);
        }
    });
};

const handleBarcodeScan = () => {
    if (!searchQuery.value) return;
    const found = items.value.find(
        p => p.code && p.code.toLowerCase() === searchQuery.value.trim().toLowerCase()
    );
    if (found) {
        addToCart(found);
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
        }
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: e.userMessage || trans('pos.add_customer_failed')
        });
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
            payment_method: paymentMethod.value,
            discount_type: discountType.value,
            discount_value: parseFloat(discountValue.value) || 0,
            paid_amount: paymentType.value === 'cash' ? cartNetTotal.value : (paymentType.value === 'credit' ? 0 : parseFloat(paidAmount.value) || 0),
            additional_expenses: additionalExpenses.value.map(exp => ({
                title: exp.title || 'مصروف إضافي',
                amount: parseFloat(exp.amount) || 0,
                allocation_method: exp.allocation_method || 'by_quantity',
                paid_by: exp.paid_by || 'customer_account',
                notes: exp.notes || '',
            })),
            items: cart.value.map(line => ({
                item_id: line.item_id,
                quantity: parseFloat(line.quantity),
                unit_price: parseFloat(line.unit_price)
            }))
        };

        const res = await api.post('/pos/checkout', payload);
        lastCreatedInvoice.value = res.data?.data;
        lastWhatsAppData.value = res.data?.whatsapp;
        showPaymentModal.value = false;
        showSuccessModal.value = true;
        clearCart();
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: trans('pos.checkout_failed'),
            text: e.userMessage || trans('pos.checkout_failed_desc')
        });
    } finally {
        isSubmitting.value = false;
    }
};

const submitCheckoutAndPrint = async () => {
    await submitCheckout();
    setTimeout(() => {
        window.print();
    }, 400);
};

const closeSuccessModal = () => {
    showSuccessModal.value = false;
    searchInputRef.value?.focus();
};

const handleGlobalShortcuts = (e) => {
    if (e.key === 'F9') {
        e.preventDefault();
        if (!showPaymentModal.value) {
            openPaymentModal();
        } else {
            submitCheckout();
        }
    } else if (e.key === 'F2') {
        e.preventDefault();
        searchInputRef.value?.focus();
    } else if (e.key === 'Enter' && showPaymentModal.value && !isSubmitting.value) {
        e.preventDefault();
        submitCheckout();
    }
};

onMounted(() => {
    loadPOSBootstrap();
    window.addEventListener('keydown', handleGlobalShortcuts);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalShortcuts);
});
</script>