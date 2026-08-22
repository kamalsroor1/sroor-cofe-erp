<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal text-slate-900 dark:text-slate-100">
    
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 1. PAGE HEADER & GLOBAL ACTIONS                             -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <PageHeader
      :title="$t('invoices.title')"
      :subtitle="$t('invoices.subtitle')"
      :icon="'🛒'"
    >
      <template #actions>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Toggle Sidebar Filter Button -->
          <button
            type="button"
            @click="isFilterSidebarOpen = !isFilterSidebarOpen"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer border"
            :class="isFilterSidebarOpen || activeFiltersCount > 0 
              ? 'bg-theme-primary/10 border-theme-primary text-theme-primary font-black shadow-xs' 
              : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
          >
            <SlidersHorizontal class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('common.filter') || 'فلاتر البحث' }}</span>
            <span
              v-if="activeFiltersCount > 0"
              class="w-5 h-5 rounded-full bg-theme-primary text-white text-[10px] font-black flex items-center justify-center"
            >
              {{ activeFiltersCount }}
            </span>
          </button>

          <!-- Global Actions Dropdown Menu -->
          <div class="relative" ref="globalActionDropdownRef">
            <button
              type="button"
              @click="isGlobalActionOpen = !isGlobalActionOpen"
              class="px-4 py-2.5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 transition-all flex items-center gap-2 cursor-pointer shadow-xs"
            >
              <FileSpreadsheet class="w-4 h-4 text-emerald-500" />
              <span>خيارات وإجراءات</span>
              <ChevronDown class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="{ 'rotate-180': isGlobalActionOpen }" />
            </button>

            <!-- Dropdown Menu items -->
            <Transition name="fade">
              <div
                v-if="isGlobalActionOpen"
                class="absolute left-0 top-full mt-2 w-56 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-1.5 z-50 space-y-1 font-tajawal"
              >
                <button
                  type="button"
                  @click="exportToExcel(); isGlobalActionOpen = false"
                  class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded-xl transition cursor-pointer text-start"
                >
                  <Download class="w-4 h-4 text-emerald-500" />
                  <span>تصدير الفواتير (Excel / CSV)</span>
                </button>

                <button
                  type="button"
                  @click="printFilteredInvoicesReport(); isGlobalActionOpen = false"
                  class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-cyan-500 hover:bg-cyan-50 dark:hover:bg-cyan-950/40 rounded-xl transition cursor-pointer text-start"
                >
                  <Printer class="w-4 h-4 text-cyan-500" />
                  <span>طباعة تقرير المبيعات المفلتر</span>
                </button>

                <button
                  type="button"
                  @click="fetchInvoices(pagination.current_page); isGlobalActionOpen = false"
                  class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-theme-primary hover:bg-slate-50 dark:hover:bg-slate-900 rounded-xl transition cursor-pointer text-start"
                >
                  <RefreshCw class="w-4 h-4 text-theme-primary" />
                  <span>تحديث البيانات الآن</span>
                </button>
              </div>
            </Transition>
          </div>

          <!-- Open Fast POS Button -->
          <router-link
            to="/pos"
            class="px-5 py-2.5 bg-theme-gradient text-white shadow-theme-primary rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-theme-primary active:scale-95 cursor-pointer"
          >
            <Zap class="w-4 h-4 fill-white text-white" />
            <span>{{ $t('invoices.pos_fast_badge') }}</span>
          </router-link>
        </div>
      </template>
    </PageHeader>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 2. FINANCIAL METRICS SUMMARY CARDS                          -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Sales -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('common.total_sales') }}</span>
          <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-500">
            <TrendingUp class="w-4 h-4" />
          </div>
        </div>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
          {{ formatMoney(summary.total_sales || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
        </div>
        <span class="text-[10px] text-slate-500 block">{{ $t('invoices.confirmed_sales_sub') }}</span>
      </div>

      <!-- Total Paid -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('invoices.collected_cash_electronic') }}</span>
          <div class="p-2 rounded-xl bg-cyan-500/10 text-cyan-500">
            <CheckCircle2 class="w-4 h-4" />
          </div>
        </div>
        <div class="text-2xl font-black text-cyan-600 dark:text-cyan-400 font-mono">
          {{ formatMoney(summary.total_paid || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
        </div>
        <span class="text-[10px] text-slate-500 block">{{ $t('invoices.inflows_in_drawer_sub') }}</span>
      </div>

      <!-- Total Due -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('invoices.remaining_credit_due') }}</span>
          <div class="p-2 rounded-xl bg-rose-500/10 text-rose-500">
            <Clock class="w-4 h-4" />
          </div>
        </div>
        <div class="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono">
          {{ formatMoney(summary.total_due || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
        </div>
        <span class="text-[10px] text-slate-500 block">{{ $t('invoices.debt_under_collection_sub') }}</span>
      </div>

      <!-- Invoices Count -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('invoices.invoices_count_label') }}</span>
          <div class="p-2 rounded-xl bg-theme-primary/10 text-theme-primary">
            <FileText class="w-4 h-4" />
          </div>
        </div>
        <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
          {{ summary.total_count || 0 }} <span class="text-xs text-slate-400">{{ $t('invoices.invoice_unit') }}</span>
        </div>
        <span class="text-[10px] text-slate-500 block">{{ $t('invoices.sales_log_sub') }}</span>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 3. MAIN WORKSPACE: DATA TABLE & COLLAPSIBLE SIDEBAR FILTER   -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="flex flex-col lg:flex-row gap-5 items-start">
      
      <!-- MAIN TABLE COLUMN (Takes Full Width or 75% when Filter is Open) -->
      <div class="flex-1 w-full space-y-4 min-w-0">
        
        <!-- Search & Date Presets Quick Bar -->
        <div class="p-3.5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
          <!-- Search Input -->
          <div class="relative flex-1 w-full">
            <Search class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="$t('invoices.search_invoices_placeholder')"
              @input="debounceSearch"
              class="w-full pr-10 pl-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-theme-primary/30"
            />
          </div>

          <!-- Date Quick Presets Pills -->
          <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
            <button
              type="button"
              v-for="preset in datePresets"
              :key="preset.id"
              @click="applyDatePreset(preset.id)"
              class="px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer"
              :class="activeDatePreset === preset.id
                ? 'bg-theme-primary text-white shadow-xs'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
            >
              {{ preset.label }}
            </button>
          </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- FLOATING BULK ACTIONS TOOLBAR                             -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <Transition name="fade">
          <div
            v-if="selectedInvoiceIds.length > 0"
            class="p-3 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white rounded-2xl border border-emerald-500/40 shadow-2xl flex flex-wrap items-center justify-between gap-3 animate-pulse-subtle"
          >
            <div class="flex items-center gap-2.5">
              <span class="w-7 h-7 rounded-lg bg-emerald-500 text-slate-950 font-black text-xs flex items-center justify-center">
                {{ selectedInvoiceIds.length }}
              </span>
              <span class="text-xs font-bold text-slate-200">
                فواتير محددة للإجراء المجمع
              </span>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
              <!-- Bulk Print Receipts -->
              <button
                type="button"
                @click="bulkPrintReceipts"
                class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95"
              >
                <Printer class="w-3.5 h-3.5" />
                <span>طباعة الإيصالات</span>
              </button>

              <!-- Bulk Export to Excel -->
              <button
                type="button"
                @click="bulkExportSelected"
                class="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95"
              >
                <Download class="w-3.5 h-3.5" />
                <span>تصدير المحددة</span>
              </button>

              <!-- Bulk Cancel Selected -->
              <button
                type="button"
                @click="bulkCancelSelected"
                class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95"
              >
                <Ban class="w-3.5 h-3.5" />
                <span>إلغاء المحددة</span>
              </button>

              <!-- Deselect All -->
              <button
                type="button"
                @click="selectedInvoiceIds = []"
                class="px-2.5 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer"
              >
                إلغاء التحديد
              </button>
            </div>
          </div>
        </Transition>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- INVOICES DATA TABLE                                       -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
          
          <!-- Loading Spinner -->
          <div v-if="isLoading" class="p-16 text-center">
            <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
          </div>

          <!-- Invoices Table View -->
          <div v-else-if="invoices.length > 0" class="overflow-x-auto">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-100/90 dark:bg-slate-950/80 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 select-none">
                  <!-- Bulk Checkbox -->
                  <th class="py-3 px-3.5 text-center w-10">
                    <input
                      type="checkbox"
                      :checked="isAllSelected"
                      @change="toggleSelectAll"
                      class="w-4 h-4 text-theme-primary rounded border-slate-300 dark:border-slate-700 focus:ring-theme-primary cursor-pointer"
                    />
                  </th>
                  <th class="py-3 px-3 text-start font-bold">#</th>
                  <th class="py-3 px-3 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
                  <th class="py-3 px-3 text-start font-bold">{{ $t('invoices.customer') }}</th>
                  <th class="py-3 px-3 text-start font-bold">{{ $t('common.date') }}</th>
                  <th class="py-3 px-3 text-center font-bold">{{ $t('invoices.payment_method') }}</th>
                  <th class="py-3 px-3 text-end font-bold">{{ $t('common.total') }}</th>
                  <th class="py-3 px-3 text-end font-bold">{{ $t('invoices.paid') }}</th>
                  <th class="py-3 px-3 text-end font-bold">{{ $t('invoices.remaining') }}</th>
                  <th class="py-3 px-3 text-center font-bold">{{ $t('common.status') }}</th>
                  <th class="py-3 px-3 text-center font-bold">الإجراءات</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                <tr
                  v-for="(inv, idx) in invoices"
                  :key="inv.id"
                  class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
                  :class="[
                    inv.is_cancelled ? 'opacity-60 bg-rose-500/5 line-through' : '',
                    selectedInvoiceIds.includes(inv.id) ? 'bg-theme-primary/5 dark:bg-theme-primary/10' : ''
                  ]"
                >
                  <!-- Checkbox -->
                  <td class="py-3.5 px-3.5 text-center">
                    <input
                      type="checkbox"
                      :value="inv.id"
                      v-model="selectedInvoiceIds"
                      class="w-4 h-4 text-theme-primary rounded border-slate-300 dark:border-slate-700 focus:ring-theme-primary cursor-pointer"
                    />
                  </td>

                  <!-- Index -->
                  <td class="py-3.5 px-3 font-mono text-slate-500">
                    {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
                  </td>

                  <!-- Invoice Number -->
                  <td class="py-3.5 px-3 font-mono font-black text-theme-primary">
                    {{ inv.invoice_number }}
                  </td>

                  <!-- Customer Details -->
                  <td class="py-3.5 px-3">
                    <div class="font-bold text-slate-900 dark:text-white font-tajawal">{{ inv.customer_name }}</div>
                    <div v-if="inv.customer_phone" class="text-[10px] text-slate-400 font-mono mt-0.5">
                      {{ inv.customer_phone }}
                    </div>
                  </td>

                  <!-- Date & Time -->
                  <td class="py-3.5 px-3 font-mono text-slate-600 dark:text-slate-300">
                    {{ inv.invoice_date }} <span class="text-[10px] text-slate-400">({{ inv.created_at }})</span>
                  </td>

                  <!-- Payment Method -->
                  <td class="py-3.5 px-3 text-center">
                    <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold font-tajawal bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                      {{ formatPaymentType(inv.payment_type) }}
                    </span>
                  </td>

                  <!-- Net Total -->
                  <td class="py-3.5 px-3 text-end font-mono font-black text-slate-900 dark:text-white text-sm">
                    {{ formatMoney(inv.net_total) }} {{ $t('common.currency') }}
                  </td>

                  <!-- Paid Amount -->
                  <td class="py-3.5 px-3 text-end font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    {{ formatMoney(inv.paid_amount) }} {{ $t('common.currency') }}
                  </td>

                  <!-- Remaining / Credit Due -->
                  <td class="py-3.5 px-3 text-end font-mono font-bold" :class="inv.remaining_amount > 0 ? 'text-rose-500' : 'text-slate-400'">
                    {{ formatMoney(inv.remaining_amount) }} {{ $t('common.currency') }}
                  </td>

                  <!-- Status Badge -->
                  <td class="py-3.5 px-3 text-center font-tajawal">
                    <span
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-block"
                      :class="!inv.is_cancelled ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
                    >
                      {{ !inv.is_cancelled ? $t('invoices.confirmed_badge') : $t('invoices.cancelled_badge') }}
                    </span>
                  </td>

                  <!-- PER-ROW ACTION BUTTONS -->
                  <td class="py-3.5 px-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                      <!-- Quick View Modal Button -->
                      <button
                        type="button"
                        @click="openDetailsModal(inv)"
                        class="p-1.5 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 dark:hover:bg-cyan-950/40 rounded-xl transition cursor-pointer"
                        title="معاينة تفاصيل الفاتورة"
                      >
                        <Eye class="w-4 h-4" />
                      </button>

                      <!-- Quick 80mm Print Button -->
                      <button
                        type="button"
                        @click="openPrintReceipt(inv.id)"
                        class="p-1.5 text-slate-400 hover:text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded-xl transition cursor-pointer"
                        title="طباعة إيصال كاشير 80mm"
                      >
                        <Printer class="w-4 h-4" />
                      </button>

                      <!-- Cancel & Reverse Button -->
                      <button
                        v-if="!inv.is_cancelled"
                        type="button"
                        @click="cancelInvoice(inv)"
                        class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition cursor-pointer"
                        :title="$t('invoices.cancel_and_reverse_hint')"
                      >
                        <Ban class="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Empty State -->
          <EmptyState
            v-else
            :title="$t('invoices.no_invoices_found')"
            :description="$t('invoices.no_invoices_description')"
            :icon="'🛒'"
          >
            <template #action>
              <button
                type="button"
                @click="resetAllFilters"
                class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black shadow-md cursor-pointer"
              >
                إعادة ضبط فلاتر البحث
              </button>
            </template>
          </EmptyState>

          <!-- Pagination Bar -->
          <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between flex-wrap gap-2">
            <div class="text-xs text-slate-500 dark:text-slate-400">
              {{ $t('invoices.total_results_invoices', { count: pagination.total }) }}
            </div>
            <div class="flex items-center gap-1">
              <button
                type="button"
                @click="fetchInvoices(pagination.current_page - 1)"
                :disabled="pagination.current_page <= 1"
                class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer"
              >
                {{ $t('common.previous') }}
              </button>
              <span class="px-3 py-1.5 text-xs font-mono text-slate-700 dark:text-slate-300 font-bold">
                {{ pagination.current_page }} / {{ pagination.last_page }}
              </span>
              <button
                type="button"
                @click="fetchInvoices(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.last_page"
                class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer"
              >
                {{ $t('common.next') }}
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- ═════════════════════════════════════════════════════════ -->
      <!-- 4. DEDICATED SIDEBAR FILTER PANEL                         -->
      <!-- ═════════════════════════════════════════════════════════ -->
      <Transition name="slide-fade">
        <aside
          v-if="isFilterSidebarOpen"
          class="w-full lg:w-80 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xl space-y-5 shrink-0"
        >
          <!-- Sidebar Header -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <SlidersHorizontal class="w-4 h-4 text-theme-primary" />
              <h3 class="font-black text-sm text-slate-900 dark:text-white">تصفية الفواتير المتقدمة</h3>
            </div>
            <button
              type="button"
              @click="isFilterSidebarOpen = false"
              class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
            >
              ✕
            </button>
          </div>

          <!-- Filter Section: Branch / Store Filter -->
          <div class="space-y-1.5">
            <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block flex items-center gap-1.5">
              <Store class="w-3.5 h-3.5 text-theme-primary" />
              <span>الفرع / المخزن</span>
            </label>
            <BaseSelect
              v-model="selectedStoreId"
              :options="storeOptions"
              :searchable="false"
              @change="fetchInvoices(1)"
            />
          </div>

          <!-- Filter Section: Payment Type -->
          <div class="space-y-1.5">
            <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block flex items-center gap-1.5">
              <CreditCard class="w-3.5 h-3.5 text-cyan-500" />
              <span>نوع السداد المالي</span>
            </label>
            <BaseSelect
              v-model="selectedPaymentType"
              :options="paymentTypeOptions"
              :searchable="false"
              @change="fetchInvoices(1)"
            />
          </div>

          <!-- Filter Section: Invoice Status -->
          <div class="space-y-1.5">
            <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block flex items-center gap-1.5">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500" />
              <span>حالة الفاتورة</span>
            </label>
            <BaseSelect
              v-model="selectedStatus"
              :options="statusOptions"
              :searchable="false"
              @change="fetchInvoices(1)"
            />
          </div>

          <!-- Filter Section: Custom Date Range -->
          <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <label class="text-xs font-bold text-slate-600 dark:text-slate-400 block flex items-center gap-1.5">
              <Calendar class="w-3.5 h-3.5 text-amber-500" />
              <span>نطاق التاريخ المخصص</span>
            </label>
            <div class="space-y-1.5">
              <div class="flex items-center gap-1.5">
                <span class="text-[11px] text-slate-400 w-8">من:</span>
                <input
                  v-model="dateFrom"
                  type="date"
                  @change="activeDatePreset = 'custom'; fetchInvoices(1)"
                  class="flex-1 px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-white"
                />
              </div>
              <div class="flex items-center gap-1.5">
                <span class="text-[11px] text-slate-400 w-8">إلى:</span>
                <input
                  v-model="dateTo"
                  type="date"
                  @change="activeDatePreset = 'custom'; fetchInvoices(1)"
                  class="flex-1 px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-white"
                />
              </div>
            </div>
          </div>

          <!-- Sidebar Action Buttons -->
          <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
            <button
              type="button"
              @click="fetchInvoices(1)"
              class="flex-1 py-2.5 bg-theme-primary hover:bg-theme-primary-hover text-white rounded-xl text-xs font-black transition cursor-pointer shadow-md text-center active:scale-95"
            >
              تطبيق الفلاتر
            </button>
            <button
              type="button"
              @click="resetAllFilters"
              class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer text-center"
            >
              إعادة ضبط
            </button>
          </div>
        </aside>
      </Transition>

    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 5. INVOICE DETAILS & WHATSAPP MODAL                         -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <AppModal
      :show="showDetailsModal"
      :title="$t('invoices.sales_invoice_title', { number: selectedInvoiceDetails?.invoice_number || '' })"
      @close="showDetailsModal = false"
    >
      <div v-if="selectedInvoiceDetails" class="space-y-4 font-tajawal text-xs">
        
        <!-- Top Info Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl">
          <div>
            <span class="text-slate-400 block font-bold">{{ $t('invoices.customer') }}:</span>
            <span class="text-slate-900 dark:text-white font-bold">{{ selectedInvoiceDetails.customer_name }}</span>
          </div>
          <div>
            <span class="text-slate-400 block font-bold">{{ $t('common.date') }}:</span>
            <span class="text-slate-900 dark:text-slate-200 font-mono">{{ selectedInvoiceDetails.invoice_date }}</span>
          </div>
          <div>
            <span class="text-slate-400 block font-bold">{{ $t('invoices.branch_cashier') }}</span>
            <span class="text-slate-900 dark:text-slate-200">{{ selectedInvoiceDetails.store_name }} ({{ selectedInvoiceDetails.cashier_name }})</span>
          </div>
          <div>
            <span class="text-slate-400 block font-bold">{{ $t('invoices.payment_method') }}:</span>
            <span class="font-bold text-theme-primary">{{ formatPaymentType(selectedInvoiceDetails.payment_type) }}</span>
          </div>
        </div>

        <!-- Items Table -->
        <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-100 dark:bg-slate-950 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <th class="p-2.5 text-start font-bold">{{ $t('invoices.item') }}</th>
                <th class="p-2.5 text-end font-bold">{{ $t('invoices.quantity') }}</th>
                <th class="p-2.5 text-end font-bold">{{ $t('invoices.sale_price') }}</th>
                <th class="p-2.5 text-end font-bold">{{ $t('common.total') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-sans">
              <tr v-for="item in selectedInvoiceDetails.items" :key="item.id">
                <td class="p-2.5 font-bold font-tajawal text-slate-900 dark:text-white">{{ item.item_name || item.name }}</td>
                <td class="p-2.5 text-end font-mono text-slate-700 dark:text-slate-300">{{ formatMoney(item.quantity) }} {{ item.unit }}</td>
                <td class="p-2.5 text-end font-mono text-slate-700 dark:text-slate-300">{{ formatMoney(item.unit_price) }}</td>
                <td class="p-2.5 text-end font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(item.total_price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Financial Breakdown -->
        <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1.5 font-mono">
          <div class="flex justify-between text-slate-600 dark:text-slate-400 font-tajawal">
            <span>{{ $t('invoices.subtotal') }}:</span>
            <span class="font-bold font-mono">{{ formatMoney(selectedInvoiceDetails.subtotal) }} {{ $t('common.currency') }}</span>
          </div>
          <div v-if="selectedInvoiceDetails.discount_amount > 0" class="flex justify-between text-rose-500 font-tajawal">
            <span>{{ $t('invoices.discount') }}:</span>
            <span class="font-bold font-mono">- {{ formatMoney(selectedInvoiceDetails.discount_amount) }} {{ $t('common.currency') }}</span>
          </div>
          <div class="flex justify-between text-sm font-black text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-tajawal">
            <span>{{ $t('invoices.net_total') }}:</span>
            <span class="text-emerald-500 text-base font-mono">{{ formatMoney(selectedInvoiceDetails.net_total) }} {{ $t('common.currency') }}</span>
          </div>
        </div>

        <!-- Modal Action Footer Buttons -->
        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800 flex-wrap">
          <!-- WhatsApp Share Button -->
          <a
            v-if="whatsAppData?.url"
            :href="whatsAppData.url"
            target="_blank"
            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 transition"
          >
            <Share2 class="w-4 h-4" />
            <span>{{ $t('invoices.share_whatsapp_btn') }}</span>
          </a>

          <!-- Print Thermal Receipt Button -->
          <button
            type="button"
            @click="openPrintReceipt(selectedInvoiceDetails.id)"
            class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 transition cursor-pointer"
          >
            <Printer class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('invoices.print_receipt_btn') }}</span>
          </button>
        </div>

      </div>
    </AppModal>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../services/api';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseSelect from '../../Components/Form/BaseSelect.vue';
import AppModal from '../../Components/Common/AppModal.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    Zap,
    Search,
    TrendingUp,
    CheckCircle2,
    Clock,
    FileText,
    Eye,
    Ban,
    Share2,
    Printer,
    SlidersHorizontal,
    FileSpreadsheet,
    Download,
    RefreshCw,
    ChevronDown,
    Calendar,
    Store,
    CreditCard
} from 'lucide-vue-next';

// State
const invoices = ref([]);
const isFilterSidebarOpen = ref(false);
const isGlobalActionOpen = ref(false);
const selectedInvoiceIds = ref([]);
const activeDatePreset = ref('all');

const summary = ref({
    total_sales: 0,
    total_paid: 0,
    total_due: 0,
    total_count: 0,
});

const searchQuery = ref('');
const selectedStoreId = ref('all');
const selectedPaymentType = ref('all');
const selectedStatus = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const isLoading = ref(false);

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

const showDetailsModal = ref(false);
const selectedInvoiceDetails = ref(null);
const whatsAppData = ref(null);
let debounceTimer = null;

// Filter Options
const paymentTypeOptions = computed(() => [
  { value: 'all', label: trans('invoices.all_payment_types') || 'كافة أنواع السداد' },
  { value: 'cash', label: trans('invoices.payment_cash_option') || 'نقدي (كاش)' },
  { value: 'credit', label: trans('invoices.payment_credit_option') || 'آجل (مديونية)' },
  { value: 'partial', label: trans('invoices.payment_partial_option') || 'سداد جزئي' }
]);

const statusOptions = computed(() => [
  { value: 'all', label: trans('invoices.status_all') || 'كافة الحالات' },
  { value: 'confirmed', label: trans('invoices.status_confirmed_option') || 'معتمدة ومسجلة' },
  { value: 'cancelled', label: trans('invoices.status_cancelled_option') || 'ملغاة ومعكوسة' }
]);

const storeOptions = computed(() => [
  { value: 'all', label: 'كافة الفروع والمخازن' },
  { value: '1', label: 'الفرع الرئيسي' }
]);

const datePresets = [
  { id: 'all', label: 'الكل' },
  { id: 'today', label: 'اليوم' },
  { id: 'yesterday', label: 'أمس' },
  { id: 'week', label: 'آخر 7 أيام' },
  { id: 'month', label: 'هذا الشهر' }
];

// Computed
const activeFiltersCount = computed(() => {
    let count = 0;
    if (selectedPaymentType.value !== 'all') count++;
    if (selectedStatus.value !== 'all') count++;
    if (selectedStoreId.value !== 'all') count++;
    if (dateFrom.value || dateTo.value) count++;
    return count;
});

const isAllSelected = computed(() => {
    return invoices.value.length > 0 && selectedInvoiceIds.value.length === invoices.value.length;
});

// Methods
const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatPaymentType = (type) => {
    const map = {
        cash: `💵 ${trans('invoices.cash') || 'نقدي'}`,
        credit: `📝 ${trans('invoices.credit') || 'آجل'}`,
        partial: `⚖️ ${trans('invoices.partial') || 'جزئي'}`,
        bank_transfer: `⚡ تحويل إلكتروني`,
    };
    return map[type] || type;
};

const fetchInvoices = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get('/invoices', {
            params: {
                search: searchQuery.value || undefined,
                store_id: selectedStoreId.value !== 'all' ? selectedStoreId.value : undefined,
                payment_type: selectedPaymentType.value !== 'all' ? selectedPaymentType.value : undefined,
                status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
                from_date: dateFrom.value || undefined,
                to_date: dateTo.value || undefined,
                page: page,
                per_page: 15,
            },
        });
        invoices.value = response.data?.data || [];
        summary.value = response.data?.summary || {
            total_sales: 0,
            total_paid: 0,
            total_due: 0,
            total_count: 0,
        };
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 15,
            total: invoices.value.length,
        };
    } catch (error) {
        console.error('Failed to load invoices:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchInvoices(1);
    }, 300);
};

const applyDatePreset = (presetId) => {
    activeDatePreset.value = presetId;
    const now = new Date();
    
    if (presetId === 'all') {
        dateFrom.value = '';
        dateTo.value = '';
    } else if (presetId === 'today') {
        const todayStr = now.toISOString().split('T')[0];
        dateFrom.value = todayStr;
        dateTo.value = todayStr;
    } else if (presetId === 'yesterday') {
        const y = new Date();
        y.setDate(y.getDate() - 1);
        const yStr = y.toISOString().split('T')[0];
        dateFrom.value = yStr;
        dateTo.value = yStr;
    } else if (presetId === 'week') {
        const w = new Date();
        w.setDate(w.getDate() - 7);
        dateFrom.value = w.toISOString().split('T')[0];
        dateTo.value = now.toISOString().split('T')[0];
    } else if (presetId === 'month') {
        const mStart = new Date(now.getFullYear(), now.getMonth(), 1);
        dateFrom.value = mStart.toISOString().split('T')[0];
        dateTo.value = now.toISOString().split('T')[0];
    }
    
    fetchInvoices(1);
};

const resetAllFilters = () => {
    searchQuery.value = '';
    selectedStoreId.value = 'all';
    selectedPaymentType.value = 'all';
    selectedStatus.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    activeDatePreset.value = 'all';
    fetchInvoices(1);
};

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedInvoiceIds.value = [];
    } else {
        selectedInvoiceIds.value = invoices.value.map(inv => inv.id);
    }
};

const openDetailsModal = async (inv) => {
    try {
        const response = await api.get(`/invoices/${inv.id}`);
        selectedInvoiceDetails.value = response.data?.data;
        whatsAppData.value = response.data?.whatsapp;
        showDetailsModal.value = true;
    } catch (error) {
        console.error('Failed to load invoice details:', error);
    }
};

const openPrintReceipt = (id) => {
    if (!id) return;
    window.open(`/invoices/${id}/print`, '_blank', 'width=800,height=600');
};

const cancelInvoice = async (inv) => {
    const result = await Swal.fire({
        title: trans('invoices.cancel_invoice_confirm_title', { number: inv.invoice_number }) || `هل أنت متأكد من إلغاء الفاتورة ${inv.invoice_number}؟`,
        text: trans('invoices.cancel_invoice_confirm_text') || 'سيتم إرجاع كافة البضائع إلى رصيد المخزن وعكس أثرها المالي فوراً.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: trans('invoices.cancel_confirmed_btn') || 'نعم، قم بالإلغاء',
        cancelButtonText: trans('invoices.cancel_dismiss_btn') || 'تراجع',
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        try {
            await api.post(`/invoices/${inv.id}/cancel`, { reason: trans('invoices.cancel_reason_default') || 'إلغاء من لوحة المبيعات' });
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('invoices.invoice_cancelled_success') || 'تم إلغاء الفاتورة بنجاح',
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchInvoices(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: trans('common.error'),
                text: error.userMessage || trans('invoices.invoice_cancelled_failed') || 'فشل إلغاء الفاتورة',
            });
        }
    }
};

// Bulk Actions
const bulkPrintReceipts = () => {
    selectedInvoiceIds.value.forEach((id) => {
        window.open(`/invoices/${id}/print`, '_blank');
    });
};

const bulkExportSelected = () => {
    const selectedInvoices = invoices.value.filter(inv => selectedInvoiceIds.value.includes(inv.id));
    let csv = "رقم الفاتورة,العميل,الهاتف,التاريخ,طريقة الدفع,الصافي,المدفوع,المتبقي,الحالة\n";
    selectedInvoices.forEach(inv => {
        csv += `"${inv.invoice_number}","${inv.customer_name || ''}","${inv.customer_phone || ''}","${inv.invoice_date}","${inv.payment_type}","${inv.net_total}","${inv.paid_amount}","${inv.remaining_amount}","${inv.status}"\n`;
    });
    
    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `invoices_export_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
};

const bulkCancelSelected = async () => {
    const count = selectedInvoiceIds.value.length;
    const result = await Swal.fire({
        title: `إلغاء ${count} فواتير محددة؟`,
        text: 'سيتم إلغاء كافة الفواتير المحددة وإرجاع بضائعها إلى رصيد المخزن وعكس القيود المالية.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، قم بالإلغاء المجمع',
        cancelButtonText: 'تراجع',
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        let successCount = 0;
        for (const id of selectedInvoiceIds.value) {
            try {
                await api.post(`/invoices/${id}/cancel`, { reason: 'إلغاء مجمع من لوحة المبيعات' });
                successCount++;
            } catch (e) {
                console.error(`Failed to cancel invoice ${id}:`, e);
            }
        }
        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: `تم إلغاء ${successCount} فواتير بنجاح وعكس رصيد المخزون.`,
        });
        selectedInvoiceIds.value = [];
        await fetchInvoices(pagination.value.current_page);
    }
};

const exportToExcel = () => {
    let csv = "رقم الفاتورة,العميل,الهاتف,التاريخ,طريقة الدفع,الصافي,المدفوع,المتبقي,الحالة\n";
    invoices.value.forEach(inv => {
        csv += `"${inv.invoice_number}","${inv.customer_name || ''}","${inv.customer_phone || ''}","${inv.invoice_date}","${inv.payment_type}","${inv.net_total}","${inv.paid_amount}","${inv.remaining_amount}","${inv.status}"\n`;
    });
    
    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `all_invoices_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
};

const printFilteredInvoicesReport = () => {
    window.print();
};

onMounted(() => {
    fetchInvoices(1);
});
</script>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.25s ease-out;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateX(20px);
  opacity: 0;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>