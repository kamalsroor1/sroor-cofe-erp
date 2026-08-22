<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal transition-colors duration-300">
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🌟 TOP WELCOME HEADER BANNER                                -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
      <div>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-theme-primary/10 text-theme-primary font-black text-sm">
            ☕
          </span>
          <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
            مرحباً بك في لوحة تحكم {{ appConfigStore.companyName || 'سرور كوفي' }}
          </h1>
        </div>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-bold mt-1">
          نظرة عامة على المبيعات، مؤشرات الذروة، رصيد الخزينة، والمخزون الحي
        </p>
      </div>

      <!-- Quick Action Buttons in Banner -->
      <div class="flex flex-wrap items-center gap-3 shrink-0">
        <router-link
          to="/pos"
          class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-black text-xs rounded-2xl shadow-lg shadow-emerald-500/20 flex items-center gap-2 transition-all active:scale-95 cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>نقطة البيع السريعة (POS)</span>
        </router-link>

        <router-link
          to="/purchases"
          class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-2xl shadow-xs flex items-center gap-2 transition-all active:scale-95 cursor-pointer"
        >
          <ShoppingCart class="w-4 h-4 text-slate-500 dark:text-slate-400" />
          <span>فاتورة توريد / مشتريات</span>
        </router-link>

        <button
          type="button"
          @click="fetchDashboard"
          :disabled="isLoading"
          class="p-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-2xl shadow-xs transition-all active:scale-95 cursor-pointer"
          title="تحديث البيانات الحية"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
        </button>
      </div>
    </div>

    <!-- Loading Spinner -->
    <div v-if="isLoading && !dashboardData" class="p-20 text-center">
      <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <p class="text-xs text-slate-400 font-bold">جاري تحميل المؤشرات والتحليلات الحية...</p>
    </div>

    <div v-else class="space-y-6">
      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- 📊 4 KEY KPI METRIC CARDS                                    -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. مبيعات اليوم (Green Theme) -->
        <div class="p-5 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-2.5 relative overflow-hidden group">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مبيعات اليوم</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-black text-sm">
              💵
            </div>
          </div>
          <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
            {{ formatMoney(metrics.today_sales || 0) }} <span class="text-xs font-sans text-slate-400 font-bold">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold flex items-center justify-between">
            <span>{{ metrics.today_invoices_count || 0 }} فاتورة معتمدة</span>
            <span class="text-emerald-500 font-mono">نقد: {{ formatMoney(metrics.cash_sales || 0) }}</span>
          </div>
        </div>

        <!-- 2. مجمل أرباح الشهر (Cyan/Teal Theme) -->
        <div class="p-5 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-2.5 relative overflow-hidden group">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مجمل أرباح الشهر</span>
            <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-500 dark:text-cyan-400 flex items-center justify-center">
              <TrendingUp class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-cyan-600 dark:text-cyan-400 font-mono">
            {{ formatMoney(metrics.monthly_gross_profit || 0) }} <span class="text-xs font-sans text-slate-400 font-bold">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold flex items-center justify-between">
            <span>هامش الربحية:</span>
            <span class="font-mono text-emerald-500 font-black">%{{ metrics.monthly_margin || '0.00' }}</span>
          </div>
        </div>

        <!-- 3. إجمالي ديون العملاء (Amber/Gold Theme) -->
        <div class="p-5 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-2.5 relative overflow-hidden group">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مستحقات العملاء (الآجل)</span>
            <div class="w-8 h-8 rounded-xl bg-theme-light text-theme-primary flex items-center justify-center">
              <CreditCard class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-theme-primary text-theme-primary font-mono">
            {{ formatMoney(metrics.customers_debt || 0) }} <span class="text-xs font-sans text-slate-400 font-bold">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold flex items-center justify-between">
            <span>العملاء النشطون:</span>
            <span class="font-mono font-bold text-slate-700 dark:text-slate-300">{{ metrics.customers_count || 0 }} عميل</span>
          </div>
        </div>

        <!-- 4. مبيعات الشهر الحالي (Indigo/Purple Theme) -->
        <div class="p-5 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-2.5 relative overflow-hidden group">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مبيعات الشهر الحالي</span>
            <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-500 dark:text-indigo-400 flex items-center justify-center">
              <BarChart3 class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono">
            {{ formatMoney(metrics.monthly_sales || 0) }} <span class="text-xs font-sans text-slate-400 font-bold">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold flex items-center justify-between">
            <span>صافي حركة اليوم:</span>
            <span class="font-mono font-bold text-emerald-500">{{ formatMoney(metrics.net_cash_today || 0) }} ج.م</span>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- 📈 EXECUTIVE ANALYTICS SECTION: 7-Day Trend + Payment Dist  -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- 📊 1. حركة ومبيعات آخر 7 أيام (Last 7 Days Sales Trend) - 7 Cols -->
        <div class="lg:col-span-7 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4">
          <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                <BarChart3 class="w-4 h-4" />
              </div>
              <div>
                <h2 class="text-sm font-black text-slate-900 dark:text-white">📊 حركة ومبيعات آخر 7 أيام</h2>
                <p class="text-[10px] text-slate-400 font-bold">مقارنة وتدفق المبيعات اليومية وعدد الفواتير</p>
              </div>
            </div>

            <div class="flex items-center gap-2 text-xs font-mono font-bold text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800">
              <span>إجمالي الأسبوع:</span>
              <span class="text-emerald-500 font-black">{{ formatMoney(periodAnalytics.sales || 0) }} ج.م</span>
            </div>
          </div>

          <!-- Interactive Visual Bar Chart -->
          <div class="pt-4 pb-2">
            <div class="h-48 flex items-end gap-2 sm:gap-4 justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
              <div
                v-for="day in dailyTrend"
                :key="day.date"
                class="flex-1 flex flex-col items-center gap-2 h-full justify-end group cursor-pointer relative"
              >
                <!-- Tooltip on Hover -->
                <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute -top-12 z-20 bg-slate-900 text-white text-[10px] font-mono py-1 px-2.5 rounded-xl shadow-xl pointer-events-none whitespace-nowrap border border-slate-700">
                  <div class="font-bold">{{ day.sales_formatted }}</div>
                  <div class="text-slate-400 font-sans">{{ day.invoices }} فاتورة</div>
                </div>

                <!-- Animated Bar -->
                <div class="w-full max-w-[42px] bg-slate-100 dark:bg-slate-800/80 rounded-xl relative overflow-hidden flex flex-col justify-end transition-all duration-300 h-full">
                  <div
                    class="w-full rounded-xl transition-all duration-500 relative group-hover:brightness-110"
                    :style="{
                      height: `${computeBarHeight(day.sales, maxDailySale)}%`,
                      backgroundColor: isToday(day.date) ? 'var(--color-primary, #10b981)' : '#0ea5e9'
                    }"
                  >
                    <!-- Highlight pulse for today -->
                    <div v-if="isToday(day.date)" class="absolute inset-0 bg-white/20 animate-pulse rounded-xl"></div>
                  </div>
                </div>

                <!-- Day Label -->
                <div class="text-[10px] font-bold text-center truncate w-full text-slate-500 dark:text-slate-400 font-tajawal group-hover:text-theme-primary transition-colors">
                  {{ day.label }}
                </div>
              </div>
            </div>

            <!-- Chart Footer Summary -->
            <div class="flex items-center justify-between pt-3 text-[11px] text-slate-500 dark:text-slate-400 font-bold">
              <div class="flex items-center gap-4">
                <span class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-md bg-theme-primary"></span>
                  اليوم الحالي
                </span>
                <span class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-md bg-sky-500"></span>
                  الأيام السابقة
                </span>
              </div>
              <div class="font-mono text-slate-600 dark:text-slate-300">
                متوسط السلة: <span class="font-black text-emerald-500">{{ formatMoney(periodAnalytics.basket_size || 0) }} ج.م</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 💳 2. توزيع طرق التحصيل والدفع (Payment Distribution) - 5 Cols -->
        <div class="lg:col-span-5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-xl bg-theme-light text-theme-primary flex items-center justify-center">
                  <CreditCard class="w-4 h-4" />
                </div>
                <div>
                  <h2 class="text-sm font-black text-slate-900 dark:text-white">💳 توزيع طرق التحصيل والدفع</h2>
                  <p class="text-[10px] text-slate-400 font-bold">نسب التحصيل الفعلي حسب القناة المالية</p>
                </div>
              </div>
            </div>

            <!-- Payment Methods List -->
            <div class="space-y-3.5 pt-4">
              <div
                v-for="method in paymentDistribution"
                :key="method.key"
                class="space-y-1.5"
              >
                <div class="flex items-center justify-between text-xs font-bold">
                  <div class="flex items-center gap-2">
                    <span class="text-sm">{{ getPaymentMethodIcon(method.key) }}</span>
                    <span class="text-slate-800 dark:text-slate-200">{{ method.label }}</span>
                  </div>
                  <div class="flex items-center gap-2 font-mono">
                    <span class="text-slate-900 dark:text-white font-black">{{ formatMoney(method.amount) }} ج.م</span>
                    <span class="text-[10px] text-slate-400 font-bold">({{ method.percentage }}%)</span>
                  </div>
                </div>

                <!-- Progress Bar -->
                <div class="w-full h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="getPaymentMethodColor(method.key)"
                    :style="{ width: `${Math.max(method.percentage, 2)}%` }"
                  ></div>
                </div>
              </div>

              <div v-if="paymentDistribution.length === 0" class="py-8 text-center text-xs text-slate-400 font-bold">
                لا توجد مدفوعات مسجلة خلال الفترة
              </div>
            </div>
          </div>

          <!-- Active Cash Shift Summary Box -->
          <div v-if="activeShift" class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs mt-2">
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
              <span class="font-bold text-slate-700 dark:text-slate-300">الوردية المفتوحة (#{{ activeShift.shift_number }}):</span>
            </div>
            <div class="font-mono font-black text-emerald-500">
              {{ formatMoney(activeShift.current_cash || activeShift.starting_cash) }} ج.م
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- ⚡ 3. توزيع ساعات الذروة (24 ساعة) (Peak Sales Rush Heatmap) -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center">
              <Zap class="w-4 h-4" />
            </div>
            <div>
              <h2 class="text-sm font-black text-slate-900 dark:text-white">⚡ توزيع ساعات الذروة (24 ساعة)</h2>
              <p class="text-[10px] text-slate-400 font-bold">كثافة حركة المبيعات والإقبال على مدار اليوم لتنظيم ورديات الكاشير والتحضير</p>
            </div>
          </div>

          <!-- Peak Hour Badge -->
          <div v-if="peakHour && parseFloat(peakHour.sales) > 0" class="flex items-center gap-2 px-3.5 py-1.5 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-600 dark:text-purple-400 text-xs font-bold font-tajawal">
            <span>🔥 ساعة الذروة القصوى:</span>
            <span class="font-black font-mono">({{ peakHour.label }})</span>
            <span class="font-mono text-emerald-500 font-bold">[{{ formatMoney(peakHour.sales) }} ج.م]</span>
          </div>
        </div>

        <!-- 24-Hour Interactive Timeline Heatmap Bars -->
        <div class="pt-3 pb-1 overflow-x-auto">
          <div class="min-w-[700px]">
            <div class="h-28 flex items-end gap-1.5 justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
              <div
                v-for="slot in hourlySales"
                :key="slot.hour"
                class="flex-1 flex flex-col items-center gap-1.5 h-full justify-end group cursor-pointer relative"
              >
                <!-- Tooltip on Hover -->
                <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute -top-12 z-20 bg-slate-900 text-white text-[10px] font-mono py-1 px-2 rounded-xl shadow-xl pointer-events-none whitespace-nowrap border border-slate-700">
                  <div class="font-bold">{{ slot.label }}: {{ slot.sales_formatted }}</div>
                  <div class="text-slate-400 font-sans">{{ slot.invoices }} فاتورة</div>
                </div>

                <!-- Hour Bar -->
                <div class="w-full bg-slate-100 dark:bg-slate-800/80 rounded-lg relative overflow-hidden flex flex-col justify-end h-full">
                  <div
                    class="w-full rounded-lg transition-all duration-300 group-hover:brightness-125"
                    :style="{
                      height: `${Math.max(slot.intensity, 6)}%`,
                      backgroundColor: slot.intensity > 70 ? '#a855f7' : (slot.intensity > 30 ? 'var(--color-primary, #10b981)' : '#64748b')
                    }"
                  ></div>
                </div>

                <!-- Hour Label -->
                <div class="text-[9px] font-mono text-center text-slate-400 dark:text-slate-500 group-hover:text-purple-400 transition-colors">
                  {{ slot.label }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- 🔄 SPLIT MAIN GRID: Recent Invoices (70%) + Low Stock (30%)  -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- 🧾 RIGHT COLUMN (~70%): آخر فواتير المبيعات الصادرة -->
        <div class="lg:col-span-8 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <h2 class="text-sm font-black text-slate-900 dark:text-white">آخر فواتير المبيعات الصادرة</h2>
            </div>
            <router-link
              to="/invoices"
              class="text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-theme-primary transition flex items-center gap-1 cursor-pointer"
            >
              <span>سجل الفواتير بالكامل</span>
              <span>←</span>
            </router-link>
          </div>

          <!-- Invoices Table -->
          <div class="overflow-x-auto">
            <table class="w-full text-start text-xs font-tajawal">
              <thead class="text-slate-400 text-[11px] font-bold border-b border-slate-200 dark:border-slate-800/80">
                <tr>
                  <th class="py-3 text-start">رقم الفاتورة</th>
                  <th class="py-3 text-start">العميل</th>
                  <th class="py-3 text-start">التاريخ</th>
                  <th class="py-3 text-start">الإجمالي</th>
                  <th class="py-3 text-center">الحالة</th>
                  <th class="py-3 text-end">إجراءات</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono">
                <tr
                  v-for="inv in recentInvoices"
                  :key="inv.id"
                  class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                >
                  <!-- رقم الفاتورة -->
                  <td class="py-3.5 text-cyan-600 dark:text-cyan-400 font-bold font-mono">
                    {{ inv.invoice_number }}
                  </td>

                  <!-- العميل -->
                  <td class="py-3.5 font-sans font-bold text-slate-800 dark:text-slate-200">
                    {{ inv.customer_name }}
                  </td>

                  <!-- التاريخ -->
                  <td class="py-3.5 text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                    {{ inv.invoice_date || inv.created_at }}
                  </td>

                  <!-- الإجمالي -->
                  <td class="py-3.5 font-bold text-slate-900 dark:text-white font-mono">
                    {{ formatMoney(inv.net_total) }} <span class="text-[10px] font-sans text-slate-400">ج.م</span>
                  </td>

                  <!-- الحالة -->
                  <td class="py-3.5 text-center font-sans">
                    <span
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                      :class="getInvoiceStatusBadge(inv)"
                    >
                      {{ getInvoiceStatusLabel(inv) }}
                    </span>
                  </td>

                  <!-- إجراءات -->
                  <td class="py-3.5 text-end font-sans">
                    <button
                      type="button"
                      @click="previewInvoice(inv)"
                      class="px-3 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-[11px] font-bold transition cursor-pointer border border-slate-300 dark:border-slate-700 active:scale-95"
                    >
                      معاينة / طباعة
                    </button>
                  </td>
                </tr>

                <tr v-if="recentInvoices.length === 0">
                  <td colspan="6" class="py-12 text-center text-xs text-slate-400 font-bold font-sans">
                    لا توجد فواتير مبيعات مسجلة حتى الآن
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ⚠️ LEFT COLUMN (~30%): تنبيهات النواقص بالمخزن -->
        <div class="lg:col-span-4 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4 flex flex-col justify-between">
          <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <h2 class="text-sm font-black text-slate-900 dark:text-white">تنبيهات النواقص بالمخزن</h2>
              </div>
              <router-link
                to="/smart-reorder"
                class="text-xs font-bold text-theme-primary hover:text-theme-primary transition flex items-center gap-1 cursor-pointer"
              >
                <span>مساعد المشتريات</span>
                <span>←</span>
              </router-link>
            </div>

            <!-- Low Stock Items List -->
            <div class="space-y-2 max-h-[460px] overflow-y-auto pr-1">
              <div
                v-for="item in lowStockItems"
                :key="item.id"
                class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-rose-500/30 transition flex items-center justify-between gap-3 group"
              >
                <!-- Right: Name & Code -->
                <div class="min-w-0 flex-1">
                  <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-theme-primary transition">
                    {{ item.name }}
                  </div>
                  <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                    كود: {{ item.code || `ITM-${item.id}` }}
                  </div>
                </div>

                <!-- Left: Stock Badge & Limit -->
                <div class="text-end shrink-0">
                  <div class="text-xs font-black text-rose-500 font-mono">
                    {{ formatQty(item.current_stock) }} {{ item.unit }}
                  </div>
                  <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                    الحد الأدنى: {{ formatQty(item.min_stock || 5) }}
                  </div>
                </div>
              </div>

              <div v-if="lowStockItems.length === 0" class="py-12 text-center text-xs text-slate-400 font-bold">
                ✓ جميع الأصناف متوفرة بمستويات آمنة
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import api from '../services/api';
import {
    Plus,
    ShoppingCart,
    TrendingUp,
    CreditCard,
    BarChart3,
    Zap,
    RefreshCw
} from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();

const dashboardData = ref(null);
const isLoading = ref(true);

const metrics = computed(() => dashboardData.value?.metrics || {});
const analytics = computed(() => dashboardData.value?.analytics || {});
const dailyTrend = computed(() => analytics.value?.daily_trend || []);
const hourlySales = computed(() => analytics.value?.hourly_sales || []);
const peakHour = computed(() => analytics.value?.peak_hour || null);
const paymentDistribution = computed(() => analytics.value?.payment_distribution || []);
const periodAnalytics = computed(() => analytics.value?.period || {});
const recentInvoices = computed(() => dashboardData.value?.recent_invoices || []);
const lowStockItems = computed(() => dashboardData.value?.low_stock_items || []);
const activeShift = computed(() => dashboardData.value?.active_shift || null);

const maxDailySale = computed(() => {
    const sales = dailyTrend.value.map(d => parseFloat(d.sales) || 0);
    return Math.max(...sales, 1);
});

const isToday = (dateStr) => {
    if (!dateStr) return false;
    const today = new Date().toISOString().split('T')[0];
    return dateStr === today;
};

const computeBarHeight = (sales, max) => {
    const s = parseFloat(sales) || 0;
    if (max <= 0) return 8;
    return Math.min(100, Math.max(8, Math.round((s / max) * 100)));
};

const getPaymentMethodIcon = (key) => {
    const icons = {
        cash: '💵',
        instapay: '⚡',
        visa: '💳',
        e_wallet: '📱',
        bank_transfer: '🏦',
    };
    return icons[key] || '💰';
};

const getPaymentMethodColor = (key) => {
    const colors = {
        cash: 'bg-emerald-500',
        instapay: 'bg-indigo-500',
        visa: 'bg-sky-500',
        e_wallet: 'bg-theme-primary',
        bank_transfer: 'bg-teal-500',
    };
    return colors[key] || 'bg-emerald-500';
};

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatQty = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getInvoiceStatusBadge = (inv) => {
    if (inv.status === 'cancelled') {
        return 'bg-rose-500/10 text-rose-500 border-rose-500/30';
    }
    if (inv.remaining_amount > 0) {
        return 'bg-theme-light text-theme-primary border-theme-border';
    }
    return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30';
};

const getInvoiceStatusLabel = (inv) => {
    if (inv.status === 'cancelled') return 'ملغاة';
    if (inv.remaining_amount > 0) return 'آجل';
    return 'مدفوعة';
};

const previewInvoice = (inv) => {
    router.push(`/invoices?view=${inv.id}`);
};

const fetchDashboard = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/dashboard');
        dashboardData.value = res.data?.data;
    } catch (e) {
        console.error('Failed to load dashboard data:', e);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchDashboard();
});
</script>

