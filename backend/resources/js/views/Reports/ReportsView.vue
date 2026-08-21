<template>
  <SpaLayout>
    <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('reports.title')"
        :subtitle="$t('reports.subtitle')"
        :icon="'📊'"
      >
        <template #actions>
          <button
            type="button"
            @click="printReport"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
          >
            <Printer class="w-4 h-4" />
            <span>طباعة التقرير (A4)</span>
          </button>
        </template>
      </PageHeader>

      <!-- Global Filter Bar -->
      <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-3">
        <!-- Preset Periods -->
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              v-for="p in presets"
              :key="p.key"
              type="button"
              @click="setPeriod(p.key)"
              class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
              :class="filters.period === p.key ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'"
            >
              {{ p.label }}
            </button>
          </div>

          <!-- Store Selector Filter -->
          <div class="w-full sm:w-48">
            <select
              v-model="filters.store_id"
              @change="fetchReportsData"
              class="w-full h-9 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
            >
              <option value="all">كافة الفروع والمخازن</option>
              <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
        </div>

        <!-- Custom Dates & Stock Filter Row -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-800/80">
          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 font-bold">من:</span>
            <input
              v-model="filters.from"
              @change="customDateChanged"
              type="date"
              class="h-9 px-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
            >
            <span class="text-xs text-slate-400 font-bold">إلى:</span>
            <input
              v-model="filters.to"
              @change="customDateChanged"
              type="date"
              class="h-9 px-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
            >
          </div>

          <div v-if="activeTab === 'inventory'" class="flex items-center gap-2">
            <span class="text-xs text-slate-400 font-bold">فلتر الرصيد:</span>
            <select
              v-model="filters.stock_filter"
              @change="fetchReportsData"
              class="h-9 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
            >
              <option value="all">كافة الأصناف</option>
              <option value="in_stock">المتوفر فقط بالمخزن</option>
              <option value="zero_stock">الأصناف المنتهية (رصيد 0)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-800 text-xs font-bold">
        <button
          v-for="t in tabs"
          :key="t.key"
          type="button"
          @click="activeTab = t.key"
          class="px-4 py-2.5 rounded-xl transition flex items-center gap-2 whitespace-nowrap cursor-pointer"
          :class="activeTab === t.key ? 'bg-amber-500 text-slate-950 font-black shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
        >
          <span>{{ t.icon }}</span>
          <span>{{ t.label }}</span>
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-16 text-center">
        <div class="w-10 h-10 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400 font-bold">جاري تحميل وتجميع التقارير والبيانات التحليلية...</p>
      </div>

      <!-- TAB 1: Sales & Executive Profit & Loss -->
      <div v-else-if="activeTab === 'sales'" class="space-y-6">
        <!-- 9 Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Total Sales -->
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">إجمالي المبيعات (Revenue)</span>
            <div class="text-2xl font-black text-white font-mono">{{ formatMoney(summary.total_sales) }} <span class="text-xs text-slate-400">ج.م</span></div>
            <span class="text-[10px] text-slate-500">عدد الفواتير: {{ summary.invoices_count }} فاتورة</span>
          </div>

          <!-- Total COGS -->
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">تكلفة البضاعة المباعة (COGS)</span>
            <div class="text-2xl font-black text-rose-400 font-mono">{{ formatMoney(summary.total_cogs) }} <span class="text-xs text-slate-400">ج.م</span></div>
            <span class="text-[10px] text-slate-500">تكلفة شراء خامات البن والأصناف</span>
          </div>

          <!-- Gross Profit & Margin -->
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-emerald-500/30 bg-emerald-500/5 shadow-md space-y-1">
            <span class="text-xs font-bold text-emerald-400">مجمل الربح (Gross Profit)</span>
            <div class="text-2xl font-black text-emerald-400 font-mono">{{ formatMoney(summary.gross_profit) }} <span class="text-xs text-emerald-500">ج.م</span></div>
            <span class="text-[10px] text-emerald-500 font-bold">هامش مجمل الربح: {{ summary.margin_percentage }}%</span>
          </div>

          <!-- Operating Expenses -->
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">المصروفات التشغيلية (Expenses)</span>
            <div class="text-2xl font-black text-amber-400 font-mono">{{ formatMoney(summary.total_expenses) }} <span class="text-xs text-slate-400">ج.م</span></div>
            <span class="text-[10px] text-slate-500">عدد إيصالات الصرف: {{ summary.expenses_count }} إيصال</span>
          </div>

          <!-- Net True Profit -->
          <div class="sm:col-span-2 lg:col-span-2 p-5 rounded-2xl bg-gradient-to-r from-emerald-950/60 to-slate-950/80 border border-emerald-500/40 shadow-xl space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-xs font-black text-emerald-300">صافي الربح الحقيقي (Net True Profit)</span>
              <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-400 font-mono font-bold text-[10px]">مبيعات - تكلفة - مصروفات</span>
            </div>
            <div class="text-3xl font-black font-mono" :class="summary.net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400'">
              {{ formatMoney(summary.net_profit) }} <span class="text-sm">ج.م</span>
            </div>
            <span class="text-[11px] text-slate-400">الربح الفعلي النهائي بعد خصم كافة التكاليف والمصروفات التشغيلية</span>
          </div>

          <!-- Cash Collected -->
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">المحصل نقداً بالخزينة</span>
            <div class="text-xl font-black text-cyan-400 font-mono">{{ formatMoney(summary.total_paid) }} <span class="text-xs text-slate-400">ج.م</span></div>
            <span class="text-[10px] text-slate-500">السيولة النقدية الفعلية المحصلة</span>
          </div>

          <!-- Receivables in Period -->
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">المتبقي آجل (في الفترة)</span>
            <div class="text-xl font-black text-amber-400 font-mono">{{ formatMoney(summary.total_remaining) }} <span class="text-xs text-slate-400">ج.م</span></div>
            <span class="text-[10px] text-slate-500">إجمالي ديون العملاء الإجمالية: {{ formatMoney(summary.total_customers_debt) }} ج.م</span>
          </div>
        </div>
      </div>

      <!-- TAB 2: Items Profitability -->
      <div v-else-if="activeTab === 'items'" class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
          <h3 class="text-xs font-bold text-slate-300">مبيعات وربحية الأصناف وحبوب البن</h3>
          <span class="text-xs text-slate-500 font-mono">عدد الأصناف: {{ itemProfits.length }}</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900 text-slate-400 border-b border-slate-800">
                <th class="p-3 text-start font-bold">الصنف</th>
                <th class="p-3 text-start font-bold">الكود</th>
                <th class="p-3 text-start font-bold">الفئة</th>
                <th class="p-3 text-end font-bold">الكمية المباعة</th>
                <th class="p-3 text-end font-bold">إجمالي المبيعات</th>
                <th class="p-3 text-end font-bold">التكلفة (COGS)</th>
                <th class="p-3 text-end font-bold">مجمل الربح</th>
                <th class="p-3 text-end font-bold">الهامش %</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50 font-sans">
              <tr v-for="it in itemProfits" :key="it.item_id" class="hover:bg-slate-900/40">
                <td class="p-3 font-bold text-white">{{ it.name }}</td>
                <td class="p-3 font-mono text-slate-400">{{ it.code || '—' }}</td>
                <td class="p-3 text-slate-400">{{ it.category }}</td>
                <td class="p-3 text-end font-mono font-bold text-cyan-400">{{ it.total_qty }} {{ it.unit }}</td>
                <td class="p-3 text-end font-mono font-bold text-white">{{ formatMoney(it.total_revenue) }}</td>
                <td class="p-3 text-end font-mono text-rose-400">{{ formatMoney(it.total_cogs) }}</td>
                <td class="p-3 text-end font-mono font-black text-emerald-400">{{ formatMoney(it.profit) }}</td>
                <td class="p-3 text-end font-mono font-bold text-amber-400">{{ it.margin }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 3: Stores Comparison -->
      <div v-else-if="activeTab === 'stores'" class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-slate-800">
          <h3 class="text-xs font-bold text-slate-300">مقارنة أداء الفروع والمخازن</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900 text-slate-400 border-b border-slate-800">
                <th class="p-3 text-start font-bold">الفرع / المخزن</th>
                <th class="p-3 text-center font-bold">الفواتير</th>
                <th class="p-3 text-end font-bold">إجمالي المبيعات</th>
                <th class="p-3 text-end font-bold">المحصل</th>
                <th class="p-3 text-end font-bold">الآجل</th>
                <th class="p-3 text-end font-bold">مجمل الربح</th>
                <th class="p-3 text-end font-bold">الهامش %</th>
                <th class="p-3 text-end font-bold">الحصة السوقية %</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50 font-sans">
              <tr v-for="st in storeBreakdown" :key="st.id" class="hover:bg-slate-900/40">
                <td class="p-3 font-bold text-white font-tajawal">{{ st.name }}</td>
                <td class="p-3 text-center font-mono font-bold text-slate-300">{{ st.invoice_count }}</td>
                <td class="p-3 text-end font-mono font-bold text-white">{{ formatMoney(st.total_sales) }}</td>
                <td class="p-3 text-end font-mono text-emerald-400">{{ formatMoney(st.total_paid) }}</td>
                <td class="p-3 text-end font-mono text-amber-400">{{ formatMoney(st.total_remaining) }}</td>
                <td class="p-3 text-end font-mono font-black text-emerald-400">{{ formatMoney(st.gross_profit) }}</td>
                <td class="p-3 text-end font-mono font-bold text-cyan-400">{{ st.margin }}%</td>
                <td class="p-3 text-end font-mono font-black text-amber-400">{{ st.share_pct }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 4: Customers Analytics -->
      <div v-else-if="activeTab === 'customers'" class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
          <h3 class="text-xs font-bold text-slate-300">أعلى العملاء مسحوبات ومديونيات في الفترة</h3>
          <span class="text-xs text-slate-500 font-mono">عرض أعلى 50 عميل</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900 text-slate-400 border-b border-slate-800">
                <th class="p-3 text-start font-bold">العميل</th>
                <th class="p-3 text-start font-bold">الهاتف</th>
                <th class="p-3 text-center font-bold">عدد الفواتير</th>
                <th class="p-3 text-end font-bold">إجمالي المشتريات</th>
                <th class="p-3 text-end font-bold">المسدد</th>
                <th class="p-3 text-end font-bold">المتبقي بالفترة</th>
                <th class="p-3 text-end font-bold">الرصيد الكلي الحالي</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50 font-sans">
              <tr v-for="c in customerSales" :key="c.customer_id" class="hover:bg-slate-900/40">
                <td class="p-3 font-bold text-white font-tajawal">{{ c.name }}</td>
                <td class="p-3 font-mono text-slate-400">{{ c.phone || '—' }}</td>
                <td class="p-3 text-center font-mono font-bold text-slate-300">{{ c.total_invoices }}</td>
                <td class="p-3 text-end font-mono font-bold text-white">{{ formatMoney(c.total_bought) }}</td>
                <td class="p-3 text-end font-mono text-emerald-400">{{ formatMoney(c.total_paid) }}</td>
                <td class="p-3 text-end font-mono text-amber-400">{{ formatMoney(c.total_debt_in_period) }}</td>
                <td class="p-3 text-end font-mono font-bold" :class="c.current_balance > 0 ? 'text-rose-400' : 'text-emerald-400'">
                  {{ formatMoney(c.current_balance) }} ج.م
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 5: Operational Expenses -->
      <div v-else-if="activeTab === 'expenses'" class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-slate-800">
          <h3 class="text-xs font-bold text-slate-300">تبويب المصروفات التشغيلية حسب الفئة</h3>
        </div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="e in expensesBreakdown"
            :key="e.category"
            class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 space-y-2"
          >
            <div class="flex items-center justify-between text-xs font-bold">
              <span class="text-amber-400 font-tajawal">{{ e.category }}</span>
              <span class="text-slate-400 font-mono">{{ e.count }} إيصال</span>
            </div>
            <div class="text-xl font-black text-white font-mono">
              {{ formatMoney(e.amount) }} <span class="text-xs text-slate-400">ج.م</span>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 6: Inventory Valuation & ABC -->
      <div v-else-if="activeTab === 'inventory'" class="space-y-6">
        <!-- Stock Valuation Top Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">تقييم المخزون بسعر التكلفة</span>
            <div class="text-2xl font-black text-white font-mono">{{ formatMoney(inventoryData.stock_cost_valuation) }} <span class="text-xs text-slate-400">ج.م</span></div>
          </div>
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">تقييم المخزون بسعر البيع (القطاعي)</span>
            <div class="text-2xl font-black text-emerald-400 font-mono">{{ formatMoney(inventoryData.stock_selling_valuation) }} <span class="text-xs text-slate-400">ج.م</span></div>
          </div>
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-emerald-500/30 bg-emerald-500/5 shadow-md space-y-1">
            <span class="text-xs font-bold text-emerald-400">الأرباح المتوقعة عند البيع</span>
            <div class="text-2xl font-black text-emerald-400 font-mono">{{ formatMoney(inventoryData.expected_stock_profit) }} <span class="text-xs text-emerald-500">ج.م</span></div>
          </div>
        </div>

        <!-- Inventory Table -->
        <div class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
          <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-300">أرصدة وتقييم الأصناف</h3>
            <span class="text-xs text-slate-500 font-mono">عدد الأصناف: {{ inventoryData.items?.length || 0 }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-900 text-slate-400 border-b border-slate-800">
                  <th class="p-3 text-start font-bold">الصنف</th>
                  <th class="p-3 text-start font-bold">الكود</th>
                  <th class="p-3 text-end font-bold">الرصيد الحالي</th>
                  <th class="p-3 text-end font-bold">سعر التكلفة</th>
                  <th class="p-3 text-end font-bold">سعر البيع</th>
                  <th class="p-3 text-end font-bold">قيمة التكلفة</th>
                  <th class="p-3 text-end font-bold">قيمة البيع</th>
                  <th class="p-3 text-end font-bold">الربح المتوقع</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/50 font-sans">
                <tr v-for="itm in inventoryData.items" :key="itm.id" class="hover:bg-slate-900/40">
                  <td class="p-3 font-bold text-white">{{ itm.name }}</td>
                  <td class="p-3 font-mono text-slate-400">{{ itm.code || '—' }}</td>
                  <td class="p-3 text-end font-mono font-bold text-cyan-400">{{ itm.current_stock }} {{ itm.unit }}</td>
                  <td class="p-3 text-end font-mono text-slate-300">{{ formatMoney(itm.cost_price) }}</td>
                  <td class="p-3 text-end font-mono text-emerald-400">{{ formatMoney(itm.selling_price) }}</td>
                  <td class="p-3 text-end font-mono text-rose-400">{{ formatMoney(itm.cost_val) }}</td>
                  <td class="p-3 text-end font-mono font-bold text-white">{{ formatMoney(itm.sell_val) }}</td>
                  <td class="p-3 text-end font-mono font-black text-emerald-400">{{ formatMoney(itm.profit) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB 7: Treasury Cash Flow -->
      <div v-else-if="activeTab === 'treasury'" class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">إجمالي المقبوضات (Inflow)</span>
            <div class="text-2xl font-black text-emerald-400 font-mono">{{ formatMoney(treasuryData.total_inflow || 0) }} <span class="text-xs text-slate-400">ج.م</span></div>
          </div>
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">إجمالي المدفوعات (Outflow)</span>
            <div class="text-2xl font-black text-rose-400 font-mono">{{ formatMoney(treasuryData.total_outflow || 0) }} <span class="text-xs text-slate-400">ج.م</span></div>
          </div>
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
            <span class="text-xs font-bold text-slate-400">صافي التدفق النقدي (Net Cash)</span>
            <div class="text-2xl font-black text-cyan-400 font-mono">{{ formatMoney(treasuryData.net_cash_flow || 0) }} <span class="text-xs text-slate-400">ج.م</span></div>
          </div>
        </div>
      </div>
    </div>
  </SpaLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import SpaLayout from '../../Layouts/SpaLayout.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import api from '../../services/api';
import {
    Printer
} from 'lucide-vue-next';

const activeTab = ref('sales');
const isLoading = ref(false);

const stores = ref([]);

const filters = reactive({
    period: 'this_month',
    from: '',
    to: '',
    store_id: 'all',
    stock_filter: 'all',
});

const tabs = [
    { key: 'sales', label: 'المبيعات والأرباح', icon: '📈' },
    { key: 'items', label: 'ربحية الأصناف', icon: '☕' },
    { key: 'stores', label: 'مقارنة الفروع', icon: '🏢' },
    { key: 'customers', label: 'العملاء والآجل', icon: '👥' },
    { key: 'expenses', label: 'المصروفات', icon: '💸' },
    { key: 'inventory', label: 'تقييم المخزون', icon: '📦' },
    { key: 'treasury', label: 'الخزينة والسيولة', icon: '🏦' },
];

const presets = [
    { key: 'today', label: 'اليوم' },
    { key: 'yesterday', label: 'أمس' },
    { key: 'this_week', label: 'هذا الأسبوع' },
    { key: 'this_month', label: 'هذا الشهر' },
    { key: 'this_year', label: 'هذا العام' },
];

const summary = ref({
    total_sales: 0,
    total_cogs: 0,
    gross_profit: 0,
    margin_percentage: 0,
    total_expenses: 0,
    expenses_count: 0,
    net_profit: 0,
    invoices_count: 0,
    avg_invoice: 0,
    total_paid: 0,
    total_remaining: 0,
    total_customers_debt: 0,
});

const itemProfits = ref([]);
const storeBreakdown = ref([]);
const customerSales = ref([]);
const expensesBreakdown = ref([]);
const inventoryData = ref({
    stock_cost_valuation: 0,
    stock_selling_valuation: 0,
    expected_stock_profit: 0,
    items: [],
    abc_data: null,
});
const treasuryData = ref({
    total_inflow: 0,
    total_outflow: 0,
    net_cash_flow: 0,
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const setPeriod = (period) => {
    filters.period = period;
    const now = new Date();
    if (period === 'today') {
        const todayStr = now.toISOString().split('T')[0];
        filters.from = todayStr;
        filters.to = todayStr;
    } else if (period === 'yesterday') {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        const yStr = d.toISOString().split('T')[0];
        filters.from = yStr;
        filters.to = yStr;
    } else if (period === 'this_week') {
        const d = new Date();
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        filters.from = new Date(d.setDate(diff)).toISOString().split('T')[0];
        filters.to = new Date().toISOString().split('T')[0];
    } else if (period === 'this_month') {
        const d = new Date();
        filters.from = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
        filters.to = new Date().toISOString().split('T')[0];
    } else if (period === 'this_year') {
        const d = new Date();
        filters.from = new Date(d.getFullYear(), 0, 1).toISOString().split('T')[0];
        filters.to = new Date().toISOString().split('T')[0];
    }

    fetchReportsData();
};

const customDateChanged = () => {
    filters.period = 'custom';
    fetchReportsData();
};

const loadStores = async () => {
    try {
        const res = await api.get('/stores');
        stores.value = res.data?.data || [];
    } catch (e) {
        console.error('Failed to load stores:', e);
    }
};

const fetchReportsData = async () => {
    isLoading.value = true;
    try {
        const params = {
            period: filters.period,
            from: filters.from || undefined,
            to: filters.to || undefined,
            store_id: filters.store_id !== 'all' ? filters.store_id : undefined,
            stock_filter: filters.stock_filter !== 'all' ? filters.stock_filter : undefined,
        };

        const res = await api.get('/reports/comprehensive', { params });
        const d = res.data || {};

        summary.value = d.summary || {};
        itemProfits.value = d.item_profits || [];
        storeBreakdown.value = d.store_breakdown || [];
        customerSales.value = d.customer_sales || [];
        expensesBreakdown.value = d.expenses_breakdown || [];
        inventoryData.value = d.inventory_data || {};
        treasuryData.value = d.treasury_data || {};
    } catch (e) {
        console.error('Failed to load comprehensive reports:', e);
    } finally {
        isLoading.value = false;
    }
};

const printReport = () => {
    window.print();
};

onMounted(() => {
    const d = new Date();
    filters.from = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
    filters.to = new Date().toISOString().split('T')[0];

    loadStores();
    fetchReportsData();
});
</script>
