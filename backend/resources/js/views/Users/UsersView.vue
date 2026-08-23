<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header & Action Controls -->
    <PageHeader
      :title="$t('users.users_title')"
      :subtitle="$t('users.users_subtitle')"
      icon="👥"
    >
      <template #actions>
        <div class="flex items-center gap-3 w-full sm:w-auto">
          <router-link
            to="/roles"
            class="min-h-[38px] px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-xl shadow-xs flex items-center gap-2 transition active:scale-95"
          >
            <ShieldCheck class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('users.permissions_matrix_btn') }}</span>
          </router-link>

          <BaseButton
            type="button"
            variant="primary"
            size="md"
            @click="openCreateModal"
            class="font-black shadow-theme-primary shadow-lg flex items-center gap-2"
          >
            <UserPlus class="w-4 h-4" />
            <span>{{ $t('users.add_user_btn') }}</span>
          </BaseButton>
        </div>
      </template>
    </PageHeader>

    <!-- Filters & Search -->
    <UsersFilterBar
      :search="filters.search"
      :role="filters.role"
      :roles-list="rolesList"
      @update:search="updateSearch"
      @update:role="updateRoleFilter"
    />

    <!-- Users Table & Mobile Cards -->
    <UsersTable
      :users="users"
      :pagination="pagination"
      :loading="isLoading"
      @edit="openEditModal"
      @delete="deleteUser"
      @toggle-active="toggleActive"
      @page-change="changePage"
    />

    <!-- Create / Edit User Modal -->
    <UserFormModal
      :show="showModal"
      :is-editing="isEditing"
      :form="form"
      :roles-list="rolesList"
      :stores-list="storesList"
      :submitting="isSubmitting"
      @close="showModal = false"
      @submit="submitForm"
      @update:field="updateFormField"
    />
  </div>
</template>

<script setup>
import { ShieldCheck, UserPlus } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import UsersFilterBar from '../../Components/Users/UsersFilterBar.vue';
import UsersTable from '../../Components/Users/UsersTable.vue';
import UserFormModal from '../../Components/Users/UserFormModal.vue';
import { useUsers } from '../../Composables/useUsers';

const {
  users,
  rolesList,
  storesList,
  isLoading,
  isSubmitting,
  filters,
  pagination,
  showModal,
  isEditing,
  form,
  updateSearch,
  updateRoleFilter,
  updateFormField,
  changePage,
  openCreateModal,
  openEditModal,
  submitForm,
  toggleActive,
  deleteUser,
} = useUsers();
</script>
