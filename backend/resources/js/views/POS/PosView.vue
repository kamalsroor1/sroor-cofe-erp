<template>
  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950 select-none" dir="rtl">
    
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🔝 1. TOP HEADER & SEARCH COMMAND BAR                       -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 py-2.5 shrink-0 shadow-xs z-30">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        
        <!-- Right: Logo & Quick Navigation -->
        <div class="flex items-center gap-3 shrink-0">
          <router-link
            to="/invoices"
            class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center transition border border-slate-200 dark:border-slate-700"
            :title="$t('pos.back_to_invoices')"
          >
            <ArrowRight class="w-4 h-4" />
          </router-link>

          <div class="flex items-center gap-2">
            <div class="w-10 h-10 rounded-xl bg-theme-primary text-slate-950 flex items-center justify-center font-black text-lg shadow-xs">
              ⚡
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h1 class="text-sm font-black text-slate-900 dark:text-white leading-none">{{ $t('pos.pos_fast_title') || 'نقطة البيع السريعة' }}</h1>
                <span class="px-1.5 py-0.2 text-[10px] font-mono font-bold rounded bg-theme-light text-theme-primary">v{{ appVersion }}</span>
              </div>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-[11px] text-slate-500 dark:text-slate-400 font-bold">{{ activeStore?.name || $t('common.main_branch') }}</span>
                <span class="text-slate-300 dark:text-slate-700">•</span>
                <span v-if="activeShift" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  وردية #{{ activeShift.shift_number }}
                </span>
                <span v-else class="text-[10px] text-rose-500 font-bold">لا توجد وردية مفتوحة</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Center: Giant Barcode / Product Search Box with Dropdown -->
        <div class="flex-1 max-w-3xl relative">
          <div class="relative">
            <div class="absolute inset-y-0 start-0 ps-3.5 flex items-center pointer-events-none text-slate-400">
              <Search class="w-5 h-5" />
            </div>
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              class="w-full h-12 ps-11 pe-24 bg-slate-50 dark:bg-slate-950 border-2 border-slate-300 dark:border-slate-700 focus:border-theme-primary focus:ring-4 focus:ring-theme-primary/20 rounded-2xl text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 transition-all focus:outline-none"
              :placeholder="$t('pos.search_placeholder_long') || 'ابحث باسم الصنف، الكود، أو امسح الباركود مباشرة... (F2)'"
              @keydown.down.prevent="navigateDropdown('down')"
              @keydown.up.prevent="navigateDropdown('up')"
              @keydown.enter.prevent="selectHighlightedOrFirstItem"
              @keydown.esc="closeDropdown"
              @focus="isSearchFocused = true"
            />
            
            <div class="absolute inset-y-0 end-0 pe-2 flex items-center gap-1.5">
              <button
                v-if="searchQuery"
                type="button"
                @click="searchQuery = ''; searchInputRef?.focus()"
                class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg"
              >
                ✕
              </button>
              <kbd class="hidden sm:inline-block px-2 py-1 text-[10px] font-mono font-bold bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-lg border border-slate-300 dark:border-slate-700">F2</kbd>
            </div>
          </div>

          <!-- 🌟 FLOATING LIVE SEARCH RESULTS DROPDOWN -->
          <div
            v-if="isSearchFocused && searchDropdownResults.length > 0"
            class="absolute top-full start-0 end-0 mt-2 bg-white dark:bg-slate-900 border-2 border-theme-primary/50 rounded-2xl shadow-2xl overflow-hidden z-50 max-h-96 overflow-y-auto custom-scrollbar animate-in fade-in slide-in-from-top-2 duration-150"
          >
            <div class="p-2 bg-slate-50 dark:bg-slate-950/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-[11px] font-bold text-slate-500">
              <span>نتائج البحث ({{ searchDropdownResults.length }}) • استخدم الأسهم ⬆ ⬇ ثم اضغط Enter</span>
              <span class="text-theme-primary">الأسعار: {{ activePriceTier === 'wholesale' ? 'جملة' : 'قطاعي' }}</span>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
              <button
                v-for="(item, idx) in searchDropdownResults"
                :key="item.id"
                type="button"
                @click="addItemFromDropdown(item)"
                @mouseenter="highlightedIndex = idx"
                class="w-full p-3 flex items-center justify-between text-start transition-all cursor-pointer group"
                :class="highlightedIndex === idx
                  ? 'bg-theme-light dark:bg-slate-800 text-slate-950 dark:text-white ring-1 ring-inset ring-theme-primary'
                  : 'hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-800 dark:text-slate-200'"
              >
                <!-- Item Info -->
                <div class="flex items-center gap-3 min-w-0 flex-1">
                  <div
                    class="w-9 h-9 rounded-xl flex items-center justify-center font-mono font-bold text-xs shrink-0"
                    :class="highlightedIndex === idx ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
                  >
                    +
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="font-black text-sm text-slate-950 dark:text-white truncate flex items-center gap-2">
                      <span>{{ item.name }}</span>
                      <span v-if="item.category" class="text-[10px] font-normal px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 font-tajawal">{{ item.category }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500 font-mono">
                      <span class="font-bold text-slate-400">{{ item.code || '—' }}</span>
                      <span>•</span>
                      <span
                        class="font-bold px-1.5 py-0.2 rounded text-[11px]"
                        :class="item.current_stock > 0 ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10' : 'text-rose-500 bg-rose-500/10'"
                      >
                        {{ item.current_stock > 0 ? '📦 متاح: ' : '⚠️ غير متوفر: ' }} {{ formatMoney(item.current_stock) }} {{ item.unit }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Prices Breakdown & Action -->
                <div class="flex items-center gap-4 shrink-0 ms-3 text-end">
                  <div class="space-y-0.5">
                    <div v-if="item.price_wholesale || item.min_selling_price" class="text-[10px] font-mono text-purple-600 dark:text-purple-400 font-bold">
                      أقل بيع: {{ formatMoney(item.min_selling_price || item.price_wholesale) }}
                    </div>
                    <div class="text-base font-black font-mono text-emerald-600 dark:text-emerald-400">
                      {{ formatMoney(getItemPrice(item)) }} <span class="text-[10px] font-normal text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
                    </div>
                  </div>

                  <span class="hidden sm:inline-block text-xs font-bold px-2.5 py-1 rounded-lg bg-theme-primary text-slate-950 group-hover:scale-105 transition-transform shadow-xs">
                    إضافة ⏎
                  </span>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- Left: Customer, Tier & Action Buttons -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- Customer Selection Button -->
          <button
            type="button"
            @click="openCustomerPicker"
            class="px-3 py-2 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700/80 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer shadow-2xs"
          >
            <Users class="w-4 h-4 text-theme-primary shrink-0" />
            <div class="text-start leading-tight min-w-0">
              <div class="text-slate-900 dark:text-white truncate max-w-[130px] font-black">{{ selectedCustomer.name }}</div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ selectedCustomer.phone || 'نقدي عام' }}</div>
            </div>
          </button>

          <!-- Price Tier Toggle (Retail / Wholesale) -->
          <div class="flex p-0.5 bg-slate-200 dark:bg-slate-800 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold">
            <button
              type="button"
              @click="setPriceTier('retail')"
              class="px-2.5 py-1 rounded-lg transition"
              :class="activePriceTier === 'retail' ? 'bg-white dark:bg-slate-900 text-theme-primary font-black shadow-xs' : 'text-slate-600 dark:text-slate-400'"
            >
              قطاعي
            </button>
            <button
              type="button"
              @click="setPriceTier('wholesale')"
              class="px-2.5 py-1 rounded-lg transition"
              :class="activePriceTier === 'wholesale' ? 'bg-white dark:bg-slate-900 text-purple-500 font-black shadow-xs' : 'text-slate-600 dark:text-slate-400'"
            >
              جملة
            </button>
          </div>

          <!-- Clear Cart -->
          <button
            type="button"
            @click="clearCart"
            :disabled="cart.length === 0"
            class="p-2.5 bg-slate-100 hover:bg-rose-500/20 hover:text-rose-600 dark:bg-slate-800 dark:hover:bg-rose-500/20 dark:hover:text-rose-400 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700 rounded-xl transition disabled:opacity-30 cursor-pointer"
            title="إفراغ السلة بالكامل"
          >
            <RotateCcw class="w-4 h-4" />
          </button>
        </div>

      </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🖥️ 2. MAIN POS WORKSPACE: INVOICE TABLE (65%) + CHECKOUT (35%) -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 overflow-hidden">
      
      <!-- 🧾 RIGHT SECTION: INVOICE TABLE & QUICK ITEMS (col-span-7 or 8) -->
      <main class="lg:col-span-7 xl:col-span-8 flex flex-col justify-between p-4 bg-slate-50 dark:bg-slate-950 overflow-hidden border-e border-slate-200 dark:border-slate-800">
        
        <!-- Table Card Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
          <div class="flex items-center gap-2">
            <span class="text-lg">🧾</span>
            <h2 class="text-sm font-black text-slate-900 dark:text-white">بنود الفاتورة الحالية</h2>
            <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
              {{ cart.length }} صنف ({{ cartTotalQuantity }} قطعة)
            </span>
          </div>

          <div class="text-xs text-slate-500 dark:text-slate-400 font-bold">
            اضغط <kbd class="px-1.5 py-0.5 bg-slate-200 dark:bg-slate-800 rounded font-mono text-[10px]">F2</kbd> للبحث أو امسح الباركود
          </div>
        </div>

        <!-- 📋 ACTIVE INVOICE ITEMS TABLE -->
        <div class="flex-1 overflow-y-auto my-3 custom-scrollbar rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 shadow-xs">
          
          <table v-if="cart.length > 0" class="w-full text-start text-xs border-collapse">
            <thead class="bg-slate-100/90 dark:bg-slate-800/90 text-slate-700 dark:text-slate-300 font-bold sticky top-0 border-b border-slate-200 dark:border-slate-700/80 z-10">
              <tr>
                <th class="p-3 text-center w-12">#</th>
                <th class="p-3 text-start">الصنف والكود</th>
                <th class="p-3 text-center w-36">الكمية</th>
                <th class="p-3 text-start w-28">السعر</th>
                <th class="p-3 text-start w-32">الإجمالي</th>
                <th class="p-3 text-center w-14">حذف</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/70 font-tajawal">
              <tr
                v-for="(item, idx) in cart"
                :key="item.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
              >
                <!-- Row Number -->
                <td class="p-3 text-center font-mono font-bold text-slate-400">
                  {{ idx + 1 }}
                </td>

                <!-- Item Info -->
                <td class="p-3">
                  <div class="font-black text-sm text-slate-950 dark:text-white leading-snug">
                    {{ item.name }}
                  </div>
                  <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 font-mono">
                    <span class="font-bold text-slate-400">{{ item.code || '—' }}</span>
                    <span>•</span>
                    <span class="text-slate-500 font-tajawal">{{ item.unit || 'قطعة' }}</span>
                    <span v-if="item.price_wholesale" class="text-purple-600 dark:text-purple-400 font-bold" title="أقل سعر جملة">
                      (أقل بيع: {{ formatMoney(item.min_selling_price || item.price_wholesale) }})
                    </span>
                  </div>
                </td>

                <!-- Quantity Controls -->
                <td class="p-3">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      type="button"
                      @click="decreaseCartItemQty(idx)"
                      class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-500/20 hover:text-rose-500 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black transition active:scale-95 cursor-pointer border border-slate-200 dark:border-slate-700"
                    >
                      -
                    </button>
                    <input
                      type="number"
                      :value="item.quantity"
                      @input="onCartQtyInput(idx, $event)"
                      step="1"
                      min="1"
                      class="w-16 h-8 text-center font-mono font-black text-sm bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                    />
                    <button
                      type="button"
                      @click="increaseCartItemQty(idx)"
                      class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-emerald-500/20 hover:text-emerald-500 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black transition active:scale-95 cursor-pointer border border-slate-200 dark:border-slate-700"
                    >
                      +
                    </button>
                  </div>
                </td>

                <!-- Unit Price -->
                <td class="p-3 font-mono font-black text-sm text-slate-800 dark:text-slate-200">
                  {{ formatMoney(item.unit_price) }}
                </td>

                <!-- Line Subtotal -->
                <td class="p-3 font-mono font-black text-base text-emerald-600 dark:text-emerald-400">
                  {{ formatMoney(item.quantity * item.unit_price) }}
                </td>

                <!-- Delete Action -->
                <td class="p-3 text-center">
                  <button
                    type="button"
                    @click="removeFromCart(idx)"
                    class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-lg transition cursor-pointer"
                    title="حذف البند من الفاتورة"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Empty Cart State -->
          <div v-else class="h-full flex flex-col items-center justify-center p-12 text-center text-slate-400">
            <div class="w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-2xl mb-3 shadow-inner">
              🛒
            </div>
            <h3 class="text-base font-black text-slate-800 dark:text-slate-200">الفاتورة فارغة حالياً</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm">
              اكتب اسم الصنف في شريط البحث أعلاه أو مرر قارئ الباركود، أو اضغط على أحد الأصناف الشائعة بالأسفل لإضافته فوراً.
            </p>
          </div>

        </div>

        <!-- ⭐ BOTTOM QUICK PINNED BAR (الأصناف الشائعة / الأكثر مبيعاً) -->
        <div class="pt-2 border-t border-slate-200 dark:border-slate-800 shrink-0">
          <div class="flex items-center justify-between text-xs font-bold text-slate-500 mb-2">
            <span class="flex items-center gap-1.5">
              <span>⭐</span>
              <span>الأصناف الشائعة والسريعة</span>
            </span>
            <span class="text-[11px] text-slate-400">إضافة بلمسة واحدة</span>
          </div>

          <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
            <button
              v-for="item in quickPinnedItems"
              :key="item.id"
              type="button"
              @click="addToCart(item)"
              class="px-3 py-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-theme-primary text-start transition shrink-0 active:scale-95 cursor-pointer shadow-2xs group flex items-center gap-2.5 max-w-[220px]"
            >
              <div class="w-7 h-7 rounded-lg bg-theme-light text-theme-primary flex items-center justify-center text-xs font-black shrink-0 group-hover:bg-theme-primary group-hover:text-slate-950 transition-colors">
                +
              </div>
              <div class="min-w-0">
                <div class="text-xs font-black text-slate-900 dark:text-white truncate">{{ item.name }}</div>
                <div class="text-[11px] font-mono font-bold text-emerald-500 mt-0.5">{{ formatMoney(getItemPrice(item)) }} ج.م</div>
              </div>
            </button>
          </div>
        </div>

      </main>

      <!-- 💳 LEFT SECTION: FINANCIAL TOTALS & CHECKOUT PANEL (col-span-5 or 4) -->
      <aside class="lg:col-span-5 xl:col-span-4 flex flex-col justify-between p-4 bg-white dark:bg-slate-900 border-s border-slate-200 dark:border-slate-800 shadow-lg overflow-y-auto custom-scrollbar space-y-4">
        
        <!-- 1. Financial Breakdown Card -->
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
          
          <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
            <span>المجموع الفرعي ({{ cart.length }} صنف):</span>
            <span class="font-mono font-black text-sm text-slate-900 dark:text-white">{{ formatMoney(cartSubtotal) }} {{ $t('common.currency') }}</span>
          </div>

          <!-- Quick Discount Row -->
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-1.5">
            <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
              <span>الخصم:</span>
              <span class="font-mono font-black text-sm text-rose-500">- {{ formatMoney(discountAmount) }} {{ $t('common.currency') }}</span>
            </div>
            
            <div class="flex flex-wrap gap-1">
              <button
                type="button"
                @click="applyDiscountPreset(0, 'percentage')"
                class="px-2 py-0.5 rounded-lg text-[11px] font-bold border transition"
                :class="parseFloat(discountValue) === 0 ? 'bg-theme-primary text-slate-950 font-black border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800'"
              >
                0%
              </button>
              <button
                v-for="r in [5, 10, 15, 20]"
                :key="r"
                type="button"
                @click="applyDiscountPreset(r, 'percentage')"
                class="px-2 py-0.5 rounded-lg text-[11px] font-mono font-bold border transition"
                :class="(discountType === 'percentage' && parseFloat(discountValue) === r) ? 'bg-theme-primary text-slate-950 font-black border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800'"
              >
                {{ r }}%
              </button>
            </div>
          </div>

          <!-- Customer Extra Expenses (Shipping / Services) -->
          <div v-if="customerExpensesTotal > 0" class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
            <span>مصاريف إضافية / شحن:</span>
            <span class="font-mono font-black text-sm text-emerald-500">+ {{ formatMoney(customerExpensesTotal) }} {{ $t('common.currency') }}</span>
          </div>

          <!-- GIANT NET TOTAL DUE -->
          <div class="pt-3 border-t-2 border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <span class="text-sm font-black text-slate-900 dark:text-white">الصافي النهائي:</span>
            <span class="text-3xl font-black font-mono text-emerald-600 dark:text-emerald-400 tracking-tight">
              {{ formatMoney(cartNetTotal) }} <span class="text-sm font-bold text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
            </span>
          </div>

        </div>

        <!-- 2. Payment Type (كاش / آجل / جزئي) -->
        <div class="space-y-2">
          <label class="block text-xs font-black text-slate-700 dark:text-slate-300">نوع الفاتورة والسداد:</label>
          <div class="grid grid-cols-3 gap-2">
            <button
              type="button"
              @click="setPaymentType('cash')"
              class="p-2.5 rounded-xl border text-center transition active:scale-95 cursor-pointer"
              :class="paymentType === 'cash' ? 'bg-emerald-500/10 border-emerald-500 text-emerald-600 dark:text-emerald-400 font-black shadow-xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
            >
              <div class="text-xs">كاش فوري</div>
            </button>
            <button
              type="button"
              @click="setPaymentType('credit')"
              class="p-2.5 rounded-xl border text-center transition active:scale-95 cursor-pointer"
              :class="paymentType === 'credit' ? 'bg-rose-500/10 border-rose-500 text-rose-600 dark:text-rose-400 font-black shadow-xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
            >
              <div class="text-xs">آجل ذمم</div>
            </button>
            <button
              type="button"
              @click="setPaymentType('partial')"
              class="p-2.5 rounded-xl border text-center transition active:scale-95 cursor-pointer"
              :class="paymentType === 'partial' ? 'bg-amber-500/10 border-amber-500 text-amber-600 dark:text-amber-400 font-black shadow-xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
            >
              <div class="text-xs">دفع جزئي</div>
            </button>
          </div>
        </div>

        <!-- 3. Payment Method & Fast Cash Calculator -->
        <div v-if="paymentType !== 'credit'" class="space-y-3 p-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
          
          <!-- Payment Method Chips -->
          <div class="flex items-center gap-2">
            <button
              v-for="m in [
                { key: 'cash', label: '💵 كاش نقدي' },
                { key: 'instapay', label: '⚡ إنستاباي' },
                { key: 'smart_wallet', label: '📱 محفظة ذكية' }
              ]"
              :key="m.key"
              type="button"
              @click="paymentMethod = m.key"
              class="flex-1 py-1.5 rounded-lg text-xs font-bold border transition text-center"
              :class="paymentMethod === m.key ? 'bg-theme-primary text-slate-950 font-black border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800'"
            >
              {{ m.label }}
            </button>
          </div>

          <!-- Quick Cash Amount Buttons -->
          <div class="flex flex-wrap gap-1">
            <button
              type="button"
              @click="setQuickCash(cartNetTotal)"
              class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-black border border-emerald-500/30"
            >
              🎯 المبلغ بالظبط
            </button>
            <button
              v-for="amt in [50, 100, 200, 500, 1000, 2000]"
              :key="amt"
              type="button"
              @click="setQuickCash(amt)"
              class="px-2 py-1 rounded-lg bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 text-xs font-mono font-bold"
            >
              {{ amt }}
            </button>
          </div>

          <!-- Cash Received & Change Due -->
          <div class="grid grid-cols-2 gap-2 pt-1">
            <div>
              <label class="block text-[11px] font-bold text-slate-500 mb-1">المستلم من العميل:</label>
              <input
                type="number"
                v-model="cashReceived"
                step="0.001"
                min="0"
                class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-sm font-mono font-black text-slate-950 dark:text-white focus:outline-none focus:ring-2 focus:ring-theme-primary"
              />
            </div>
            <div class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col justify-center text-center">
              <span class="text-[10px] text-slate-400 font-bold">الباقي للعميل:</span>
              <span
                class="text-base font-black font-mono"
                :class="changeDue > 0 ? 'text-emerald-500' : (changeDue < 0 ? 'text-rose-500' : 'text-slate-400')"
              >
                {{ formatMoney(Math.max(0, changeDue)) }} ج.م
              </span>
            </div>
          </div>

        </div>

        <!-- 4. Execution Buttons -->
        <div class="space-y-2 pt-2">
          <button
            type="button"
            @click="submitInvoice(true)"
            :disabled="cart.length === 0 || isSubmitting"
            class="w-full h-14 bg-theme-primary hover:opacity-95 text-slate-950 rounded-2xl font-black text-base transition-all duration-200 active:scale-[0.98] shadow-lg flex items-center justify-center gap-3 cursor-pointer disabled:opacity-30"
          >
            <span v-if="isSubmitting" class="w-5 h-5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
            <span v-else class="text-xl">✅</span>
            <span>حفظ واعتماد الفاتورة (F9 / Enter)</span>
          </button>

          <button
            type="button"
            @click="submitInvoice(true)"
            :disabled="cart.length === 0 || isSubmitting"
            class="w-full h-11 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-xl font-bold text-xs transition border border-slate-200 dark:border-slate-700 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-30"
          >
            <Printer class="w-4 h-4" />
            <span>حفظ وطباعة الفاتورة</span>
          </button>
        </div>

      </aside>

    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 👥 CUSTOMER PICKER MODAL                                    -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <AppModal
      :show="showCustomerPickerModal"
      :title="$t('pos.select_customer') || 'اختيار أو إضافة عميل'"
      max-width="lg"
      @close="showCustomerPickerModal = false"
    >
      <div class="space-y-4 font-tajawal">
        <div class="flex items-center justify-between gap-2">
          <input
            v-model="customerSearchQuery"
            type="text"
            placeholder="ابحث باسم العميل أو رقم الهاتف..."
            class="flex-1 h-11 px-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-theme-primary"
          />
          <button
            type="button"
            @click="isAddingNewCustomer = !isAddingNewCustomer"
            class="px-3.5 py-2.5 bg-theme-primary text-slate-950 rounded-xl text-xs font-black shrink-0 transition"
          >
            {{ isAddingNewCustomer ? 'إلغاء' : '+ عميل جديد' }}
          </button>
        </div>

        <!-- Quick Add Customer Form -->
        <div v-if="isAddingNewCustomer" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3">
          <div class="text-xs font-black text-slate-900 dark:text-white">إضافة عميل سريع:</div>
          <div class="grid grid-cols-2 gap-2">
            <input v-model="quickCustomerName" type="text" placeholder="اسم العميل *" class="h-10 px-3 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-xs" />
            <input v-model="quickCustomerPhone" type="text" placeholder="رقم الهاتف" class="h-10 px-3 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-mono" dir="ltr" />
          </div>
          <button
            type="button"
            @click="submitQuickCustomer"
            :disabled="!quickCustomerName || isSubmittingQuickCustomer"
            class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black transition disabled:opacity-50"
          >
            حفظ واختيار العميل
          </button>
        </div>

        <!-- Customers List -->
        <div class="max-h-64 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl">
          <button
            v-for="cust in filteredCustomerList"
            :key="cust.id"
            type="button"
            @click="selectCustomer(cust)"
            class="w-full p-3 flex items-center justify-between text-start hover:bg-slate-50 dark:hover:bg-slate-800/80 transition"
            :class="selectedCustomerId === cust.id ? 'bg-theme-light dark:bg-slate-800 text-theme-primary font-black' : 'text-slate-700 dark:text-slate-300'"
          >
            <div>
              <div class="font-bold text-xs text-slate-900 dark:text-white">{{ cust.name }}</div>
              <div class="text-[10px] text-slate-500 font-mono">{{ cust.phone || 'بدون هاتف' }}</div>
            </div>
            <div class="text-end">
              <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold" :class="cust.current_balance > 0 ? 'bg-rose-500/10 text-rose-500' : 'bg-emerald-500/10 text-emerald-500'">
                رصيد: {{ formatMoney(cust.current_balance || 0) }} ج.م
              </span>
            </div>
          </button>
        </div>
      </div>
    </AppModal>

    <!-- 🎉 SUCCESS MODAL & PRINT BRIDGE -->
    <AppModal
      :show="showSuccessModal"
      title="تم اعتماد الفاتورة بنجاح ✓"
      max-width="md"
      @close="showSuccessModal = false"
    >
      <div class="text-center p-4 space-y-4 font-tajawal">
        <div class="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 flex items-center justify-center text-3xl mx-auto">
          ✓
        </div>
        <div>
          <h3 class="text-base font-black text-slate-900 dark:text-white">فاتورة رقم #{{ lastCreatedInvoice?.invoice_number }}</h3>
          <p class="text-xs text-slate-500 mt-1">الصافي: {{ formatMoney(lastCreatedInvoice?.net_amount) }} ج.م</p>
        </div>
        <div class="flex gap-2">
          <button
            type="button"
            @click="printLastInvoice"
            class="flex-1 py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-xl text-xs font-black transition flex items-center justify-center gap-2"
          >
            <Printer class="w-4 h-4" />
            <span>طباعة الإيصال</span>
          </button>
          <button
            type="button"
            @click="showSuccessModal = false"
            class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-black transition"
          >
            فاتورة جديدة (Enter)
          </button>
        </div>
      </div>
    </AppModal>

  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import api from '../../services/api';
import AppModal from '../../Components/Common/AppModal.vue';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import versionData from '../../version.json';
import {
    ArrowRight,
    Search,
    Users,
    Trash2,
    Printer,
    RotateCcw
} from 'lucide-vue-next';

const appVersion = ref(versionData?.version || '1.0.10');

const items = ref([]);
const categories = ref([]);
const customers = ref([]);
const activeStore = ref(null);
const activeShift = ref(null);

const isLoading = ref(false);
const isSubmitting = ref(false);

const cart = ref([]);
const searchQuery = ref('');
const searchInputRef = ref(null);
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
const isAddingNewCustomer = ref(false);
const quickCustomerName = ref('');
const quickCustomerPhone = ref('');
const isSubmittingQuickCustomer = ref(false);

const showSuccessModal = ref(false);
const lastCreatedInvoice = ref(null);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getItemPrice = (item) => {
    return activePriceTier.value === 'wholesale' ? item.price_wholesale : item.price_retail;
};

// 🔍 LIVE SEARCH DROPDOWN RESULTS (Top 15 matches)
const searchDropdownResults = computed(() => {
    if (!searchQuery.value || !searchQuery.value.trim()) return [];
    const q = searchQuery.value.trim().toLowerCase();
    return items.value
        .filter(it => (it.name && it.name.toLowerCase().includes(q)) || (it.code && it.code.toLowerCase().includes(q)))
        .slice(0, 15);
});

// ⭐ Quick Pinned Items for 1-click add (top 10 items)
const quickPinnedItems = computed(() => {
    return items.value.slice(0, 10);
});

const selectedCustomer = computed(() => {
    return customers.value.find(c => c.id === selectedCustomerId.value) || { id: null, name: 'عميل نقدي عام' };
});

const filteredCustomerList = computed(() => {
    if (!customerSearchQuery.value) return customers.value;
    const q = customerSearchQuery.value.trim().toLowerCase();
    return customers.value.filter(c => (c.name && c.name.toLowerCase().includes(q)) || (c.phone && c.phone.includes(q)));
});

const cartSubtotal = computed(() => {
    return cart.value.reduce((sum, it) => sum + (parseFloat(it.quantity) || 0) * (parseFloat(it.unit_price) || 0), 0);
});

const cartTotalQuantity = computed(() => {
    return cart.value.reduce((sum, it) => sum + (parseFloat(it.quantity) || 0), 0);
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
    const exp = customerExpensesTotal.value;
    return Math.max(0, sub - disc + exp);
});

const changeDue = computed(() => {
    const rcv = parseFloat(cashReceived.value) || 0;
    const req = cartNetTotal.value;
    return rcv - req;
});

// ⌨️ KEYBOARD NAVIGATION FOR SEARCH DROPDOWN
const navigateDropdown = (dir) => {
    if (searchDropdownResults.value.length === 0) return;
    if (dir === 'down') {
        highlightedIndex.value = (highlightedIndex.value + 1) % searchDropdownResults.value.length;
    } else if (dir === 'up') {
        highlightedIndex.value = (highlightedIndex.value - 1 + searchDropdownResults.value.length) % searchDropdownResults.value.length;
    }
};

const selectHighlightedOrFirstItem = () => {
    if (searchDropdownResults.value.length > 0) {
        const selected = searchDropdownResults.value[highlightedIndex.value] || searchDropdownResults.value[0];
        addItemFromDropdown(selected);
    } else if (cart.value.length > 0 && !isSearchFocused.value) {
        submitInvoice(true);
    }
};

const addItemFromDropdown = (item) => {
    addToCart(item);
    searchQuery.value = '';
    isSearchFocused.value = false;
    highlightedIndex.value = 0;
    nextTick(() => searchInputRef.value?.focus());
};

const closeDropdown = () => {
    isSearchFocused.value = false;
};

// 🛒 CART MANAGEMENT
const addToCart = (item) => {
    const existingIdx = cart.value.findIndex(c => c.id === item.id);
    const unitPrice = getItemPrice(item);

    if (existingIdx !== -1) {
        cart.value[existingIdx].quantity += 1;
    } else {
        cart.value.push({
            id: item.id,
            name: item.name,
            code: item.code,
            unit: item.unit || 'قطعة',
            cost_price: item.cost_price,
            price_retail: item.price_retail,
            price_wholesale: item.price_wholesale,
            min_selling_price: item.min_selling_price,
            unit_price: unitPrice,
            quantity: 1,
            current_stock: item.current_stock
        });
    }

    if (paymentType.value === 'cash') {
        paidAmount.value = cartNetTotal.value.toString();
        cashReceived.value = cartNetTotal.value.toString();
    }
};

const increaseCartItemQty = (idx) => {
    cart.value[idx].quantity += 1;
};

const decreaseCartItemQty = (idx) => {
    if (cart.value[idx].quantity > 1) {
        cart.value[idx].quantity -= 1;
    } else {
        removeFromCart(idx);
    }
};

const onCartQtyInput = (idx, event) => {
    const val = parseFloat(event?.target?.value) || 1;
    cart.value[idx].quantity = Math.max(1, Math.round(val));
};

const removeFromCart = (idx) => {
    cart.value.splice(idx, 1);
};

const clearCart = () => {
    cart.value = [];
    discountValue.value = '0';
    cashReceived.value = '0.000';
    additionalExpenses.value = [];
    nextTick(() => searchInputRef.value?.focus());
};

// 🏷️ TIER & DISCOUNTS
const setPriceTier = (tier) => {
    activePriceTier.value = tier;
    cart.value.forEach(item => {
        item.unit_price = tier === 'wholesale' ? (item.price_wholesale || item.unit_price) : item.price_retail;
    });
};

const applyDiscountPreset = (rate, type = 'percentage') => {
    discountType.value = type;
    discountValue.value = rate.toString();
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
        paidAmount.value = (cartNetTotal.value / 2).toFixed(3);
    }
};

const setQuickCash = (amt) => {
    cashReceived.value = parseFloat(amt).toFixed(3);
    if (paymentType.value === 'partial') {
        paidAmount.value = Math.min(cartNetTotal.value, parseFloat(amt)).toFixed(3);
    }
};

// 👤 CUSTOMER ACTIONS
const openCustomerPicker = () => {
    customerSearchQuery.value = '';
    isAddingNewCustomer.value = false;
    showCustomerPickerModal.value = true;
};

const selectCustomer = (cust) => {
    selectedCustomerId.value = cust.id;
    if (cust.price_tier) {
        setPriceTier(cust.price_tier);
    }
    showCustomerPickerModal.value = false;
};

const submitQuickCustomer = async () => {
    if (!quickCustomerName.value) return;
    isSubmittingQuickCustomer.value = true;
    try {
        const res = await api.post('/customers/quick', {
            name: quickCustomerName.value,
            phone: quickCustomerPhone.value
        });
        const created = res.data?.data;
        if (created) {
            customers.value.unshift(created);
            selectedCustomerId.value = created.id;
            showCustomerPickerModal.value = false;
            quickCustomerName.value = '';
            quickCustomerPhone.value = '';
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: e.response?.data?.message || 'فشل إضافة العميل' });
    } finally {
        isSubmittingQuickCustomer.value = false;
    }
};

// 🚀 CHECKOUT & INVOICE SUBMISSION
const submitInvoice = async (printAfter = false) => {
    if (cart.value.length === 0) return;
    isSubmitting.value = true;

    try {
        const payload = {
            customer_id: selectedCustomerId.value,
            invoice_type: paymentType.value,
            payment_method: paymentMethod.value,
            paid_amount: parseFloat(paidAmount.value || 0),
            discount_type: discountType.value,
            discount_value: parseFloat(discountValue.value || 0),
            items: cart.value.map(it => ({
                item_id: it.id,
                quantity: it.quantity,
                unit_price: it.unit_price,
                notes: ''
            })),
            additional_expenses: additionalExpenses.value
        };

        const response = await api.post('/pos/checkout', payload);
        const data = response.data?.data;
        lastCreatedInvoice.value = data;

        if (printAfter) {
            printLastInvoice();
        }

        showSuccessModal.value = true;
        clearCart();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'فشل حفظ الفاتورة',
            text: error.response?.data?.message || 'حدث خطأ أثناء معالجة الفاتورة'
        });
    } finally {
        isSubmitting.value = false;
    }
};

const printLastInvoice = () => {
    if (!lastCreatedInvoice.value?.id) return;
    const printUrl = `/invoices/${lastCreatedInvoice.value.id}/print`;
    window.open(printUrl, '_blank', 'width=800,height=600');
};

// 📥 DATA BOOTSTRAP
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
    } catch (e) {
        console.error('POS Bootstrap failed:', e);
    } finally {
        isLoading.value = false;
        nextTick(() => searchInputRef.value?.focus());
    }
};

// Global Shortcut Listener
const handleGlobalKeydown = (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        searchInputRef.value?.focus();
    } else if (e.key === 'F9') {
        e.preventDefault();
        submitInvoice(true);
    }
};

onMounted(() => {
    loadPOSBootstrap();
    window.addEventListener('keydown', handleGlobalKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.3);
  border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.5);
}
</style>