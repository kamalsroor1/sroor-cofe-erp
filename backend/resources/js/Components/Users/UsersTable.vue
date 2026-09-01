<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl overflow-hidden font-tajawal">
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="6" />
    </div>

    <div v-else-if="users.length > 0">
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs">
          <thead class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 font-bold font-tajawal">
            <tr>
              <th class="p-4 text-start">{{ $t('users.employee_col') }}</th>
              <th class="p-4 text-start">{{ $t('users.phone_col') }}</th>
              <th class="p-4 text-start">{{ $t('users.role_col') }}</th>
              <th class="p-4 text-start">{{ $t('users.default_store_col') }}</th>
              <th class="p-4 text-center">{{ $t('users.active_status_col') }}</th>
              <th class="p-4 text-end">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-mono">
            <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
              <td class="p-4 font-sans font-bold text-slate-900 dark:text-white flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 flex items-center justify-center text-theme-primary font-bold text-sm">
                  {{ u.name.charAt(0) }}
                </div>
                <div>
                  <div class="font-tajawal text-xs text-slate-900 dark:text-white font-bold">{{ u.name }}</div>
                  <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ u.email || $t('users.no_email') }}</div>
                </div>
              </td>

              <td class="p-4 text-slate-700 dark:text-slate-300 font-mono" dir="ltr">{{ u.phone }}</td>

              <td class="p-4 font-sans">
                <span
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold border font-tajawal"
                  :class="getRoleBadgeClass(u.primary_role)"
                >
                  {{ getRoleLabel(u.primary_role) }}
                </span>
              </td>

              <td class="p-4 font-sans text-slate-700 dark:text-slate-300 font-tajawal">{{ u.default_store_name || $t('users.no_store_assigned') }}</td>

              <td class="p-4 text-center font-sans">
                <button
                  type="button"
                  @click="$emit('toggle-active', u)"
                  class="min-h-[28px] px-3 py-1 rounded-full text-[11px] font-bold border transition cursor-pointer font-tajawal active:scale-95"
                  :class="u.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20' : 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20'"
                >
                  {{ u.is_active ? $t('users.status_active_badge') : $t('users.status_inactive_badge') }}
                </button>
              </td>

              <td class="p-4 text-end font-sans">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    @click="$emit('edit', u)"
                    class="p-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-theme-primary border border-slate-300 dark:border-slate-700 rounded-xl transition cursor-pointer active:scale-95"
                    :title="$t('common.edit')"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="$emit('delete', u)"
                    class="p-2 bg-slate-100 hover:bg-rose-50 dark:bg-slate-800 dark:hover:bg-rose-950/40 text-rose-500 dark:text-rose-400 border border-slate-300 dark:border-slate-700 hover:border-rose-300 dark:hover:border-rose-800 rounded-xl transition cursor-pointer active:scale-95"
                    :title="$t('common.delete')"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Tactile Cards -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="u in users"
          :key="u.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-3"
        >
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 flex items-center justify-center text-theme-primary font-bold text-sm">
                {{ u.name.charAt(0) }}
              </div>
              <div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ u.name }}</h4>
                <span class="text-xs font-mono text-slate-500 dark:text-slate-400" dir="ltr">{{ u.phone }}</span>
              </div>
            </div>

            <button
              type="button"
              @click="$emit('toggle-active', u)"
              class="min-h-[32px] px-3 py-1 rounded-full text-[10px] font-bold border transition cursor-pointer active:scale-95"
              :class="u.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-500'"
            >
              {{ u.is_active ? $t('users.status_active_badge') : $t('users.status_inactive_badge') }}
            </button>
          </div>

          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
              :class="getRoleBadgeClass(u.primary_role)"
            >
              {{ getRoleLabel(u.primary_role) }}
            </span>
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="$emit('edit', u)"
                class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-theme-primary rounded-lg text-xs font-bold transition border border-slate-200 dark:border-slate-700"
              >
                {{ $t('common.edit') }}
              </button>
              <button
                type="button"
                @click="$emit('delete', u)"
                class="px-3 py-1.5 bg-rose-50 dark:bg-rose-950/40 text-rose-500 rounded-lg text-xs font-bold transition border border-rose-200 dark:border-rose-800"
              >
                {{ $t('common.delete') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 font-mono">
        <span class="font-tajawal">{{ $t('users.total_users_count', { count: pagination.total }) }}</span>
        <div class="flex items-center gap-2 font-sans font-tajawal">
          <button
            type="button"
            :disabled="pagination.current_page === 1"
            @click="$emit('page-change', pagination.current_page - 1)"
            class="min-h-[36px] px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-50 border border-slate-300 dark:border-slate-700 rounded-xl cursor-pointer active:scale-95"
          >
            {{ $t('common.previous') }}
          </button>
          <span class="font-mono">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button
            type="button"
            :disabled="pagination.current_page === pagination.last_page"
            @click="$emit('page-change', pagination.current_page + 1)"
            class="min-h-[36px] px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-50 border border-slate-300 dark:border-slate-700 rounded-xl cursor-pointer active:scale-95"
          >
            {{ $t('common.next') }}
          </button>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('users.no_users_found')"
      :description="$t('users.no_users_hint')"
      icon="👥"
    />
  </div>
</template>

<script setup>
import { Edit2, Trash2 } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  users: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, per_page: 15 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['edit', 'delete', 'toggle-active', 'page-change']);

const getRoleLabel = (role) => {
  switch (role) {
    case 'admin': return t('users.role_admin');
    case 'cashier': return t('users.role_cashier');
    case 'storekeeper': return t('users.role_storekeeper');
    case 'accountant': return t('users.role_accountant');
    default: return role;
  }
};

const getRoleBadgeClass = (role) => {
  switch (role) {
    case 'admin': return 'bg-purple-500/10 border-purple-500/30 text-purple-600 dark:text-purple-400';
    case 'cashier': return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400';
    case 'storekeeper': return 'bg-theme-light border-theme-border text-theme-primary';
    case 'accountant': return 'bg-cyan-500/10 border-cyan-500/30 text-cyan-600 dark:text-cyan-400';
    default: return 'bg-slate-500/10 border-slate-500/30 text-slate-600 dark:text-slate-400';
  }
};
</script>
