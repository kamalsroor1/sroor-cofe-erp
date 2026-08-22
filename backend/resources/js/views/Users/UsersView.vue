<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
            <Users class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-white">{{ $t('users.users_title') }}</h1>
            <p class="text-xs text-slate-400">{{ $t('users.users_subtitle') }}</p>
          </div>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
          <router-link
            to="/roles"
            class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-bold text-xs rounded-xl shadow flex items-center gap-2 transition font-tajawal"
          >
            <ShieldCheck class="w-4 h-4 text-amber-400" />
            <span>{{ $t('users.permissions_matrix_btn') }}</span>
          </router-link>

          <button
            @click="openCreateModal"
            class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs rounded-xl shadow-lg shadow-amber-500/20 flex items-center gap-2 transition cursor-pointer font-tajawal"
          >
            <UserPlus class="w-4 h-4" />
            <span>{{ $t('users.add_user_btn') }}</span>
          </button>
        </div>
      </div>

      <!-- Filters & Search -->
      <div class="p-4 bg-slate-950/80 rounded-2xl border border-slate-800 shadow-lg flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex-1 w-full relative">
          <Search class="w-4 h-4 text-slate-400 absolute start-3 top-3" />
          <input
            v-model="filters.search"
            @input="debouncedFetch"
            type="text"
            :placeholder="$t('users.search_users_placeholder')"
            class="w-full bg-slate-900 border border-slate-700 rounded-xl ps-9 pe-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 font-tajawal"
          />
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
          <select
            v-model="filters.role"
            @change="fetchUsers"
            class="bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-amber-500 font-tajawal"
          >
            <option value="all">{{ $t('users.all_roles_filter') }}</option>
            <option v-for="r in rolesList" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>
      </div>

      <!-- Users Grid / Table -->
      <div class="bg-slate-950/80 rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
        <div v-if="isLoading" class="p-16 text-center">
          <div class="w-10 h-10 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-xs text-slate-400 font-tajawal">{{ $t('users.loading_users') }}</p>
        </div>

        <div v-else-if="users.length === 0" class="p-16 text-center">
          <Users class="w-12 h-12 text-slate-600 mx-auto mb-3" />
          <h3 class="text-sm font-bold text-slate-300 mb-1 font-tajawal">{{ $t('users.no_users_found') }}</h3>
          <p class="text-xs text-slate-500 font-tajawal">{{ $t('users.no_users_hint') }}</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-start text-xs">
            <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 font-bold font-tajawal">
              <tr>
                <th class="p-4 text-start">{{ $t('users.employee_col') }}</th>
                <th class="p-4 text-start">{{ $t('users.phone_col') }}</th>
                <th class="p-4 text-start">{{ $t('users.role_col') }}</th>
                <th class="p-4 text-start">{{ $t('users.default_store_col') }}</th>
                <th class="p-4 text-center">{{ $t('users.active_status_col') }}</th>
                <th class="p-4 text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono">
              <tr v-for="u in users" :key="u.id" class="hover:bg-slate-900/40 transition">
                <td class="p-4 font-sans font-bold text-white flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-amber-400 font-bold">
                    {{ u.name.charAt(0) }}
                  </div>
                  <div>
                    <div class="font-tajawal">{{ u.name }}</div>
                    <div class="text-[10px] text-slate-400 font-mono">{{ u.email || $t('users.no_email') }}</div>
                  </div>
                </td>

                <td class="p-4 text-slate-300 font-mono" dir="ltr">{{ u.phone }}</td>

                <td class="p-4 font-sans">
                  <span
                    class="px-2.5 py-1 rounded-full text-[11px] font-bold border font-tajawal"
                    :class="getRoleBadgeClass(u.primary_role)"
                  >
                    {{ getRoleLabel(u.primary_role) }}
                  </span>
                </td>

                <td class="p-4 font-sans text-slate-300 font-tajawal">{{ u.default_store_name || $t('users.no_store_assigned') }}</td>

                <td class="p-4 text-center font-sans">
                  <button
                    @click="toggleActive(u)"
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border transition cursor-pointer font-tajawal"
                    :class="u.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/20' : 'bg-rose-500/10 border-rose-500/30 text-rose-400 hover:bg-rose-500/20'"
                  >
                    {{ u.is_active ? $t('users.status_active_badge') : $t('users.status_inactive_badge') }}
                  </button>
                </td>

                <td class="p-4 text-end font-sans">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click="openEditModal(u)"
                      class="p-1.5 bg-slate-900 hover:bg-slate-800 text-amber-400 border border-slate-700 rounded-lg transition cursor-pointer"
                      :title="$t('common.edit')"
                    >
                      <Edit2 class="w-3.5 h-3.5" />
                    </button>
                    <button
                      @click="deleteUser(u)"
                      class="p-1.5 bg-slate-900 hover:bg-rose-950/40 text-rose-400 border border-slate-700 hover:border-rose-800 rounded-lg transition cursor-pointer"
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

        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400 font-mono">
          <span class="font-tajawal">{{ $t('users.total_users_count', { count: pagination.total }) }}</span>
          <div class="flex items-center gap-2 font-sans font-tajawal">
            <button
              :disabled="pagination.current_page === 1"
              @click="changePage(pagination.current_page - 1)"
              class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 border border-slate-700 rounded-xl cursor-pointer"
            >
              {{ $t('common.previous') }}
            </button>
            <span class="font-mono">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button
              :disabled="pagination.current_page === pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
              class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 border border-slate-700 rounded-xl cursor-pointer"
            >
              {{ $t('common.next') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Create / Edit User Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-slate-950 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h2 class="text-base font-black text-white flex items-center gap-2">
              <UserPlus class="w-4 h-4 text-amber-400" />
              <span>{{ isEditing ? $t('users.edit_user_title') : $t('users.create_user_title') }}</span>
            </h2>
            <button @click="showModal = false" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-3.5 text-xs font-tajawal">
            <div>
              <label class="block text-slate-400 font-bold mb-1">{{ $t('users.fullname_label') }}</label>
              <input
                v-model="form.name"
                required
                type="text"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
                :placeholder="$t('users.fullname_placeholder')"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('users.phone_label') }}</label>
                <input
                  v-model="form.phone"
                  required
                  type="text"
                  dir="ltr"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500"
                  placeholder="010XXXXXXXX"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('users.email_optional_label') }}</label>
                <input
                  v-model="form.email"
                  type="email"
                  dir="ltr"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500"
                  placeholder="user@example.com"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('users.job_role_label') }}</label>
                <select
                  v-model="form.role"
                  required
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500 font-tajawal"
                >
                  <option v-for="r in rolesList" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('users.default_store_label') }}</label>
                <select
                  v-model="form.default_store_id"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500 font-tajawal"
                >
                  <option :value="null">{{ $t('users.no_store_assigned') }}</option>
                  <option v-for="st in storesList" :key="st.id" :value="st.id">{{ st.name }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-slate-400 font-bold mb-1">
                {{ isEditing ? $t('users.password_edit_label') : $t('users.password_create_label') }}
              </label>
              <input
                v-model="form.password"
                :required="!isEditing"
                type="password"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500"
                placeholder="••••••••"
              />
            </div>

            <div class="flex items-center gap-2 pt-1">
              <input
                v-model="form.is_active"
                type="checkbox"
                id="is_active_check"
                class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-amber-500"
              />
              <label for="is_active_check" class="text-slate-300 font-bold cursor-pointer">{{ $t('users.account_active_login_checkbox') }}</label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
              <button
                type="button"
                @click="showModal = false"
                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded-xl font-bold cursor-pointer"
              >
                {{ $t('common.cancel') }}
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="px-5 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black rounded-xl shadow-lg transition disabled:opacity-50 cursor-pointer"
              >
                {{ isSubmitting ? $t('common.loading') : (isEditing ? $t('common.save') : $t('common.save')) }}
              </button>
            </div>
          </form>
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
    Users,
    UserPlus,
    ShieldCheck,
    Search,
    Edit2,
    Trash2
} from 'lucide-vue-next';

const users = ref([]);
const rolesList = ref([]);
const storesList = ref([]);
const isLoading = ref(false);
const isSubmitting = ref(false);

const filters = ref({
    search: '',
    role: 'all',
    page: 1,
});

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = ref({
    name: '',
    phone: '',
    email: '',
    password: '',
    role: 'cashier',
    default_store_id: null,
    is_active: true,
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchUsers();
    }, 300);
};

const fetchUsers = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/users', { params: filters.value });
        users.value = res.data?.data || [];
        rolesList.value = res.data?.roles || [];
        storesList.value = res.data?.stores || [];
        pagination.value = res.data?.pagination || pagination.value;
    } catch (e) {
        console.error('Failed to fetch users:', e);
    } finally {
        isLoading.value = false;
    }
};

const changePage = (page) => {
    filters.value.page = page;
    fetchUsers();
};

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
        name: '',
        phone: '',
        email: '',
        password: '',
        role: 'cashier',
        default_store_id: storesList.value[0]?.id || null,
        is_active: true,
    };
    showModal.value = true;
};

const openEditModal = (u) => {
    isEditing.value = true;
    editingId.value = u.id;
    form.value = {
        name: u.name,
        phone: u.phone,
        email: u.email || '',
        password: '',
        role: u.primary_role || 'cashier',
        default_store_id: u.default_store_id,
        is_active: u.is_active,
    };
    showModal.value = true;
};

const submitForm = async () => {
    isSubmitting.value = true;
    try {
        if (isEditing.value) {
            await api.put(`/users/${editingId.value}`, form.value);
            Swal.fire({ icon: 'success', title: trans('common.success'), text: trans('users.user_updated_success'), timer: 1500, showConfirmButton: false });
        } else {
            await api.post('/users', form.value);
            Swal.fire({ icon: 'success', title: trans('common.success'), text: trans('users.user_created_success'), timer: 1500, showConfirmButton: false });
        }
        showModal.value = false;
        fetchUsers();
    } catch (e) {
        Swal.fire({ icon: 'error', title: trans('common.error'), text: e.response?.data?.message || trans('users.user_save_failed') });
    } finally {
        isSubmitting.value = false;
    }
};

const toggleActive = async (u) => {
    try {
        const res = await api.patch(`/users/${u.id}/toggle-active`);
        u.is_active = res.data?.is_active;
    } catch (e) {
        Swal.fire({ icon: 'error', title: trans('common.error'), text: e.response?.data?.message || trans('users.user_toggle_active_failed') });
    }
};

const deleteUser = async (u) => {
    const result = await Swal.fire({
        title: trans('users.delete_user_confirm_title', { name: u.name }),
        text: trans('users.delete_user_confirm_text'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#334155',
        confirmButtonText: trans('common.yes'),
        cancelButtonText: trans('common.cancel'),
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/users/${u.id}`);
            Swal.fire({ icon: 'success', title: trans('common.success'), text: trans('users.user_deleted_success'), timer: 1500, showConfirmButton: false });
            fetchUsers();
        } catch (e) {
            Swal.fire({ icon: 'error', title: trans('common.error'), text: e.response?.data?.message || trans('users.user_delete_failed') });
        }
    }
};

const getRoleLabel = (role) => {
    return matchRole(role);
};

const matchRole = (role) => {
    switch (role) {
        case 'admin': return trans('users.role_admin');
        case 'cashier': return trans('users.role_cashier');
        case 'storekeeper': return trans('users.role_storekeeper');
        case 'accountant': return trans('users.role_accountant');
        default: return role;
    }
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'admin': return 'bg-purple-500/10 border-purple-500/30 text-purple-400';
        case 'cashier': return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
        case 'storekeeper': return 'bg-amber-500/10 border-amber-500/30 text-amber-400';
        case 'accountant': return 'bg-cyan-500/10 border-cyan-500/30 text-cyan-400';
        default: return 'bg-slate-500/10 border-slate-500/30 text-slate-400';
    }
};

onMounted(() => {
    fetchUsers();
});
</script>
