<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header & Action Controls -->
    <PageHeader
      :title="$t('roles.title')"
      :subtitle="$t('roles.subtitle')"
      icon="🛡️"
    >
      <template #actions>
        <div class="flex items-center gap-3">
          <router-link
            to="/users"
            class="min-h-[38px] px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-xl shadow-xs flex items-center gap-2 transition active:scale-95"
          >
            <Users class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('roles.users_and_employees') }}</span>
          </router-link>

          <BaseButton
            v-if="selectedRole?.name !== 'admin'"
            type="button"
            variant="primary"
            size="md"
            :loading="isSaving"
            @click="savePermissions"
            class="font-black shadow-theme-primary shadow-lg flex items-center gap-2"
          >
            <Check class="w-4 h-4" />
            <span>{{ $t('profile.save_changes') }}</span>
          </BaseButton>
        </div>
      </template>
    </PageHeader>

    <!-- Roles Selector Grid -->
    <RolesSelectorGrid
      :roles="roles"
      :selected-role-id="selectedRole?.id"
      :loading="isLoading"
      @select-role="selectRole"
    />

    <!-- Admin Protection Notice -->
    <RoleAdminNotice v-if="selectedRole?.name === 'admin'" />

    <!-- Permission Modules Grid -->
    <PermissionModulesGrid
      v-if="!isLoading"
      :permission-modules="permissionModules"
      :active-permissions="activePermissions"
      :is-admin="selectedRole?.name === 'admin'"
      @toggle-module="toggleModule"
      @toggle-permission="togglePermission"
    />
  </div>
</template>

<script setup>
import { Users, Check } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import RolesSelectorGrid from '../../Components/Roles/RolesSelectorGrid.vue';
import RoleAdminNotice from '../../Components/Roles/RoleAdminNotice.vue';
import PermissionModulesGrid from '../../Components/Roles/PermissionModulesGrid.vue';
import { useRoles } from '../../Composables/useRoles';

const {
  roles,
  selectedRole,
  permissionModules,
  activePermissions,
  isLoading,
  isSaving,
  selectRole,
  togglePermission,
  toggleModule,
  savePermissions,
} = useRoles();
</script>
