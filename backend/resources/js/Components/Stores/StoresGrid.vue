<template>
  <div class="font-tajawal">
    <!-- 🔄 Skeleton Loading State -->
    <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <CardSkeleton v-for="n in 6" :key="n" />
    </div>

    <!-- 🏬 Stores Cards Grid -->
    <div v-else-if="stores.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="store in stores"
        :key="store.id"
        class="bg-white dark:bg-slate-900/90 border rounded-3xl p-5 transition-all relative overflow-hidden flex flex-col justify-between group shadow-xs dark:shadow-md hover:shadow-lg"
        :class="[
          store.is_main
            ? 'border-theme-primary shadow-theme-primary/10 ring-1 ring-theme-primary/30'
            : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700',
          !store.is_active ? 'opacity-65 grayscale-[40%]' : ''
        ]"
      >
        <!-- Main Branch Ambient Glow -->
        <div v-if="store.is_main" class="absolute -top-12 -right-12 w-32 h-32 bg-theme-light rounded-full blur-2xl pointer-events-none"></div>

        <div>
          <!-- Header Row: Type, Code & Status -->
          <div class="flex items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-xl">
                {{ store.type === 'van' ? '🚚' : (store.type === 'warehouse' ? '🏭' : '🏬') }}
              </span>
              <span class="text-[11px] font-mono font-bold px-2.5 py-0.5 rounded-xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                {{ store.code }}
              </span>
              <span v-if="store.is_main" class="text-[10px] font-black px-2.5 py-0.5 rounded-lg bg-theme-light text-theme-primary border border-theme-border font-tajawal">
                {{ $t('inventory.main_store') }}
              </span>
            </div>

            <!-- Active Status Toggle Badge -->
            <button
              type="button"
              @click="$emit('toggle-active', store)"
              :disabled="store.is_main && store.is_active"
              class="text-[10px] font-bold px-3 py-1 rounded-full border transition-all cursor-pointer font-tajawal flex items-center gap-1.5 min-h-[30px] active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed"
              :class="store.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500 dark:text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-500 dark:text-rose-400'"
              :title="store.is_main ? $t('inventory.cannot_disable_main_store') : (store.is_active ? $t('common.active') : $t('common.inactive'))"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="store.is_active ? 'bg-emerald-500 dark:bg-emerald-400 animate-pulse' : 'bg-rose-500 dark:bg-rose-400'"></span>
              <span>{{ store.is_active ? $t('common.active') : $t('common.inactive') }}</span>
            </button>
          </div>

          <!-- Store Title & Info -->
          <h3 class="text-base font-black text-slate-900 dark:text-white font-tajawal group-hover:text-theme-primary transition-colors">
            {{ store.name }}
          </h3>

          <div class="text-xs text-slate-500 dark:text-slate-400 mt-2 space-y-1.5 font-tajawal">
            <div v-if="store.address" class="flex items-center gap-1.5 text-[11px]">
              <MapPin class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span class="truncate">{{ store.address }}</span>
            </div>
            <div v-if="store.phone" class="flex items-center gap-1.5 text-[11px] font-mono" dir="ltr">
              <Phone class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span>{{ store.phone }}</span>
            </div>
          </div>

          <!-- Statistics Counters Grid -->
          <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-center">
            <div class="p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60">
              <div class="text-[10px] text-slate-400 font-tajawal">{{ $t('inventory.items_count') }}</div>
              <div class="text-sm font-black text-theme-primary font-mono mt-0.5">{{ store.stocks_count || 0 }}</div>
            </div>
            <div class="p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60">
              <div class="text-[10px] text-slate-400 font-tajawal">{{ $t('inventory.invoices_count') }}</div>
              <div class="text-sm font-black text-emerald-500 dark:text-emerald-400 font-mono mt-0.5">{{ store.invoices_count || 0 }}</div>
            </div>
            <div class="p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60">
              <div class="text-[10px] text-slate-400 font-tajawal">{{ $t('inventory.purchases_count') }}</div>
              <div class="text-sm font-black text-cyan-500 dark:text-cyan-400 font-mono mt-0.5">{{ store.purchases_count || 0 }}</div>
            </div>
          </div>

          <!-- Assigned Staff Section -->
          <div class="mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('inventory.assigned_staff') }}:</span>
              <button
                type="button"
                @click="$emit('manage-staff', store)"
                class="text-[11px] text-theme-primary hover:underline font-bold font-tajawal cursor-pointer flex items-center gap-1 min-h-[28px] active:scale-95"
              >
                <Users class="w-3 h-3" />
                <span>{{ $t('inventory.manage_staff') }}</span>
              </button>
            </div>

            <div class="flex flex-wrap gap-1.5 mt-2">
              <template v-if="store.assigned_users && store.assigned_users.length > 0">
                <span
                  v-for="user in store.assigned_users"
                  :key="user.id"
                  class="px-2.5 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 text-[10px] font-bold text-slate-700 dark:text-slate-300 font-tajawal"
                >
                  👤 {{ user.name }}
                </span>
              </template>
              <span v-else class="text-[10px] text-slate-400 font-tajawal italic">
                {{ $t('inventory.no_staff_assigned') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Card Actions Footer -->
        <div class="flex items-center justify-between gap-2 mt-5 pt-3 border-t border-slate-100 dark:border-slate-800">
          <router-link
            :to="{ path: '/stores/stocks', query: { store_id: store.id } }"
            class="min-h-[40px] px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-950/80 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5 font-tajawal shadow-xs active:scale-95"
          >
            <Package class="w-3.5 h-3.5 text-theme-primary" />
            <span>{{ $t('inventory.view_stocks') }}</span>
          </router-link>

          <!-- Unified ActionMenu Dropdown -->
          <ActionMenu :actions="getStoreActions(store)" align="start" />
        </div>
      </div>
    </div>

    <!-- 🚫 Empty State -->
    <EmptyState
      v-else
      :title="$t('inventory.no_stores_found')"
      :description="$t('inventory.add_store_description')"
      :icon="'🏬'"
    >
      <template #action>
        <button
          type="button"
          @click="$emit('create')"
          class="min-h-[44px] px-5 py-2.5 bg-theme-gradient text-white font-black rounded-2xl text-xs font-tajawal shadow-lg shadow-theme-primary/20 cursor-pointer active:scale-95 transition-all"
        >
          {{ $t('inventory.add_first_store') }}
        </button>
      </template>
    </EmptyState>
  </div>
</template>

<script setup>
import { MapPin, Phone, Users, Package, Pencil, Trash2, CheckCircle2, Ban } from 'lucide-vue-next';
import CardSkeleton from '../Common/Skeletons/CardSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import ActionMenu from '../ActionMenu.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

const props = defineProps({
  stores: {
    type: Array,
    default: () => [],
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['create', 'edit', 'delete', 'toggle-active', 'manage-staff']);

const getStoreActions = (store) => {
  const actions = [
    {
      label: t('common.edit'),
      icon: Pencil,
      onClick: () => emit('edit', store),
    },
    {
      label: t('inventory.manage_staff'),
      icon: Users,
      onClick: () => emit('manage-staff', store),
    },
    {
      label: store.is_active ? t('common.inactive') : t('common.active'),
      icon: store.is_active ? Ban : CheckCircle2,
      disabled: store.is_main && store.is_active,
      onClick: () => emit('toggle-active', store),
    },
    {
      label: t('common.delete'),
      icon: Trash2,
      variant: 'danger',
      disabled: !store.can_be_deleted,
      onClick: () => emit('delete', store),
    },
  ];
  return actions;
};
</script>
