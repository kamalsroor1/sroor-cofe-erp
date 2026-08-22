<template>
  <div class="space-y-6 max-w-7xl mx-auto">
      <!-- Page Header -->
      <PageHeader
        :title="$t('inventory.stores_branches')"
        :subtitle="$t('inventory.stores_branches_subtitle')"
        :icon="'🏬'"
      >
        <template #actions>
          <div class="flex items-center gap-2">
            <router-link
              to="/stores/stocks"
              class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-2 font-tajawal shadow-sm"
            >
              <Package class="w-4 h-4 text-theme-primary" />
              <span>{{ $t('inventory.branch_stocks_balance') }}</span>
            </router-link>

            <button
              type="button"
              @click="openCreateModal"
              class="px-4 py-2.5 bg-theme-gradient text-white font-black shadow-theme-primary rounded-xl text-xs font-black transition-all flex items-center gap-2 font-tajawal shadow-lg shadow-theme-primary cursor-pointer"
            >
              <Plus class="w-4 h-4" />
              <span>{{ $t('inventory.add_store') }}</span>
            </button>
          </div>
        </template>
      </PageHeader>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400 font-bold font-tajawal">{{ $t('common.loading') }}</p>
      </div>

      <!-- Stores Grid -->
      <div v-else-if="stores.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="store in stores"
          :key="store.id"
          class="bg-white dark:bg-slate-900/90 border rounded-2xl p-5 transition-all relative overflow-hidden flex flex-col justify-between group shadow-sm dark:shadow-lg"
          :class="[
            store.is_main ? 'border-theme-primary shadow-theme-primary ring-1 ring-theme-primary/30' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700',
            !store.is_active ? 'opacity-60 grayscale-[50%]' : ''
          ]"
        >
          <!-- Main Branch Badge Background Glow -->
          <div v-if="store.is_main" class="absolute -top-12 -right-12 w-32 h-32 bg-theme-light rounded-full blur-2xl pointer-events-none"></div>

          <div>
            <!-- Header Row: Type, Code & Status -->
            <div class="flex items-center justify-between gap-2 mb-3">
              <div class="flex items-center gap-1.5">
                <span class="text-xl">
                  {{ store.type === 'van' ? '🚚' : (store.type === 'warehouse' ? '🏭' : '🏬') }}
                </span>
                <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-400">
                  {{ store.code }}
                </span>
                <span v-if="store.is_main" class="text-[10px] font-black px-2 py-0.5 rounded-md bg-theme-light text-theme-primary border border-theme-border font-tajawal">
                  {{ $t('inventory.main_store') }}
                </span>
              </div>

              <!-- Active Status Toggle Badge -->
              <button
                type="button"
                @click="toggleActive(store)"
                :disabled="store.is_main && store.is_active"
                class="text-[10px] font-bold px-2.5 py-1 rounded-full border transition-all cursor-pointer font-tajawal flex items-center gap-1"
                :class="store.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
                :title="store.is_main ? $t('inventory.cannot_disable_main_store') : (store.is_active ? $t('common.active') : $t('common.inactive'))"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="store.is_active ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
                <span>{{ store.is_active ? $t('common.active') : $t('common.inactive') }}</span>
              </button>
            </div>

            <!-- Store Title & Info -->
            <h3 class="text-base font-black text-slate-900 dark:text-white font-tajawal group-hover:text-theme-primary transition-colors">
              {{ store.name }}
            </h3>

            <div class="text-xs text-slate-400 mt-2 space-y-1 font-tajawal">
              <div v-if="store.address" class="flex items-center gap-1.5 text-[11px]">
                <MapPin class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                <span class="truncate">{{ store.address }}</span>
              </div>
              <div v-if="store.phone" class="flex items-center gap-1.5 text-[11px] font-mono" dir="ltr">
                <Phone class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                <span>{{ store.phone }}</span>
              </div>
            </div>

            <!-- Statistics Counters -->
            <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/80 text-center">
              <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/50">
                <div class="text-[10px] text-slate-400 font-tajawal">{{ $t('inventory.items_count') }}</div>
                <div class="text-sm font-black text-theme-primary font-mono mt-0.5">{{ store.stocks_count || 0 }}</div>
              </div>
              <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/50">
                <div class="text-[10px] text-slate-400 font-tajawal">{{ $t('inventory.invoices_count') }}</div>
                <div class="text-sm font-black text-emerald-400 font-mono mt-0.5">{{ store.invoices_count || 0 }}</div>
              </div>
              <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/50">
                <div class="text-[10px] text-slate-400 font-tajawal">{{ $t('inventory.purchases_count') }}</div>
                <div class="text-sm font-black text-blue-400 font-mono mt-0.5">{{ store.purchases_count || 0 }}</div>
              </div>
            </div>

            <!-- Assigned Staff Avatars -->
            <div class="mt-3.5 pt-3 border-t border-slate-200 dark:border-slate-800/80">
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('inventory.assigned_staff') }}:</span>
                <button
                  type="button"
                  @click="openUserAssignmentModal(store)"
                  class="text-[11px] text-theme-primary hover:underline font-bold font-tajawal cursor-pointer flex items-center gap-1"
                >
                  <Users class="w-3 h-3" />
                  <span>{{ $t('inventory.manage_staff') }}</span>
                </button>
              </div>

              <div class="flex flex-wrap gap-1 mt-2">
                <template v-if="store.assigned_users && store.assigned_users.length > 0">
                  <span
                    v-for="user in store.assigned_users"
                    :key="user.id"
                    class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-[10px] font-bold text-slate-700 dark:text-slate-300 font-tajawal"
                  >
                    👤 {{ user.name }}
                  </span>
                </template>
                <span v-else class="text-[10px] text-slate-500 font-tajawal italic">
                  {{ $t('inventory.no_staff_assigned') }}
                </span>
              </div>
            </div>
          </div>

          <!-- Card Actions Footer -->
          <div class="flex items-center justify-between gap-2 mt-5 pt-3 border-t border-slate-200 dark:border-slate-800">
            <router-link
              :to="{ path: '/stores/stocks', query: { store_id: store.id } }"
              class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 font-tajawal shadow-xs"
            >
              <Package class="w-3.5 h-3.5 text-theme-primary" />
              <span>{{ $t('inventory.view_stocks') }}</span>
            </router-link>

            <div class="flex items-center gap-1">
              <button
                type="button"
                @click="openEditModal(store)"
                class="p-2 text-slate-400 hover:text-theme-primary hover:bg-slate-100 dark:hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition-all cursor-pointer"
                :title="$t('common.edit')"
              >
                <Pencil class="w-4 h-4" />
              </button>

              <button
                type="button"
                @click="deleteStore(store)"
                class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer"
                :title="$t('common.delete')"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <EmptyState
        v-else
        :title="$t('inventory.no_stores_found')"
        :description="$t('inventory.add_store_description')"
        :icon="'🏬'"
      >
        <template #action>
          <button
            type="button"
            @click="openCreateModal"
            class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black font-tajawal shadow-lg shadow-theme-primary cursor-pointer"
          >
            {{ $t('inventory.add_first_store') }}
          </button>
        </template>
      </EmptyState>

      <!-- Create / Edit Store Modal -->
      <AppModal
        :show="showStoreModal"
        :title="editingStore ? $t('inventory.edit_store') : $t('inventory.add_new_store')"
        @close="showStoreModal = false"
      >
        <form @submit.prevent="saveStore" class="space-y-4">
          <!-- Store Name -->
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 font-tajawal">
              {{ $t('inventory.store_name') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none font-tajawal"
              :placeholder="$t('inventory.store_name_placeholder')"
            >
          </div>

          <!-- Code & Type Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 font-tajawal">
                {{ $t('inventory.store_code') }}
              </label>
              <input
                v-model="form.code"
                type="text"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono uppercase focus:ring-2 focus:ring-theme-primary focus:outline-none"
                :placeholder="$t('inventory.store_code_placeholder')"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 font-tajawal">
                {{ $t('inventory.store_type') }} <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.type"
                required
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none font-tajawal"
              >
                <option value="retail_shop">🏬 {{ $t('inventory.retail_shop') }}</option>
                <option value="warehouse">🏭 {{ $t('inventory.warehouse') }}</option>
                <option value="van">🚚 {{ $t('inventory.distribution_van') }}</option>
              </select>
            </div>
          </div>

          <!-- Address & Phone Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 font-tajawal">
                {{ $t('inventory.address') }}
              </label>
              <input
                v-model="form.address"
                type="text"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none font-tajawal"
                :placeholder="$t('inventory.address_placeholder')"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 font-tajawal">
                {{ $t('inventory.phone') }}
              </label>
              <input
                v-model="form.phone"
                type="text"
                dir="ltr"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
                :placeholder="$t('inventory.phone_placeholder')"
              >
            </div>
          </div>

          <!-- Checkboxes (Is Main / Is Active) -->
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 pt-2">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input
                v-model="form.is_main"
                type="checkbox"
                class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-theme-primary focus:ring-theme-primary/20"
              >
              <span class="text-xs font-bold text-slate-700 dark:text-slate-300 font-tajawal">{{ $t('inventory.is_main_branch') }}</span>
            </label>

            <label v-if="editingStore" class="flex items-center gap-2 cursor-pointer select-none">
              <input
                v-model="form.is_active"
                type="checkbox"
                class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-theme-primary focus:ring-theme-primary/20"
              >
              <span class="text-xs font-bold text-slate-700 dark:text-slate-300 font-tajawal">{{ $t('inventory.is_active_branch') }}</span>
            </label>
          </div>

          <!-- Form Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
            <button
              type="button"
              @click="showStoreModal = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold font-tajawal cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-theme-gradient text-white shadow-theme-primary font-black rounded-xl text-xs font-black font-tajawal shadow-lg shadow-theme-primary disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ $t('common.save') }}</span>
            </button>
          </div>
        </form>
      </AppModal>

      <!-- Staff Assignment Modal -->
      <AppModal
        :show="showUserModal"
        :title="`${$t('inventory.assign_staff_to')} (${targetStore?.name})`"
        @close="showUserModal = false"
      >
        <form @submit.prevent="saveUserAssignment" class="space-y-4">
          <p class="text-xs text-slate-400 font-tajawal">
            {{ $t('inventory.assign_staff_description') }}
          </p>

          <div class="max-h-64 overflow-y-auto space-y-2 p-1 custom-scrollbar">
            <label
              v-for="user in allUsers"
              :key="user.id"
              class="flex items-center justify-between p-3 rounded-xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 hover:border-slate-700 cursor-pointer select-none transition-all"
            >
              <div class="flex items-center gap-2.5">
                <input
                  type="checkbox"
                  :value="user.id"
                  v-model="assignedUserIds"
                  class="w-4 h-4 rounded bg-white dark:bg-slate-900 border-slate-700 text-theme-primary focus:ring-theme-primary/20"
                >
                <div>
                  <div class="text-xs font-bold text-slate-900 dark:text-white font-tajawal">{{ user.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono">{{ user.email }}</div>
                </div>
              </div>
            </label>
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
            <button
              type="button"
              @click="showUserModal = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold font-tajawal cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-theme-gradient text-white shadow-theme-primary font-black rounded-xl text-xs font-black font-tajawal shadow-lg shadow-theme-primary disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ $t('common.save') }}</span>
            </button>
          </div>
        </form>
      </AppModal>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import AppModal from '../../Components/Common/AppModal.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    Plus,
    Package,
    MapPin,
    Phone,
    Users,
    Pencil,
    Trash2
} from 'lucide-vue-next';

const stores = ref([]);
const allUsers = ref([]);
const isLoading = ref(false);
const isSubmitting = ref(false);

// Create / Edit State
const showStoreModal = ref(false);
const editingStore = ref(null);
const form = reactive({
    name: '',
    code: '',
    type: 'retail_shop',
    address: '',
    phone: '',
    is_active: true,
    is_main: false,
});

// User Assignment State
const showUserModal = ref(false);
const targetStore = ref(null);
const assignedUserIds = ref([]);

const fetchStores = async () => {
    isLoading.value = true;
    try {
        const response = await api.get('/stores');
        stores.value = response.data?.stores || [];
        allUsers.value = response.data?.all_users || [];
    } catch (error) {
        console.error('Failed to load stores:', error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchStores);

const openCreateModal = () => {
    editingStore.value = null;
    form.name = '';
    form.code = '';
    form.type = 'retail_shop';
    form.address = '';
    form.phone = '';
    form.is_active = true;
    form.is_main = false;
    showStoreModal.value = true;
};

const openEditModal = (store) => {
    editingStore.value = store;
    form.name = store.name;
    form.code = store.code;
    form.type = store.type;
    form.address = store.address || '';
    form.phone = store.phone || '';
    form.is_active = store.is_active;
    form.is_main = store.is_main;
    showStoreModal.value = true;
};

const saveStore = async () => {
    isSubmitting.value = true;
    try {
        if (editingStore.value) {
            await api.put(`/stores/${editingStore.value.id}`, form);
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('inventory.store_updated_success'),
                timer: 1500,
                showConfirmButton: false,
            });
        } else {
            await api.post('/stores', form);
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('inventory.store_added_success'),
                timer: 1500,
                showConfirmButton: false,
            });
        }
        showStoreModal.value = false;
        await fetchStores();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || trans('inventory.store_save_failed'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

const toggleActive = async (store) => {
    if (store.is_main && store.is_active) {
        Swal.fire({
            icon: 'warning',
            title: trans('common.warning'),
            text: trans('inventory.cannot_disable_main_alert'),
        });
        return;
    }

    try {
        await api.patch(`/stores/${store.id}/toggle-active`);
        await fetchStores();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || trans('inventory.store_toggle_active_failed'),
        });
    }
};

const deleteStore = async (store) => {
    if (!store.can_be_deleted) {
        const blockers = store.deletion_blockers?.join('\n- ') || '';
        Swal.fire({
            icon: 'warning',
            title: trans('inventory.cannot_delete_store'),
            text: `يوجد ارتباطات عمليات تمنع الحذف:\n- ${blockers}`,
        });
        return;
    }

    const result = await Swal.fire({
        title: trans('inventory.store_delete_confirm_title', { name: store.name }),
        text: trans('inventory.store_delete_confirm_text'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: trans('common.yes'),
        cancelButtonText: trans('common.cancel'),
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/stores/${store.id}`);
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('inventory.store_deleted_success'),
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchStores();
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: trans('common.error'),
                text: error.userMessage || trans('inventory.store_delete_failed'),
            });
        }
    }
};

const openUserAssignmentModal = (store) => {
    targetStore.value = store;
    assignedUserIds.value = store.assigned_user_ids ? [...store.assigned_user_ids] : [];
    showUserModal.value = true;
};

const saveUserAssignment = async () => {
    isSubmitting.value = true;
    try {
        await api.post(`/stores/${targetStore.value.id}/assign-users`, {
            user_ids: assignedUserIds.value,
        });
        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: trans('inventory.staff_assigned_success'),
            timer: 1500,
            showConfirmButton: false,
        });
        showUserModal.value = false;
        await fetchStores();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || trans('inventory.staff_assign_failed'),
        });
    } finally {
        isSubmitting.value = false;
    }
};
</script>
