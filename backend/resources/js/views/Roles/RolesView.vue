<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/90 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
            <ShieldCheck class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ $t('roles.title') }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('roles.subtitle') }}</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <router-link
            to="/users"
            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-xl shadow-xs flex items-center gap-2 transition"
          >
            <Users class="w-4 h-4 text-amber-400" />
            <span>{{ $t('roles.users_and_employees') }}</span>
          </router-link>

          <button
            v-if="selectedRole?.name !== 'admin'"
            @click="savePermissions"
            :disabled="isSaving"
            class="px-5 py-2.5 bg-theme-gradient text-white shadow-theme-primary font-black text-xs rounded-xl shadow-lg shadow-theme-primary flex items-center gap-2 transition cursor-pointer disabled:opacity-50"
          >
            <Check class="w-4 h-4" />
            <span>{{ isSaving ? $t('common.loading') : $t('profile.save_changes') }}</span>
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-16 text-center">
        <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('roles.loading_roles_matrix') }}</p>
      </div>

      <div v-else class="space-y-6">
        <!-- Roles Selector Tabs -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <button
            v-for="r in roles"
            :key="r.id"
            @click="selectRole(r)"
            class="p-4 rounded-2xl border text-start transition cursor-pointer"
            :class="selectedRole?.id === r.id ? 'bg-amber-500/15 border-amber-500 ring-2 ring-amber-500/30 shadow-md' : 'bg-white dark:bg-slate-900/90 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'"
          >
            <div class="text-sm font-bold mb-1" :class="selectedRole?.id === r.id ? 'text-amber-600 dark:text-amber-400 font-black' : 'text-slate-900 dark:text-white'">{{ r.label }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
              {{ r.name === 'admin' ? $t('roles.full_permissions_badge') : $t('roles.active_permissions_count', { count: r.permissions_count }) }}
            </div>
          </button>
        </div>

        <!-- Admin Notice -->
        <div v-if="selectedRole?.name === 'admin'" class="p-4 bg-purple-500/10 border border-purple-500/20 rounded-2xl flex items-center gap-3 text-xs text-purple-300">
          <ShieldAlert class="w-5 h-5 shrink-0" />
          <span>{{ $t('roles.admin_role_notice') }}</span>
        </div>

        <!-- Permission Modules Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="(mod, modKey) in permissionModules"
            :key="modKey"
            class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-3"
          >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2.5">
              <div class="flex items-center gap-2">
                <span class="text-lg">{{ mod.icon }}</span>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white">{{ mod.title }}</h3>
              </div>

              <div v-if="selectedRole?.name !== 'admin'" class="flex items-center gap-2 text-[10px]">
                <button
                  @click="toggleModule(mod.permissions, true)"
                  class="text-amber-400 hover:underline"
                >
                  {{ $t('roles.select_all') }}
                </button>
                <span class="text-slate-600">|</span>
                <button
                  @click="toggleModule(mod.permissions, false)"
                  class="text-slate-500 hover:text-slate-400"
                >
                  {{ $t('roles.deselect_all') }}
                </button>
              </div>
            </div>

            <!-- Permission Items -->
            <div class="space-y-2 pt-1">
              <label
                v-for="(label, permKey) in mod.permissions"
                :key="permKey"
                class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-100 dark:hover:bg-slate-900 cursor-pointer transition text-xs"
              >
                <span class="text-slate-700 dark:text-slate-300 font-medium">{{ label }}</span>
                <input
                  type="checkbox"
                  :value="permKey"
                  v-model="activePermissions"
                  :disabled="selectedRole?.name === 'admin'"
                  class="w-4 h-4 rounded bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-emerald-600 focus:ring-emerald-500 cursor-pointer disabled:opacity-50"
                />
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    ShieldCheck,
    ShieldAlert,
    Users,
    Check
} from 'lucide-vue-next';

const roles = ref([]);
const selectedRole = ref(null);
const permissionModules = ref({});
const activePermissions = ref([]);
const isLoading = ref(false);
const isSaving = ref(false);

const fetchMatrix = async (roleId = null) => {
    isLoading.value = true;
    try {
        const res = await api.get('/roles', { params: { role_id: roleId } });
        const data = res.data?.data || {};
        roles.value = data.roles || [];
        permissionModules.value = data.permission_modules || {};

        if (roleId) {
            selectedRole.value = roles.value.find(r => r.id === roleId) || roles.value[0];
        } else {
            selectedRole.value = data.selected_role || roles.value[0];
        }

        activePermissions.value = [...(selectedRole.value?.permissions || [])];
    } catch (e) {
        console.error('Failed to load roles matrix:', e);
    } finally {
        isLoading.value = false;
    }
};

const selectRole = (role) => {
    selectedRole.value = role;
    activePermissions.value = [...(role.permissions || [])];
};

const toggleModule = (permissionsObj, selectAll) => {
    const keys = Object.keys(permissionsObj);
    if (selectAll) {
        keys.forEach(k => {
            if (!activePermissions.value.includes(k)) {
                activePermissions.value.push(k);
            }
        });
    } else {
        activePermissions.value = activePermissions.value.filter(k => !keys.includes(k));
    }
};

const savePermissions = async () => {
    if (!selectedRole.value || selectedRole.value.name === 'admin') return;

    isSaving.value = true;
    try {
        await api.put(`/roles/${selectedRole.value.id}/permissions`, {
            permissions: activePermissions.value,
        });

        // Update local state count
        selectedRole.value.permissions = [...activePermissions.value];
        selectedRole.value.permissions_count = activePermissions.value.length;

        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: trans('roles.permissions_saved_success'),
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: e.response?.data?.message || trans('roles.permissions_save_failed'),
        });
    } finally {
        isSaving.value = false;
    }
};

onMounted(() => {
    fetchMatrix();
});
</script>
