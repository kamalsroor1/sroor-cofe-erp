<template>
  <div
    class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl overflow-hidden font-tajawal"
  >
    <DataTable
      data-testid="users-table"
      :rows="users"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      @page-change="$emit('page-change', $event)"
      :empty-title="$t('users.no_users_found')"
      :empty-message="$t('users.no_users_hint')"
    >
      <template #cell-name="{ row }">
        <div class="font-sans font-bold text-slate-900 dark:text-white flex items-center gap-3">
          <div
            class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 flex items-center justify-center text-theme-primary font-bold text-sm"
          >
            {{ row.name.charAt(0) }}
          </div>
          <div>
            <div class="font-tajawal text-xs text-slate-900 dark:text-white font-bold">{{ row.name }}</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
              {{ row.email || $t('users.no_email') }}
            </div>
          </div>
        </div>
      </template>

      <template #cell-phone="{ row }">
        <span class="text-slate-700 dark:text-slate-300 font-mono" dir="ltr">{{ row.phone }}</span>
      </template>

      <template #cell-primary_role="{ row }">
        <span
          class="px-2.5 py-1 rounded-full text-[11px] font-bold border font-tajawal"
          :class="getRoleBadgeClass(row.primary_role)"
        >
          {{ getRoleLabel(row.primary_role) }}
        </span>
      </template>

      <template #cell-default_store_name="{ row }">
        <span class="font-sans text-slate-700 dark:text-slate-300 font-tajawal">
          {{ row.default_store_name || $t('users.no_store_assigned') }}
        </span>
      </template>

      <template #cell-is_active="{ row }">
        <button
          type="button"
          @click="$emit('toggle-active', row)"
          data-testid="action-toggle-active"
          class="min-h-[44px] min-w-[44px] lg:min-h-[28px] lg:min-w-0 px-3 py-1 rounded-full text-[11px] font-bold border transition cursor-pointer font-tajawal active:scale-95"
          :class="
            row.is_active
              ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'
              : 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20'
          "
        >
          {{ row.is_active ? $t('users.status_active_badge') : $t('users.status_inactive_badge') }}
        </button>
      </template>

      <template #cell-actions="{ row }">
        <div class="flex items-center justify-end gap-2 font-sans">
          <button
            type="button"
            @click="$emit('edit', row)"
            data-testid="action-edit"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-theme-primary border border-slate-300 dark:border-slate-700 rounded-xl transition cursor-pointer active:scale-95 flex items-center justify-center"
            :title="$t('common.edit')"
          >
            <Edit2 class="w-3.5 h-3.5" />
          </button>
          <button
            type="button"
            @click="$emit('delete', row)"
            data-testid="action-delete"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-2 bg-slate-100 hover:bg-rose-50 dark:bg-slate-800 dark:hover:bg-rose-950/40 text-rose-500 dark:text-rose-400 border border-slate-300 dark:border-slate-700 hover:border-rose-300 dark:hover:border-rose-800 rounded-xl transition cursor-pointer active:scale-95 flex items-center justify-center"
            :title="$t('common.delete')"
          >
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Edit2, Trash2 } from 'lucide-vue-next';
import DataTable from '@/Components/Common/DataTable.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  users: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, per_page: 15 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['edit', 'delete', 'toggle-active', 'page-change']);

const columns = computed(() => [
  { key: 'name', label: t('users.employee_col') },
  { key: 'phone', label: t('users.phone_col') },
  { key: 'primary_role', label: t('users.role_col') },
  { key: 'default_store_name', label: t('users.default_store_col') },
  { key: 'is_active', label: t('users.active_status_col') },
  { key: 'actions', label: t('common.actions') },
]);

const getRoleLabel = (role) => {
  switch (role) {
    case 'admin':
      return t('users.role_admin');
    case 'cashier':
      return t('users.role_cashier');
    case 'storekeeper':
      return t('users.role_storekeeper');
    case 'accountant':
      return t('users.role_accountant');
    default:
      return role;
  }
};

const getRoleBadgeClass = (role) => {
  switch (role) {
    case 'admin':
      return 'bg-purple-500/10 border-purple-500/30 text-purple-600 dark:text-purple-400';
    case 'cashier':
      return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400';
    case 'storekeeper':
      return 'bg-theme-light border-theme-border text-theme-primary';
    case 'accountant':
      return 'bg-cyan-500/10 border-cyan-500/30 text-cyan-600 dark:text-cyan-400';
    default:
      return 'bg-slate-500/10 border-slate-500/30 text-slate-600 dark:text-slate-400';
  }
};
</script>
