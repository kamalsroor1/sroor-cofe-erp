<template>
  <div
    class="flex-1 h-full max-h-full min-h-0 overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950 transition-colors duration-200"
  >
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🔝 TOP HEADER BAR (Clean, Non-Crowded & Responsive)          -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <header
      v-if="!isPosView"
      class="min-h-[3.5rem] sm:min-h-[4rem] py-1.5 sm:py-0 shrink-0 bg-white/95 dark:bg-slate-900/95 border-b border-slate-200 dark:border-slate-800/80 z-40 px-2.5 sm:px-5 flex items-center justify-between shadow-xs select-none backdrop-blur-md mobile-safe-header transition-all duration-200"
    >
      <!-- Right Side (in RTL): Menu Toggle, User Pill, Theme, Notifications, Branch -->
      <div class="flex items-center gap-1.5 sm:gap-3 min-w-0">
        <!-- Mobile Menu Toggle Button -->
        <button
          type="button"
          data-testid="mobile-menu-button"
          @click="isSidebarOpen = true"
          class="min-h-[44px] min-w-[44px] p-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/80 rounded-xl transition lg:hidden cursor-pointer active:scale-95 flex items-center justify-center"
          :title="$t('nav.layout.menu')"
          :aria-label="$t('nav.layout.menu')"
          :aria-expanded="isSidebarOpen"
        >
          <Menu class="w-5 h-5" />
        </button>

        <!-- 👤 User Profile Dropdown Pill -->
        <div class="relative" ref="userDropdownRef">
          <button
            type="button"
            @click="isUserDropdownOpen = !isUserDropdownOpen"
            class="min-h-[38px] flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-xl sm:rounded-2xl text-xs font-bold text-slate-800 dark:text-slate-200 transition cursor-pointer active:scale-95 shadow-2xs"
          >
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
            <!-- Mobile: Short user/role name. Desktop: Full name & role -->
            <span class="font-black truncate max-w-[100px] sm:max-w-[200px]">
              {{ authStore.userName }}
              <span class="hidden sm:inline text-slate-500 dark:text-slate-400 font-normal">
                - {{ authStore.roles?.[0] || $t('nav.admin_role') }}</span
              >
            </span>
            <ChevronDown
              class="w-3 h-3 text-slate-400 transition-transform duration-200 shrink-0"
              :class="{ 'rotate-180': isUserDropdownOpen }"
            />
          </button>

          <!-- User Dropdown Menu -->
          <Transition name="fade">
            <div
              v-if="isUserDropdownOpen"
              class="absolute start-0 top-full mt-2 w-64 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-2 z-50 font-tajawal space-y-1"
            >
              <div
                class="p-3 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 rounded-xl mb-1"
              >
                <div class="font-black text-xs text-slate-900 dark:text-white">
                  {{ authStore.userName }} - {{ authStore.roles?.[0] || $t('nav.admin_role') }}
                </div>
                <div
                  v-if="authStore.user?.phone || authStore.user?.email"
                  class="text-[11px] text-slate-400 font-mono mt-0.5"
                >
                  {{ authStore.user?.phone || authStore.user?.email }}
                </div>
              </div>

              <router-link
                to="/profile"
                @click="isUserDropdownOpen = false"
                class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition"
              >
                <User class="w-4 h-4 text-slate-400" />
                <span>{{ $t('nav.layout.profile_security') }}</span>
              </router-link>

              <router-link
                v-can="'roles.manage'"
                to="/users"
                @click="isUserDropdownOpen = false"
                class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition"
              >
                <Users class="w-4 h-4 text-slate-400" />
                <span>{{ $t('nav.layout.users_permissions') }}</span>
              </router-link>

              <router-link
                v-can="'roles.manage'"
                to="/roles"
                @click="isUserDropdownOpen = false"
                class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition"
              >
                <ShieldCheck class="w-4 h-4 text-slate-400" />
                <span>{{ $t('nav.layout.roles_matrix') }}</span>
              </router-link>

              <router-link
                v-can="'logs.view'"
                to="/activity-logs"
                @click="isUserDropdownOpen = false"
                class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition"
              >
                <FileText class="w-4 h-4 text-slate-400" />
                <span>{{ $t('nav.layout.activity_audit') }}</span>
              </router-link>

              <LanguageSwitcher variant="menu" @switched="isUserDropdownOpen = false" />

              <div class="border-t border-slate-100 dark:border-slate-800/80 pt-1">
                <button
                  type="button"
                  @click="confirmLogout"
                  class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer text-start"
                >
                  <LogOut class="w-4 h-4 text-rose-500" />
                  <span>{{ $t('nav.logout') }}</span>
                </button>
              </div>
            </div>
          </Transition>
        </div>

        <!-- ☀️/🌙 Theme Switcher Button -->
        <button
          type="button"
          @click="toggleTheme"
          class="min-h-[38px] min-w-[38px] px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-700 rounded-xl sm:rounded-2xl text-xs font-bold text-slate-800 text-theme-primary flex items-center justify-center gap-1.5 transition cursor-pointer active:scale-95 shadow-2xs"
          :title="appConfigStore.isDark ? $t('nav.switch_to_light') : $t('nav.switch_to_dark')"
        >
          <Sun v-if="appConfigStore.isDark" class="w-3.5 h-3.5 text-theme-primary" />
          <Moon v-else class="w-3.5 h-3.5 text-indigo-600" />
          <span class="hidden lg:inline">{{
            appConfigStore.isDark ? $t('nav.layout.light_mode') : $t('nav.layout.dark_mode')
          }}</span>
        </button>

        <!-- 🖨️ Desktop Hardware & Thermal Printer Settings (Desktop Mode Only) -->
        <button
          v-if="isDesktop"
          type="button"
          @click="isDesktopSettingsOpen = true"
          class="min-h-[38px] px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 hover:border-theme-primary rounded-xl sm:rounded-2xl text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5 transition cursor-pointer active:scale-95 shadow-2xs"
          :title="$t('settings.desktop_hardware_title')"
        >
          <Printer class="w-3.5 h-3.5 text-theme-primary" />
          <span class="hidden xl:inline font-tajawal">{{ $t('settings.desktop_badge') }}</span>
        </button>

        <NotificationsBell />

        <!-- Store switcher + journal status: full row on lg, compact chip below lg (tablet + mobile) -->
        <StoreShiftSwitcher class="hidden lg:flex" variant="inline" />
        <StoreShiftSwitcher class="flex lg:hidden" variant="chip" />
      </div>

      <!-- Left Side (in RTL): Live Clock & Logout -->
      <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
        <!-- Live Realtime Clock (Desktop only: below 1024px the header keeps just the essentials) -->
        <div
          class="hidden lg:flex text-xs font-bold text-slate-500 dark:text-slate-400 font-mono tracking-tight items-center gap-2"
        >
          <span>{{ currentTimeStr }}</span>
        </div>

        <!-- Logout Button -->
        <button
          type="button"
          @click="confirmLogout"
          class="min-h-[38px] min-w-[38px] p-2 text-slate-500 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer flex items-center justify-center"
          :title="$t('nav.logout')"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </header>

    <!-- 🗂️ 1.5 DESKTOP MULTI-TABS BAR (Visible ONLY on Desktop) -->
    <DesktopTabsBar v-if="isDesktop" />

    <!-- Connectivity banner: always mounted, visible only while offline / server unreachable -->
    <OfflineBanner />

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🖥️ MAIN BODY: DYNAMIC SIDEBAR + STAGE                        -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="flex-1 flex overflow-hidden relative min-h-0">
      <!-- 💻 MODERN DESKTOP SIDEBAR -->
      <!-- 1024-1279px: a 72px rail holds the layout slot and the expanded sidebar floats above the content -->
      <div class="hidden lg:block relative h-full shrink-0" :class="{ 'w-[72px] z-30': isSidebarOverlay }">
        <div
          v-if="isSidebarOverlay && isSidebarOverlayOpen"
          class="fixed inset-0 bg-slate-950/40 z-[-1]"
          aria-hidden="true"
          @click="closeSidebarOverlay"
        ></div>
        <DesktopSidebar
          :is-collapsed="isSidebarCollapsed"
          :overlay="isSidebarOverlay"
          @toggle-collapse="toggleSidebar"
        />
      </div>

      <!-- Main Content Stage -->
      <main
        class="flex-1 h-full min-h-0 bg-slate-50 dark:bg-slate-950"
        :class="
          isPosView ? 'p-0 overflow-hidden pb-0' : 'overflow-y-auto p-3 sm:p-6 lg:p-8 pb-24 lg:pb-8 custom-scrollbar'
        "
      >
        <slot />
      </main>

      <!-- Fixed Mobile Bottom Navigation Bar (the POS has its own sticky cart bar, the nav would cover Pay) -->
      <MobileBottomNav v-if="!isPosView" @open-drawer="isSidebarOpen = true" />
    </div>

    <!-- 📱 DYNAMIC MOBILE DRAWER (Complete Mirror of Full ERP System) -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="isSidebarOpen"
          @click="isSidebarOpen = false"
          class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs z-[9998] lg:hidden"
        ></div>
      </Transition>

      <Transition name="sidebar-drawer">
        <aside
          v-if="isSidebarOpen"
          role="dialog"
          aria-modal="true"
          :aria-label="$t('nav.layout.menu')"
          data-testid="mobile-drawer"
          class="fixed inset-y-0 start-0 w-[88vw] max-w-[360px] bg-white dark:bg-slate-950 border-e border-slate-200 dark:border-slate-800 flex flex-col shadow-2xl z-[9999] font-tajawal lg:hidden"
        >
          <!-- Drawer Header -->
          <div
            class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50 mobile-safe-header"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <img
                v-if="appConfigStore.companyLogo"
                :src="appConfigStore.companyLogo"
                :alt="appConfigStore.companyName"
                class="w-9 h-9 rounded-xl object-contain shrink-0"
              />
              <BrandMark v-else variant="app-icon" :size="36" class="rounded-xl shadow-sm" />
              <div class="min-w-0">
                <span class="font-black text-sm text-slate-900 dark:text-white truncate block">{{
                  appConfigStore.companyName
                }}</span>
                <span class="text-[10px] text-slate-400 font-bold truncate block"
                  >{{ authStore.userName }} ({{ authStore.roles?.[0] || $t('nav.admin_role') }})</span
                >
              </div>
            </div>
            <button
              ref="drawerCloseRef"
              type="button"
              data-testid="mobile-drawer-close"
              :aria-label="$t('common.close')"
              @click="isSidebarOpen = false"
              class="min-h-[44px] min-w-[44px] p-2 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center justify-center font-bold cursor-pointer"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Store switcher + journal status -->
          <div class="p-3 border-b border-slate-200 dark:border-slate-800">
            <StoreShiftSwitcher variant="drawer" @navigate="isSidebarOpen = false" />
          </div>

          <!-- Drawer Navigation Items -->
          <div class="flex-1 overflow-y-auto p-3 space-y-3 custom-scrollbar">
            <!-- 🌟 Quick Action Button: POS -->
            <router-link
              v-if="canAccessPath('/pos') && !activeMobileSection"
              to="/pos"
              @click="isSidebarOpen = false"
              class="flex items-center justify-center gap-2.5 w-full py-3 px-4 rounded-2xl text-slate-950 font-black text-xs shadow-lg transition active:scale-95 cursor-pointer"
              :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
            >
              <Plus class="w-4 h-4 stroke-[3]" />
              <span>{{ $t('nav.layout.quick_pos') }}</span>
            </router-link>

            <!-- 📁 LEVEL 1: MODULES CATEGORIES HUB (When No Category Selected) -->
            <div v-if="!activeMobileSection" class="space-y-2.5">
              <div class="px-1 text-[11px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                {{ $t('nav.layout.modules_heading') }}
              </div>

              <!-- Module Cards Grid/List -->
              <div class="space-y-2">
                <template v-for="section in navigationSections" :key="'mob-sec-' + section.key">
                  <!-- Direct Dashboard Card -->
                  <router-link
                    v-if="section.isDirect"
                    :to="section.directPath || '/'"
                    @click="isSidebarOpen = false"
                    class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/80 hover:border-theme-primary flex items-center justify-between transition-all active:scale-[0.98] cursor-pointer shadow-2xs group"
                  >
                    <div class="flex items-center gap-3 min-w-0">
                      <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                        :class="section.iconBg"
                      >
                        <component :is="section.icon" class="w-5 h-5" />
                      </div>
                      <div class="min-w-0">
                        <div
                          class="text-xs font-black text-slate-900 dark:text-white group-hover:text-theme-primary transition truncate"
                        >
                          {{ section.title }}
                        </div>
                        <div class="text-[10px] text-slate-400 font-bold truncate mt-0.5">
                          {{ section.subtitle || $t('nav.dashboard_short') }}
                        </div>
                      </div>
                    </div>
                    <ChevronLeft
                      class="ltr:rotate-180 w-4 h-4 text-slate-400 group-hover:text-theme-primary transition shrink-0"
                    />
                  </router-link>

                  <!-- Category Card with Drill-Down Sub-menu -->
                  <button
                    v-else
                    type="button"
                    @click="!section.comingSoon && (activeMobileSection = section)"
                    class="w-full p-3.5 rounded-2xl border flex items-center justify-between transition-all select-none shadow-2xs group text-start"
                    :class="[
                      section.comingSoon
                        ? 'bg-slate-100/60 dark:bg-slate-900/40 border-slate-200/50 dark:border-slate-800/40 opacity-50 cursor-not-allowed'
                        : 'bg-slate-50 dark:bg-slate-900/80 border-slate-200 dark:border-slate-800/80 hover:border-theme-primary cursor-pointer active:scale-[0.98]',
                    ]"
                  >
                    <div class="flex items-center gap-3 min-w-0">
                      <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                        :class="section.iconBg"
                      >
                        <component :is="section.icon" class="w-5 h-5" />
                      </div>
                      <div class="min-w-0">
                        <div
                          class="text-xs font-black text-slate-900 dark:text-white transition truncate"
                          :class="section.comingSoon ? '' : 'group-hover:text-theme-primary'"
                        >
                          {{ section.title }}
                        </div>
                        <div
                          class="text-[10px] font-bold truncate mt-0.5"
                          :class="section.comingSoon ? 'text-amber-500 dark:text-amber-400' : 'text-slate-400'"
                        >
                          {{
                            section.comingSoon
                              ? $t('nav.coming_soon')
                              : section.subtitle || $t('nav.layout.links_count', { count: section.items?.length || 0 })
                          }}
                        </div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <span
                        v-if="section.comingSoon"
                        class="px-2 py-0.5 rounded-lg bg-amber-500/15 text-[10px] font-bold text-amber-600 dark:text-amber-400 border border-amber-500/20"
                      >
                        {{ $t('nav.coming_soon') }}
                      </span>
                      <template v-else>
                        <span
                          class="px-2 py-0.5 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-[10px] font-mono text-slate-600 dark:text-slate-400 font-bold"
                        >
                          {{ $t('nav.layout.links_count', { count: section.items?.length || 0 }) }}
                        </span>
                        <ChevronLeft
                          class="ltr:rotate-180 w-4 h-4 text-slate-400 group-hover:text-theme-primary group-hover:-translate-x-0.5 ltr:group-hover:translate-x-0.5 transition"
                        />
                      </template>
                    </div>
                  </button>
                </template>
              </div>
            </div>

            <!-- 📂 LEVEL 2: DRILL-DOWN SUB-MENU (When Category is Active) -->
            <div
              v-else
              class="space-y-3 animate-in fade-in rtl:slide-in-from-left ltr:slide-in-from-right duration-200"
            >
              <!-- Back to Modules Bar -->
              <div
                class="p-3 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between"
              >
                <button
                  type="button"
                  @click="activeMobileSection = null"
                  class="flex items-center gap-1.5 text-xs font-black text-theme-primary hover:opacity-80 transition cursor-pointer active:scale-95"
                >
                  <ChevronRight class="ltr:rotate-180 w-4 h-4 stroke-[3]" />
                  <span>{{ $t('nav.layout.back_to_sections') }}</span>
                </button>

                <div class="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                  <component :is="activeMobileSection.icon" class="w-4 h-4 text-theme-primary" />
                  <span>{{ activeMobileSection.title }}</span>
                </div>
              </div>

              <!-- Module Sub-Links List -->
              <div class="space-y-1.5 pt-1">
                <router-link
                  v-for="item in activeMobileSection.items"
                  :key="'drill-mob-' + item.key"
                  :to="item.path"
                  @click="
                    isSidebarOpen = false;
                    activeMobileSection = null;
                  "
                  class="flex items-center justify-between p-3.5 rounded-2xl text-xs font-bold transition-all border shadow-2xs active:scale-[0.98] cursor-pointer"
                  :class="
                    isItemActive(item)
                      ? 'font-black border-theme-primary shadow-sm'
                      : 'bg-white dark:bg-slate-900/80 text-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-800 hover:border-theme-primary'
                  "
                  :style="
                    isItemActive(item)
                      ? {
                          color: 'var(--color-primary, #f59e0b)',
                          borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                          backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))',
                        }
                      : {}
                  "
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div
                      class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                      :class="
                        isItemActive(item)
                          ? 'text-slate-950'
                          : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'
                      "
                      :style="isItemActive(item) ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
                    >
                      <component :is="item.icon" class="w-4 h-4 stroke-[2.4]" />
                    </div>
                    <span class="truncate">{{ item.title }}</span>
                  </div>
                  <ChevronLeft class="ltr:rotate-180 w-4 h-4 opacity-40 shrink-0" />
                </router-link>
              </div>
            </div>
          </div>

          <!-- Drawer Footer Version & Quick Update Check -->
          <div
            class="p-3.5 border-t border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50 dark:bg-slate-900/90 flex items-center justify-between text-xs font-mono"
          >
            <div class="flex items-center gap-2">
              <span class="font-tajawal font-bold text-slate-700 dark:text-slate-300">{{
                $t('nav.layout.current_version')
              }}</span>
              <span class="px-2 py-0.5 rounded-lg bg-theme-primary/10 text-theme-primary font-black font-mono"
                >v{{ currentVersionName }}</span
              >
            </div>
            <button
              type="button"
              @click="checkForUpdates(true)"
              class="px-3 py-1 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl text-[11px] font-bold font-tajawal transition flex items-center gap-1.5 shadow-2xs cursor-pointer active:scale-95"
            >
              <Rocket class="w-3.5 h-3.5" />
              <span>{{ $t('nav.layout.check_updates') }}</span>
            </button>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- 🖨️ Desktop Hardware Settings Modal -->
    <DesktopPrinterSettingsModal
      v-if="isDesktop"
      :show="isDesktopSettingsOpen"
      @close="isDesktopSettingsOpen = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import { useRoutePermission } from '../Composables/useRoutePermission';
import { useNavigation } from '../Composables/useNavigation';
import { useAppUpdate } from '../Composables/useAppUpdate';
import { useDesktopHardware } from '../Composables/useDesktopHardware';
import { useFormatters } from '../Composables/useFormatters';
import { useTabsStore } from '../stores/tabs';
import { useSidebarState } from '../Composables/useSidebarState';
import LanguageSwitcher from '../Components/Common/LanguageSwitcher.vue';
import DesktopPrinterSettingsModal from '../Components/Common/DesktopPrinterSettingsModal.vue';
import DesktopTabsBar from '../Components/Navigation/DesktopTabsBar.vue';
import MobileBottomNav from '../Components/Navigation/MobileBottomNav.vue';
import DesktopSidebar from '../Components/Navigation/DesktopSidebar.vue';
import BrandMark from '../Components/Brand/BrandMark.vue';
import OfflineBanner from '../Components/Layout/OfflineBanner.vue';
import NotificationsBell from '../Components/Layout/NotificationsBell.vue';
import StoreShiftSwitcher from '../Components/Layout/StoreShiftSwitcher.vue';
import Swal from 'sweetalert2';
import { trans } from '../helpers/trans';
import {
  LogOut,
  Sun,
  Moon,
  Menu,
  Plus,
  ChevronDown,
  ChevronRight,
  ChevronLeft,
  User,
  Users,
  ShieldCheck,
  FileText,
  Printer,
  X,
  Rocket,
} from 'lucide-vue-next';

const { currentVersionName, checkForUpdates } = useAppUpdate();
const { isDesktop } = useDesktopHardware();
const tabsStore = useTabsStore();
const isDesktopSettingsOpen = ref(false);
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const { formatDate, formatTime } = useFormatters();
const { canAccessPath } = useRoutePermission();
const { navigationSections, isItemActive } = useNavigation();

const route = useRoute();
const router = useRouter();

// 🗂️ Sync route with desktop multi-tabs store
watch(
  () => route.fullPath,
  () => {
    if (isDesktop.value) {
      tabsStore.addTab(route);
    }
  },
  { immediate: true }
);

const isSidebarOpen = ref(false);
const activeMobileSection = ref(null);
const drawerCloseRef = ref(null);
let drawerReturnFocusEl = null;

const isPosView = computed(() => route.path === '/pos' || route.path.startsWith('/pos/'));

const {
  isDesktopUp,
  isCollapsed: isSidebarCollapsed,
  isOverlay: isSidebarOverlay,
  isOverlayOpen: isSidebarOverlayOpen,
  toggle: toggleSidebar,
  closeOverlay: closeSidebarOverlay,
} = useSidebarState(isPosView);

watch(
  () => route.path,
  () => {
    isSidebarOpen.value = false;
    activeMobileSection.value = null;
  },
  { immediate: true }
);

// The drawer only exists below 1024px; it must not stay open after a rotate/resize into desktop width.
watch(isDesktopUp, (isDesktopWidth) => {
  if (isDesktopWidth) isSidebarOpen.value = false;
});

// Drawer a11y: focus moves into it on open and returns to whatever opened it (header button or bottom nav).
watch(isSidebarOpen, async (isOpen) => {
  if (isOpen) {
    drawerReturnFocusEl = document.activeElement;
    await nextTick();
    drawerCloseRef.value?.focus();
  } else if (drawerReturnFocusEl) {
    const target = drawerReturnFocusEl;
    drawerReturnFocusEl = null;
    if (target.isConnected) target.focus();
  }
});

const handleDrawerEscape = (event) => {
  if (event.key === 'Escape' && isSidebarOpen.value) isSidebarOpen.value = false;
};

const isUserDropdownOpen = ref(false);
const userDropdownRef = ref(null);

const currentTimeStr = ref('');
let clockInterval = null;

const updateLiveClock = () => {
  const now = new Date();
  const date = formatDate(now, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  const time = formatTime(now, { second: '2-digit', hour12: true });
  currentTimeStr.value = `${date} | ${time}`;
};

const toggleTheme = () => {
  const nextTheme = appConfigStore.isDark ? 'light' : 'dark';
  appConfigStore.setTheme(nextTheme);
};

const confirmLogout = async () => {
  isUserDropdownOpen.value = false;
  const result = await Swal.fire({
    title: trans('nav.layout.logout_confirm_title'),
    text: trans('nav.layout.logout_confirm_text'),
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: trans('nav.layout.logout_confirm_btn'),
    cancelButtonText: trans('common.cancel'),
    confirmButtonColor: '#e11d48',
    cancelButtonColor: '#64748b',
  });

  if (result.isConfirmed) {
    await authStore.logout();
    router.push({ name: 'login' });
  }
};

// Close dropdowns on outside click
const handleOutsideClick = (e) => {
  if (userDropdownRef.value && !userDropdownRef.value.contains(e.target)) {
    isUserDropdownOpen.value = false;
  }
};

onMounted(() => {
  updateLiveClock();
  clockInterval = setInterval(updateLiveClock, 1000);
  document.addEventListener('click', handleOutsideClick);
  document.addEventListener('keydown', handleDrawerEscape);
});

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval);
  document.removeEventListener('click', handleOutsideClick);
  document.removeEventListener('keydown', handleDrawerEscape);
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
[dir='ltr'] .sidebar-drawer-enter-from,
[dir='ltr'] .sidebar-drawer-leave-to {
  transform: translateX(-100%);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
  transition:
    opacity 0.15s cubic-bezier(0.16, 1, 0.3, 1),
    transform 0.15s cubic-bezier(0.16, 1, 0.3, 1);
}
.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
  opacity: 0;
  transform: translate(6px, -50%);
}
</style>
