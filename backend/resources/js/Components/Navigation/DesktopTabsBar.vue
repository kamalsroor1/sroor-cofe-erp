<template>
  <nav
    class="h-9 w-full shrink-0 bg-slate-200/90 dark:bg-slate-950 border-b border-slate-300/80 dark:border-slate-800 flex items-center justify-between px-2 select-none text-xs font-tajawal z-30 shadow-2xs"
    dir="rtl"
  >
    <!-- 🗂️ Scrollable Tabs List Container -->
    <div
      ref="tabsContainerRef"
      @wheel.prevent="handleWheelScroll"
      class="flex-1 flex items-center gap-1 overflow-x-auto no-scrollbar py-0.5"
    >
      <div
        v-for="(tab, index) in tabsStore.tabs"
        :key="tab.id"
        @click="tabsStore.selectTab(tab)"
        @contextmenu.prevent="openContextMenu($event, tab)"
        class="group relative flex items-center gap-1.5 h-8 px-3 rounded-t-xl transition-all duration-150 cursor-pointer shrink-0 text-xs font-bold border-t border-x"
        :class="tabsStore.activeTabId === tab.id
          ? 'bg-slate-50 dark:bg-slate-900 text-slate-950 dark:text-white border-slate-300 dark:border-slate-700 font-black shadow-xs'
          : 'bg-slate-200/60 dark:bg-slate-950/60 hover:bg-slate-100 dark:hover:bg-slate-900/60 text-slate-600 dark:text-slate-400 border-transparent'"
        :title="tab.title"
      >
        <!-- Active Bottom Glowing Indicator -->
        <span
          v-if="tabsStore.activeTabId === tab.id"
          class="absolute bottom-0 inset-x-2 h-0.5 bg-theme-primary rounded-full"
        ></span>

        <!-- Tab Icon -->
        <DynamicIcon :name="tab.icon" class="w-3.5 h-3.5 shrink-0 text-slate-500 group-hover:text-theme-primary transition-colors" />

        <!-- Tab Title -->
        <span class="truncate max-w-[130px] sm:max-w-[160px]">{{ tab.title }}</span>

        <!-- Close Tab Button (x) -->
        <button
          v-if="tab.closable"
          type="button"
          @click.stop="tabsStore.removeTab(tab.id)"
          class="w-4 h-4 rounded-md text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 flex items-center justify-center transition opacity-60 group-hover:opacity-100 ms-1 cursor-pointer"
          :title="$t('common.close_tab')"
        >
          <X class="w-3 h-3" />
        </button>
      </div>

      <!-- Add New Tab Button (+) -->
      <button
        type="button"
        @click="openNewDashboardTab"
        class="w-7 h-7 rounded-lg hover:bg-slate-300 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition cursor-pointer shrink-0 ms-1"
        :title="$t('common.new_tab')"
      >
        <Plus class="w-4 h-4" />
      </button>
    </div>

    <!-- ⚡ Quick Tab Count & Global Actions -->
    <div class="hidden sm:flex items-center gap-1 shrink-0 ps-2 text-[11px] font-mono text-slate-400">
      <span class="px-1.5 py-0.5 rounded bg-slate-300/60 dark:bg-slate-900 text-[10px] font-black">
        {{ tabsStore.tabs.length }}/{{ tabsStore.maxTabs }}
      </span>
      <button
        v-if="tabsStore.tabs.length > 1"
        type="button"
        @click="tabsStore.closeAllTabs"
        class="p-1 rounded hover:bg-rose-500/10 hover:text-rose-500 transition cursor-pointer"
        :title="$t('common.close_all_tabs')"
      >
        <XCircle class="w-3.5 h-3.5" />
      </button>
    </div>

    <!-- 📋 Right-Click Context Menu -->
    <Teleport to="body">
      <div
        v-if="contextMenu.visible"
        :style="{ top: `${contextMenu.y}px`, left: `${contextMenu.x}px` }"
        class="fixed z-[999999] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-1.5 w-48 text-xs font-tajawal space-y-0.5 animate-in fade-in zoom-in-95 duration-100"
        dir="rtl"
        @click.stop
      >
        <button
          type="button"
          @click="handleContextRefresh"
          class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold transition text-start cursor-pointer"
        >
          <RotateCw class="w-3.5 h-3.5 text-cyan-500" />
          <span>{{ $t('common.refresh_tab') }}</span>
        </button>

        <button
          v-if="contextMenu.tab?.closable"
          type="button"
          @click="handleContextClose"
          class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl hover:bg-rose-500/10 text-rose-600 dark:text-rose-400 font-bold transition text-start cursor-pointer"
        >
          <X class="w-3.5 h-3.5" />
          <span>{{ $t('common.close_tab') }}</span>
        </button>

        <button
          v-if="tabsStore.tabs.length > 2"
          type="button"
          @click="handleContextCloseOthers"
          class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold transition text-start cursor-pointer"
        >
          <MinusCircle class="w-3.5 h-3.5 text-amber-500" />
          <span>{{ $t('common.close_other_tabs') }}</span>
        </button>

        <button
          v-if="tabsStore.tabs.length > 1"
          type="button"
          @click="handleContextCloseAll"
          class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold transition text-start cursor-pointer border-t border-slate-100 dark:border-slate-800 mt-1 pt-1.5"
        >
          <XCircle class="w-3.5 h-3.5 text-slate-400" />
          <span>{{ $t('common.close_all_tabs') }}</span>
        </button>
      </div>
    </Teleport>
  </nav>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { X, Plus, RotateCw, MinusCircle, XCircle } from 'lucide-vue-next';
import { useTabsStore } from '../../stores/tabs';
import DynamicIcon from '../Common/DynamicIcon.vue';

const router = useRouter();
const tabsStore = useTabsStore();

const tabsContainerRef = ref(null);

// Context Menu State
const contextMenu = reactive({
  visible: false,
  x: 0,
  y: 0,
  tab: null,
});

const openContextMenu = (e, tab) => {
  contextMenu.x = e.clientX;
  contextMenu.y = e.clientY;
  contextMenu.tab = tab;
  contextMenu.visible = true;
};

const closeContextMenu = () => {
  contextMenu.visible = false;
  contextMenu.tab = null;
};

const handleContextRefresh = () => {
  if (contextMenu.tab) {
    tabsStore.refreshTab(contextMenu.tab.id);
  }
  closeContextMenu();
};

const handleContextClose = () => {
  if (contextMenu.tab) {
    tabsStore.removeTab(contextMenu.tab.id);
  }
  closeContextMenu();
};

const handleContextCloseOthers = () => {
  if (contextMenu.tab) {
    tabsStore.closeOtherTabs(contextMenu.tab.id);
  }
  closeContextMenu();
};

const handleContextCloseAll = () => {
  tabsStore.closeAllTabs();
  closeContextMenu();
};

const handleWheelScroll = (e) => {
  if (tabsContainerRef.value) {
    tabsContainerRef.value.scrollLeft += e.deltaY;
  }
};

const openNewDashboardTab = () => {
  router.push('/');
};

// Global Keyboard Shortcuts (Ctrl+W to close active tab, Ctrl+Tab to switch)
const handleKeydown = (e) => {
  // Ctrl + W: Close Active Tab
  if (e.ctrlKey && e.key.toLowerCase() === 'w') {
    const currentTab = tabsStore.tabs.find((t) => t.id === tabsStore.activeTabId);
    if (currentTab && currentTab.closable) {
      e.preventDefault();
      tabsStore.removeTab(currentTab.id);
    }
  }

  // Ctrl + Tab: Switch Next Tab
  if (e.ctrlKey && e.key === 'Tab' && !e.shiftKey) {
    e.preventDefault();
    const currentIndex = tabsStore.tabs.findIndex((t) => t.id === tabsStore.activeTabId);
    if (currentIndex !== -1) {
      const nextIndex = (currentIndex + 1) % tabsStore.tabs.length;
      tabsStore.selectTab(tabsStore.tabs[nextIndex]);
    }
  }

  // Ctrl + Shift + Tab: Switch Previous Tab
  if (e.ctrlKey && e.shiftKey && e.key === 'Tab') {
    e.preventDefault();
    const currentIndex = tabsStore.tabs.findIndex((t) => t.id === tabsStore.activeTabId);
    if (currentIndex !== -1) {
      const prevIndex = (currentIndex - 1 + tabsStore.tabs.length) % tabsStore.tabs.length;
      tabsStore.selectTab(tabsStore.tabs[prevIndex]);
    }
  }
};

onMounted(() => {
  window.addEventListener('click', closeContextMenu);
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('click', closeContextMenu);
  window.removeEventListener('keydown', handleKeydown);
});
</script>
