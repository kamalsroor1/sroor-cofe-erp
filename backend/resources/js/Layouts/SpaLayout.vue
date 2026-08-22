<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-tajawal selection:bg-amber-500 selection:text-slate-950" dir="rtl">
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🔝 TOP HEADER BAR (Exact match to reference image)          -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <header class="h-16 bg-slate-950/95 border-b border-slate-800/80 sticky top-0 z-40 px-4 sm:px-6 flex items-center justify-between shadow-lg select-none">
      <!-- Right Side: User, Theme, Notifications, Shift & Store Context -->
      <div class="flex items-center gap-2 sm:gap-3">
        <!-- Mobile Menu Hamburger -->
        <button
          type="button"
          @click="isSidebarOpen = true"
          class="p-2 text-slate-400 hover:text-white hover:bg-slate-800/80 rounded-xl transition md:hidden cursor-pointer active:scale-95"
          title="القائمة"
        >
          <Menu class="w-6 h-6" />
        </button>

        <!-- User Profile Dropdown / Pill -->
        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-2xl text-xs font-bold text-slate-200">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          <span>{{ authStore.userName }} - {{ authStore.roles?.[0] || 'المدير العام' }}</span>
        </div>

        <!-- Theme Switcher Button -->
        <button
          type="button"
          @click="toggleTheme"
          class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 rounded-2xl text-xs font-bold text-amber-400 flex items-center gap-1.5 transition cursor-pointer active:scale-95"
        >
          <Sun v-if="appConfigStore.isDark" class="w-3.5 h-3.5 text-amber-400" />
          <Moon v-else class="w-3.5 h-3.5 text-indigo-400" />
          <span class="hidden md:inline">{{ appConfigStore.isDark ? 'الوضع النهاري' : 'الوضع الليلي' }}</span>
        </button>

        <!-- Notifications Bell -->
        <router-link
          to="/activity-logs"
          class="relative p-2 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded-2xl text-slate-300 hover:text-white transition cursor-pointer"
          title="الإشعارات والتنبيهات"
        >
          <Bell class="w-4 h-4 text-amber-400" />
          <span class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white rounded-full text-[9px] font-black flex items-center justify-center">
            3
          </span>
        </router-link>

        <!-- Store Context & Shift Badge -->
        <div v-if="!isSuperAdminPanel" class="hidden lg:flex items-center gap-2">
          <!-- Active Store Badge -->
          <div class="px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-2xl text-xs font-bold text-cyan-400 flex items-center gap-1.5">
            <StoreIcon class="w-3.5 h-3.5" />
            <span>{{ authStore.activeStoreName || 'المخزن والفرع الرئيسي' }}</span>
          </div>

          <!-- Active Shift Badge -->
          <router-link
            to="/daily-journal"
            class="px-3 py-1.5 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl text-xs font-bold text-emerald-400 flex items-center gap-1.5 hover:bg-emerald-500/20 transition cursor-pointer"
          >
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>• فتح</span>
          </router-link>
        </div>
      </div>

      <!-- Left Side: Live Arabic Clock & Brand Header -->
      <div class="flex items-center gap-3">
        <!-- Live Realtime Clock -->
        <div class="text-xs font-bold text-slate-400 font-mono tracking-tight flex items-center gap-2">
          <span>{{ currentTimeStr }}</span>
        </div>

        <!-- Super Admin Switcher (If Permitted) -->
        <router-link
          v-if="canAccessSuperAdmin"
          to="/super-admin/dashboard"
          class="hidden sm:flex items-center gap-1 px-2.5 py-1 bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-purple-400 rounded-xl text-xs font-black transition"
        >
          <span>👑</span>
          <span>السوبر أدمن</span>
        </router-link>

        <!-- Logout Button -->
        <button
          type="button"
          @click="handleLogout"
          class="p-2 text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition cursor-pointer"
          title="تسجيل الخروج"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🖥️ MAIN BODY: SIDEBAR + CONTENT                             -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="flex-1 flex overflow-hidden relative">
      <!-- 💻 DESKTOP SIDEBAR (Exact match to reference image) -->
      <aside class="hidden md:flex w-64 lg:w-72 bg-slate-950 border-l border-slate-800/80 flex-col shrink-0 font-tajawal select-none">
        <!-- Sidebar Top: Brand & Logo -->
        <div class="p-4 border-b border-slate-800/80 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-600 to-amber-500 p-0.5 shadow-lg shadow-amber-500/20 flex items-center justify-center text-slate-950 font-black text-lg">
              ☕
            </div>
            <div>
              <h2 class="font-black text-sm text-white tracking-tight">
                {{ appConfigStore.companyName || 'سرور كوفي' }}
              </h2>
              <p class="text-[10px] text-slate-400 font-bold">
                توزيع خامات ومطاحن البن
              </p>
            </div>
          </div>
          <button type="button" class="text-slate-500 hover:text-slate-300 transition text-sm">
            «
          </button>
        </div>

        <!-- Sidebar Navigation List -->
        <div class="flex-1 overflow-y-auto p-4 space-y-2 custom-scrollbar">
          <!-- 🌟 Big Amber Action Button: New Invoice (F2) -->
          <router-link
            to="/pos"
            class="flex items-center justify-center gap-2 w-full py-3 px-4 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-lg shadow-amber-500/25 transition-all active:scale-95 cursor-pointer mb-3"
          >
            <Plus class="w-4 h-4 stroke-[3]" />
            <span>+ فاتورة بيع جديدة (F2)</span>
          </router-link>

          <!-- 🏠 Dashboard Active Tab -->
          <router-link
            to="/"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name === 'dashboard' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black shadow-xs' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <LayoutDashboard class="w-4 h-4 text-amber-400" />
            <span>لوحة التحكم (Dashboard)</span>
          </router-link>

          <!-- 1. المبيعات والفواتير -->
          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">
            المبيعات والفواتير
          </div>

          <router-link
            to="/invoices"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('invoices') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <FileText class="w-4 h-4 text-slate-400" />
            <span>فواتير المبيعات</span>
          </router-link>

          <router-link
            to="/daily-journal"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('daily_journal') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <Wallet class="w-4 h-4 text-slate-400" />
            <span>اليومية وحركة الدرج</span>
          </router-link>

          <!-- 2. العملاء والحسابات -->
          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">
            العملاء والحسابات
          </div>

          <router-link
            to="/customers"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('customers') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <Users class="w-4 h-4 text-slate-400" />
            <span>العملاء والشركات</span>
          </router-link>

          <!-- 3. المخزون والفروع والتوزيع -->
          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">
            المخزون والفروع والتوزيع
          </div>

          <router-link
            to="/items"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('items') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <Package class="w-4 h-4 text-slate-400" />
            <span>الأصناف والأسعار</span>
          </router-link>

          <router-link
            to="/purchases"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('purchases') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <ShoppingCart class="w-4 h-4 text-slate-400" />
            <span>فواتير المشتريات</span>
          </router-link>

          <router-link
            to="/suppliers"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('suppliers') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <Building2 class="w-4 h-4 text-slate-400" />
            <span>الموردون والشركات</span>
          </router-link>

          <router-link
            to="/stores"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('stores') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <StoreIcon class="w-4 h-4 text-slate-400" />
            <span>المخازن والفروع</span>
          </router-link>

          <router-link
            to="/coffee-blender"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('coffee_blender') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <Layers class="w-4 h-4 text-slate-400" />
            <span>صانع الخلطات والبن</span>
          </router-link>

          <!-- 4. المرتجعات والمصروفات والتقارير -->
          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">
            المرتجعات والمصروفات والتقارير
          </div>

          <router-link
            to="/expenses"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('expenses') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <Receipt class="w-4 h-4 text-slate-400" />
            <span>المصروفات والنثريات</span>
          </router-link>

          <router-link
            to="/returns"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('returns') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <RotateCcw class="w-4 h-4 text-slate-400" />
            <span>سجل المرتجعات</span>
          </router-link>

          <router-link
            to="/reports"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('reports') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <BarChart3 class="w-4 h-4 text-slate-400" />
            <span>التقارير المالية والأرباح</span>
          </router-link>

          <!-- 5. إدارة النظام والمستخدمين -->
          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">
            إدارة النظام والمستخدمين
          </div>

          <router-link
            to="/users"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('users') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <Users class="w-4 h-4 text-slate-400" />
            <span>المستخدمون والكاشير</span>
          </router-link>

          <router-link
            to="/roles"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('roles') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <ShieldCheck class="w-4 h-4 text-slate-400" />
            <span>الأدوار والصلاحيات</span>
          </router-link>

          <router-link
            to="/activity-logs"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('activity_logs') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <Activity class="w-4 h-4 text-slate-400" />
            <span>سجل العمليات والرقابة</span>
          </router-link>

          <router-link
            to="/settings"
            class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('settings') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-900'"
          >
            <Sliders class="w-4 h-4 text-slate-400" />
            <span>إعدادات المؤسسة</span>
          </router-link>
        </div>

        <!-- Sidebar Bottom: User Profile -->
        <div class="p-3 border-t border-slate-800/80 flex items-center justify-between bg-slate-950/60">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-xs">
              {{ authStore.userName?.charAt(0) || 'U' }}
            </div>
            <div>
              <div class="text-xs font-bold text-white">{{ authStore.userName }}</div>
              <div class="text-[10px] text-slate-400 font-mono">{{ authStore.roles?.[0] || 'المدير العام' }}</div>
            </div>
          </div>
          <button
            type="button"
            @click="handleLogout"
            class="p-1.5 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition"
            title="تسجيل الخروج"
          >
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </aside>

      <!-- Main Content Stage -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-slate-900/90 pb-24 md:pb-8">
        <slot />
      </main>

      <!-- Fixed Mobile Bottom Navigation Bar -->
      <MobileBottomNav @open-drawer="isSidebarOpen = true" />
    </div>

    <!-- Mobile Drawer Sidebar (When opened on phone/tablet) -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="isSidebarOpen"
          @click="isSidebarOpen = false"
          class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs z-[9998] md:hidden"
        ></div>
      </Transition>

      <Transition name="sidebar-drawer">
        <aside
          v-if="isSidebarOpen"
          class="fixed inset-y-0 right-0 w-[85vw] max-w-[360px] bg-slate-950 border-l border-slate-800 flex flex-col shadow-2xl z-[9999] font-tajawal md:hidden"
          dir="rtl"
        >
          <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-xl">☕</span>
              <span class="font-black text-sm text-white">{{ appConfigStore.companyName || 'سرور كوفي' }}</span>
            </div>
            <button @click="isSidebarOpen = false" class="text-slate-400 p-2 font-bold">✕</button>
          </div>

          <div class="flex-1 overflow-y-auto p-4 space-y-2">
            <router-link
              to="/pos"
              @click="isSidebarOpen = false"
              class="flex items-center justify-center gap-2 w-full py-3 rounded-2xl bg-amber-500 text-slate-950 font-black text-xs shadow-lg mb-3"
            >
              <span>+ فاتورة بيع جديدة (F2)</span>
            </router-link>

            <router-link
              to="/"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-200"
            >
              <LayoutDashboard class="w-4 h-4 text-amber-400" />
              <span>لوحة التحكم (Dashboard)</span>
            </router-link>

            <router-link
              to="/invoices"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-300"
            >
              <FileText class="w-4 h-4" />
              <span>فواتير المبيعات</span>
            </router-link>

            <router-link
              to="/customers"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-300"
            >
              <Users class="w-4 h-4" />
              <span>العملاء والشركات</span>
            </router-link>

            <router-link
              to="/items"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-300"
            >
              <Package class="w-4 h-4" />
              <span>الأصناف والأسعار</span>
            </router-link>

            <router-link
              to="/purchases"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-300"
            >
              <ShoppingCart class="w-4 h-4" />
              <span>فواتير المشتريات</span>
            </router-link>

            <router-link
              to="/suppliers"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-300"
            >
              <Building2 class="w-4 h-4" />
              <span>الموردون والشركات</span>
            </router-link>

            <router-link
              to="/expenses"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-300"
            >
              <Receipt class="w-4 h-4" />
              <span>المصروفات والنثريات</span>
            </router-link>

            <router-link
              to="/reports"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-300"
            >
              <BarChart3 class="w-4 h-4" />
              <span>التقارير المالية والأرباح</span>
            </router-link>
          </div>
        </aside>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import MobileBottomNav from '../Components/Navigation/MobileBottomNav.vue';
import {
    LayoutDashboard,
    ShoppingCart,
    FileText,
    RotateCcw,
    Layers,
    Package,
    Store as StoreIcon,
    Users,
    Building2,
    Receipt,
    Wallet,
    BarChart3,
    ShieldCheck,
    Activity,
    Sliders,
    LogOut,
    Sun,
    Moon,
    Bell,
    Menu,
    Plus,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const route = useRoute();
const router = useRouter();

const isSidebarOpen = ref(false);
const currentTimeStr = ref('');
let clockInterval = null;

const isSuperAdminPanel = computed(() => {
    return route.path.startsWith('/super-admin');
});

const canAccessSuperAdmin = computed(() => {
    return authStore.user?.roles?.includes('super_admin') || authStore.roles?.includes('super_admin') || authStore.user?.email?.includes('admin');
});

const updateLiveClock = () => {
    const now = new Date();
    const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    const dayName = days[now.getDay()];
    const day = now.getDate();
    const monthName = months[now.getMonth()];
    const year = now.getFullYear();
    const time = now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    currentTimeStr.value = `${dayName}، ${day} ${monthName} ${year} | ${time}`;
};

const toggleTheme = () => {
    appConfigStore.toggleTheme();
};

const handleLogout = async () => {
    await authStore.logout();
    router.push({ name: 'login' });
};

onMounted(() => {
    updateLiveClock();
    clockInterval = setInterval(updateLiveClock, 1000);
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
});
</script>

<style scoped>
.sidebar-drawer-enter-active,
.sidebar-drawer-leave-active {
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.sidebar-drawer-enter-from,
.sidebar-drawer-leave-to {
    transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
