<template>
  <div class="p-4 sm:p-6 rounded-2xl sm:rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4 font-tajawal select-none">
    
    <!-- 🔝 Header: Title + Category Tabs + Search Filter -->
    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800/80">
      <div class="flex items-center gap-2.5">
        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-theme-primary/20 to-emerald-500/20 text-theme-primary flex items-center justify-center text-lg font-black shadow-xs">
          <Sparkles class="w-5 h-5" />
        </span>
        <div>
          <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight">
            {{ $t('dashboard.menu_hub_title') }}
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-bold">
            {{ $t('dashboard.menu_hub_subtitle') }}
          </p>
        </div>
      </div>

      <!-- Search Input -->
      <div class="w-full lg:w-72 relative">
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="$t('dashboard.search_screens_placeholder')"
          class="w-full h-10 ps-9 pe-3 bg-slate-100 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-theme-primary transition"
        />
        <Search class="w-4 h-4 text-slate-400 absolute start-3 top-3 pointer-events-none" />
      </div>
    </div>

    <!-- 📂 Category Tabs -->
    <div class="flex items-center gap-1.5 overflow-x-auto custom-scrollbar pb-1">
      <button
        v-for="tab in categoryTabs"
        :key="tab.id"
        type="button"
        @click="activeTab = tab.id"
        class="px-3 py-1.5 rounded-xl text-xs font-black transition-all duration-150 cursor-pointer shrink-0 flex items-center gap-1.5"
        :class="activeTab === tab.id
          ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-950 shadow-sm'
          : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
      >
        <component :is="tab.icon" class="w-3.5 h-3.5 shrink-0" />
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <!-- 🗂️ The Tiles Grid (Metro App Menu) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-2.5 sm:gap-3">
      <router-link
        v-for="tile in filteredTiles"
        :key="tile.id"
        :to="tile.to"
        @click="playScanBeep"
        class="group relative flex flex-col items-center justify-center p-3 rounded-2xl sm:rounded-3xl transition-all duration-200 active:scale-95 cursor-pointer select-none shadow-md hover:shadow-xl hover:-translate-y-0.5 border min-h-[100px] sm:min-h-[115px]"
        :class="tile.bgClass"
      >
        <!-- Shortcut Badge -->
        <span
          v-if="tile.shortcut"
          class="absolute top-2 start-2 text-[10px] font-mono font-black px-1.5 py-0.5 rounded-md bg-black/20 text-white/90 backdrop-blur-xs border border-white/10"
        >
          {{ tile.shortcut }}
        </span>

        <!-- Icon -->
        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl bg-white/15 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform duration-200 shadow-inner">
          <component :is="tile.icon" class="w-5 h-5 sm:w-6 sm:h-6 text-white" />
        </div>

        <!-- Title -->
        <span class="text-xs sm:text-sm font-black text-white text-center leading-tight">
          {{ tile.title }}
        </span>

        <!-- Description / Subtitle -->
        <span v-if="tile.subtitle" class="text-[10px] text-white/80 font-bold mt-0.5 text-center line-clamp-1">
          {{ tile.subtitle }}
        </span>
      </router-link>
    </div>

    <!-- Empty State for Search -->
    <div v-if="filteredTiles.length === 0" class="text-center py-8 text-slate-400">
      <Search class="w-8 h-8 mx-auto mb-2 opacity-50" />
      <p class="text-xs font-bold">{{ $t('dashboard.no_screens_found') }}</p>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import {
  Search,
  Zap,
  ShoppingBag,
  ShoppingCart,
  Receipt,
  RotateCcw,
  RefreshCw,
  Package,
  Layers,
  Users,
  Truck,
  Building2,
  BarChart3,
  CalendarDays,
  Settings,
  Coffee,
  Radar,
  CreditCard,
  FolderOpen,
  Sparkles,
  Banknote,
} from 'lucide-vue-next';
import { useAudioFeedback } from '../../Composables/useAudioFeedback';

const { playScanBeep } = useAudioFeedback();

const searchQuery = ref('');
const activeTab = ref('all');

const categoryTabs = [
  { id: 'all', label: 'الكل', icon: Sparkles },
  { id: 'sales', label: 'المبيعات والـ POS', icon: ShoppingBag },
  { id: 'purchases', label: 'المشتريات والتوريد', icon: ShoppingCart },
  { id: 'finance', label: 'الخزينة واليومية', icon: Banknote },
  { id: 'inventory', label: 'المخازن والأصناف', icon: Package },
  { id: 'reports', label: 'التقارير والمتابعة', icon: BarChart3 },
  { id: 'setup', label: 'التعريفات والنظام', icon: Settings },
];

const tiles = [
  // 1. Sales & POS
  {
    id: 'pos',
    category: 'sales',
    title: 'نقطة البيع (POS)',
    subtitle: 'كاشير سريع ولمس',
    to: '/pos',
    shortcut: 'F2',
    icon: Zap,
    bgClass: 'bg-gradient-to-br from-emerald-600 to-teal-700 border-emerald-500/50 shadow-emerald-500/20',
  },
  {
    id: 'invoices',
    category: 'sales',
    title: 'فاتورة مبيعات',
    subtitle: 'إصدار ومتابعة البيع',
    to: '/invoices',
    shortcut: 'F4',
    icon: ShoppingBag,
    bgClass: 'bg-gradient-to-br from-emerald-500 to-green-600 border-emerald-400/50 shadow-emerald-500/20',
  },
  {
    id: 'sale_returns',
    category: 'sales',
    title: 'مرتجع مبيعات',
    subtitle: 'استرجاع بضاعة',
    to: '/returns/create',
    icon: RotateCcw,
    bgClass: 'bg-gradient-to-br from-teal-600 to-emerald-700 border-teal-500/50 shadow-teal-500/20',
  },
  {
    id: 'customers',
    category: 'sales',
    title: 'العملاء والحسابات',
    subtitle: 'كشوف حساب الآجل',
    to: '/customers',
    icon: Users,
    bgClass: 'bg-gradient-to-br from-sky-600 to-blue-700 border-sky-500/50 shadow-sky-500/20',
  },

  // 2. Purchases
  {
    id: 'purchases',
    category: 'purchases',
    title: 'فاتورة مشتريات',
    subtitle: 'توريد واستلام بضاعة',
    to: '/purchases/create',
    icon: ShoppingCart,
    bgClass: 'bg-gradient-to-br from-amber-600 to-orange-700 border-amber-500/50 shadow-amber-500/20',
  },
  {
    id: 'purchase_returns',
    category: 'purchases',
    title: 'مرتجع مشتريات',
    subtitle: 'إرجاع لمورد',
    to: '/returns',
    icon: RefreshCw,
    bgClass: 'bg-gradient-to-br from-orange-700 to-rose-700 border-orange-600/50 shadow-orange-500/20',
  },
  {
    id: 'suppliers',
    category: 'purchases',
    title: 'الموردين والشركات',
    subtitle: 'أرصدة الموردين',
    to: '/suppliers',
    icon: Truck,
    bgClass: 'bg-gradient-to-br from-amber-700 to-yellow-800 border-amber-600/50 shadow-amber-500/20',
  },
  {
    id: 'smart_reorder',
    category: 'purchases',
    title: 'رادار النواقص',
    subtitle: 'إعادة الطلب الذكي',
    to: '/purchases/smart-reorder',
    icon: Radar,
    bgClass: 'bg-gradient-to-br from-purple-600 to-indigo-700 border-purple-500/50 shadow-purple-500/20',
  },

  // 3. Finance
  {
    id: 'daily_journal',
    category: 'finance',
    title: 'دفتر اليومية',
    subtitle: 'حركة الخزينة والسيولة',
    to: '/daily-journal',
    icon: CalendarDays,
    bgClass: 'bg-gradient-to-br from-blue-600 to-indigo-700 border-blue-500/50 shadow-blue-500/20',
  },
  {
    id: 'expenses',
    category: 'finance',
    title: 'المصروفات والعهد',
    subtitle: 'سندات الصرف والتشغيل',
    to: '/expenses',
    icon: Receipt,
    bgClass: 'bg-gradient-to-br from-rose-500 to-red-600 border-rose-400/50 shadow-rose-500/20',
  },

  // 4. Inventory
  {
    id: 'items',
    category: 'inventory',
    title: 'كارت صنف والمخزن',
    subtitle: 'إدخال البضاعة والأسعار',
    to: '/items',
    icon: Package,
    bgClass: 'bg-gradient-to-br from-cyan-600 to-teal-700 border-cyan-500/50 shadow-cyan-500/20',
  },
  {
    id: 'categories',
    category: 'inventory',
    title: 'فئات الأصناف',
    subtitle: 'تصنيفات وأقسام المنيو',
    to: '/categories',
    icon: FolderOpen,
    bgClass: 'bg-gradient-to-br from-teal-700 to-cyan-800 border-teal-600/50 shadow-teal-500/20',
  },
  {
    id: 'store_stocks',
    category: 'inventory',
    title: 'جرد وأرصدة الفروع',
    subtitle: 'جرد الفروع والمخازن',
    to: '/stores/stocks',
    icon: Layers,
    bgClass: 'bg-gradient-to-br from-sky-700 to-cyan-800 border-sky-600/50 shadow-sky-500/20',
  },
  {
    id: 'stock_transfers',
    category: 'inventory',
    title: 'التحويل بين المخازن',
    subtitle: 'إذن تحويل بضاعة',
    to: '/stock-transfers',
    icon: Truck,
    bgClass: 'bg-gradient-to-br from-teal-600 to-emerald-800 border-teal-500/50 shadow-teal-500/20',
  },
  {
    id: 'coffee_blender',
    category: 'inventory',
    title: 'خلطة وتجميع الأصناف',
    subtitle: 'معمل البن والإنتاج',
    to: '/coffee-blender',
    icon: Coffee,
    bgClass: 'bg-gradient-to-br from-amber-800 to-yellow-900 border-amber-700/50 shadow-amber-600/20',
  },

  // 5. Reports
  {
    id: 'reports',
    category: 'reports',
    title: 'التقارير الشاملة',
    subtitle: 'أرباح، مبيعات، ومخازن',
    to: '/reports',
    icon: BarChart3,
    bgClass: 'bg-gradient-to-br from-indigo-600 to-blue-700 border-indigo-500/50 shadow-indigo-500/20',
  },

  // 6. Setup
  {
    id: 'stores',
    category: 'setup',
    title: 'الفروع والمخازن',
    subtitle: 'إدارة منافذ البيع',
    to: '/stores',
    icon: Building2,
    bgClass: 'bg-gradient-to-br from-violet-600 to-purple-800 border-violet-500/50 shadow-violet-500/20',
  },
  {
    id: 'settings',
    category: 'setup',
    title: 'إعدادات النظام',
    subtitle: 'الضرائب، الفاتورة، والفرع',
    to: '/settings',
    icon: Settings,
    bgClass: 'bg-gradient-to-br from-slate-700 to-slate-900 border-slate-600/50 shadow-slate-600/20',
  },
];

const filteredTiles = computed(() => {
  let list = tiles;

  // Filter by Tab
  if (activeTab.value !== 'all') {
    list = list.filter((t) => t.category === activeTab.value);
  }

  // Filter by Search Query
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase();
    list = list.filter((t) =>
      t.title.toLowerCase().includes(q) ||
      (t.subtitle && t.subtitle.toLowerCase().includes(q)) ||
      (t.shortcut && t.shortcut.toLowerCase().includes(q))
    );
  }

  return list;
});
</script>
