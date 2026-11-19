<template>
  <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <StatCardSkeleton v-for="i in 4" :key="i" />
  </div>

  <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-3 font-tajawal" data-testid="roles-selector">
    <button
      v-for="r in roles"
      :key="r.id"
      type="button"
      @click="$emit('select-role', r)"
      class="min-h-[72px] p-4 rounded-2xl border text-start transition cursor-pointer active:scale-95"
      :class="
        selectedRoleId === r.id
          ? 'bg-theme-primary/15 border-theme-primary ring-2 ring-theme-primary/30 shadow-md'
          : 'bg-white dark:bg-slate-900/90 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
      "
    >
      <div
        class="flex items-center gap-2 text-sm font-bold mb-1"
        :class="selectedRoleId === r.id ? 'text-theme-primary font-black' : 'text-slate-900 dark:text-white'"
      >
        <component :is="roleIcon(r.name)" class="w-4 h-4 shrink-0" />
        <span>{{ r.label }}</span>
      </div>
      <div class="text-[11px] text-slate-500 dark:text-slate-400 num">
        {{
          r.name === 'admin'
            ? $t('roles.full_permissions_badge')
            : $t('roles.active_permissions_count', { count: r.permissions_count })
        }}
      </div>
    </button>
  </div>
</template>

<script setup>
import { Briefcase, Crown, Package, Shield, ShoppingCart } from 'lucide-vue-next';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';

defineProps({
  roles: { type: Array, default: () => [] },
  selectedRoleId: { type: [Number, String], default: null },
  loading: { type: Boolean, default: false },
});

defineEmits(['select-role']);

const ROLE_ICONS = {
  admin: Crown,
  cashier: ShoppingCart,
  storekeeper: Package,
  accountant: Briefcase,
};

const roleIcon = (name) => ROLE_ICONS[name] || Shield;
</script>
