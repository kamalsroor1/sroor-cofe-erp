<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
            <Building2 class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-white">{{ $t('super.tenants_page_title') }}</h1>
            <p class="text-xs text-slate-400">{{ $t('super.tenants_page_subtitle') }}</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <router-link
            to="/super-admin/dashboard"
            class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-bold text-xs rounded-xl shadow flex items-center gap-2 transition"
          >
            <Crown class="w-4 h-4 text-purple-400" />
            <span>{{ $t('super.dashboard') }}</span>
          </router-link>

          <button
            @click="openCreateModal"
            class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/20 flex items-center gap-2 transition cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>{{ $t('super.new_tenant_btn') }}</span>
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
            :placeholder="$t('super.search_tenants_placeholder')"
            class="w-full bg-slate-900 border border-slate-700 rounded-xl ps-9 pe-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500"
          />
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
          <select
            v-model="filters.status"
            @change="fetchTenants"
            class="bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-purple-500 font-tajawal"
          >
            <option value="all">{{ $t('super.all_statuses') }}</option>
            <option value="active">{{ $t('super.status_active') }}</option>
            <option value="trial">{{ $t('super.status_trial') }}</option>
            <option value="suspended">{{ $t('super.status_suspended') }}</option>
          </select>

          <select
            v-model="filters.plan_id"
            @change="fetchTenants"
            class="bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-purple-500 font-tajawal"
          >
            <option value="all">{{ $t('super.all_plans') }}</option>
            <option v-for="p in plansList" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
      </div>

      <!-- Tenants Table -->
      <div class="bg-slate-950/80 rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
        <div v-if="isLoading" class="p-16 text-center">
          <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-xs text-slate-400">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="tenants.length === 0" class="p-16 text-center">
          <Building2 class="w-12 h-12 text-slate-600 mx-auto mb-3" />
          <h3 class="text-sm font-bold text-slate-300 mb-1">{{ $t('super.no_tenants_registered') }}</h3>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-start text-xs">
            <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 font-bold">
              <tr>
                <th class="p-4 text-start font-tajawal">{{ $t('super.tenant_org_col') }}</th>
                <th class="p-4 text-start font-tajawal">{{ $t('super.domain_path_col') }}</th>
                <th class="p-4 text-start font-tajawal">{{ $t('super.subscribed_plan_col') }}</th>
                <th class="p-4 text-start font-tajawal">{{ $t('super.email_admin_col') }}</th>
                <th class="p-4 text-center font-tajawal">{{ $t('common.status') }}</th>
                <th class="p-4 text-end font-tajawal">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono">
              <tr v-for="t in tenants" :key="t.id" class="hover:bg-slate-900/40 transition">
                <td class="p-4 font-sans font-bold text-white font-tajawal">
                  <div class="text-sm">{{ t.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono">ID: {{ t.id }}</div>
                </td>

                <td class="p-4 text-cyan-400 font-mono">
                  <a :href="`http://${t.domain}`" target="_blank" class="hover:underline flex items-center gap-1">
                    <span>{{ t.domain }}</span>
                    <ExternalLink class="w-3 h-3" />
                  </a>
                </td>

                <td class="p-4 font-sans">
                  <span class="px-2.5 py-1 bg-purple-500/10 border border-purple-500/30 text-purple-400 rounded-full font-bold">
                    {{ t.plan_name }}
                  </span>
                </td>

                <td class="p-4 text-slate-300 font-mono">
                  <div>{{ t.email }}</div>
                  <div class="text-[10px] text-slate-500">{{ t.phone || $t('super.no_phone') }}</div>
                </td>

                <td class="p-4 text-center font-sans">
                  <button
                    @click="openStatusModal(t)"
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border transition cursor-pointer"
                    :class="getStatusBadgeClass(t.status)"
                  >
                    {{ getStatusLabel(t.status) }}
                  </button>
                </td>

                <td class="p-4 text-end font-sans">
                  <button
                    @click="openStatusModal(t)"
                    class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-amber-400 border border-slate-700 rounded-lg text-xs font-bold transition font-tajawal cursor-pointer"
                  >
                    {{ $t('super.edit_status_and_sub_btn') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Create Tenant Modal -->
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-slate-950 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h2 class="text-base font-black text-white flex items-center gap-2">
              <Building2 class="w-4 h-4 text-purple-400" />
              <span>{{ $t('super.create_tenant_modal_title') }}</span>
            </h2>
            <button @click="showCreateModal = false" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
          </div>

          <form @submit.prevent="submitCreateTenant" class="space-y-3.5 text-xs font-tajawal">
            <div>
              <label class="block text-slate-400 font-bold mb-1">{{ $t('super.org_name_label') }}</label>
              <input
                v-model="createForm.name"
                required
                type="text"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-purple-500"
                :placeholder="$t('super.org_name_placeholder')"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.slug_label') }}</label>
                <input
                  v-model="createForm.slug"
                  required
                  type="text"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
                  :placeholder="$t('super.slug_placeholder')"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.selected_plan_label') }}</label>
                <select
                  v-model="createForm.plan_id"
                  required
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-purple-500 font-tajawal"
                >
                  <option v-for="p in plansList" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.admin_email_label') }}</label>
                <input
                  v-model="createForm.email"
                  required
                  type="email"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
                  :placeholder="$t('super.admin_email_placeholder')"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.admin_phone_label') }}</label>
                <input
                  v-model="createForm.phone"
                  type="text"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
                  :placeholder="$t('super.admin_phone_placeholder')"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.admin_password_label') }}</label>
                <input
                  v-model="createForm.password"
                  required
                  type="password"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
                  placeholder="••••••••"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.trial_days_label') }}</label>
                <input
                  v-model="createForm.trial_days"
                  type="number"
                  min="0"
                  max="90"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
                />
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
              <button
                type="button"
                @click="showCreateModal = false"
                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded-xl font-bold cursor-pointer"
              >
                {{ $t('common.cancel') }}
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="px-5 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black rounded-xl shadow-lg transition disabled:opacity-50 cursor-pointer"
              >
                {{ isSubmitting ? $t('super.provisioning_status') : $t('super.create_and_provision_btn') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Status Modal -->
      <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-slate-950 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h2 class="text-base font-black text-white">{{ $t('super.edit_tenant_status_title', { name: selectedTenant?.name || '' }) }}</h2>
            <button @click="showStatusModal = false" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
          </div>

          <form @submit.prevent="submitStatusChange" class="space-y-3.5 text-xs font-tajawal">
            <div>
              <label class="block text-slate-400 font-bold mb-1">{{ $t('super.new_status_label') }}</label>
              <select
                v-model="statusForm.status"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-purple-500 font-tajawal"
              >
                <option value="active">{{ $t('super.status_active_opt') }}</option>
                <option value="trial">{{ $t('super.status_trial_opt') }}</option>
                <option value="suspended">{{ $t('super.status_suspended_opt') }}</option>
                <option value="expired">{{ $t('super.status_expired_opt') }}</option>
              </select>
            </div>

            <div>
              <label class="block text-slate-400 font-bold mb-1">{{ $t('super.extend_days_label') }}</label>
              <input
                v-model="statusForm.extend_days"
                type="number"
                min="0"
                max="365"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
                placeholder="0"
              />
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
              <button
                type="button"
                @click="showStatusModal = false"
                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded-xl font-bold cursor-pointer"
              >
                {{ $t('common.cancel') }}
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="px-5 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-black rounded-xl shadow-lg transition disabled:opacity-50 cursor-pointer"
              >
                {{ isSubmitting ? $t('common.loading') : $t('super.save_status_btn') }}
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
    Building2,
    Crown,
    Plus,
    Search,
    ExternalLink
} from 'lucide-vue-next';

const tenants = ref([]);
const plansList = ref([]);
const isLoading = ref(false);
const isSubmitting = ref(false);

const filters = ref({
    search: '',
    status: 'all',
    plan_id: 'all',
});

const showCreateModal = ref(false);
const showStatusModal = ref(false);
const selectedTenant = ref(null);

const createForm = ref({
    name: '',
    slug: '',
    email: '',
    phone: '',
    password: '',
    plan_id: null,
    trial_days: 14,
});

const statusForm = ref({
    status: 'active',
    extend_days: 0,
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchTenants();
    }, 300);
};

const fetchTenants = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/super-admin/tenants', { params: filters.value });
        tenants.value = res.data?.tenants?.data || res.data?.tenants || [];
        plansList.value = res.data?.plans || [];
        if (!createForm.value.plan_id && plansList.value.length > 0) {
            createForm.value.plan_id = plansList.value[0].id;
        }
    } catch (e) {
        console.error('Failed to load tenants:', e);
    } finally {
        isLoading.value = false;
    }
};

const openCreateModal = () => {
    createForm.value = {
        name: '',
        slug: '',
        email: '',
        phone: '',
        password: '',
        plan_id: plansList.value[0]?.id || null,
        trial_days: 14,
    };
    showCreateModal.value = true;
};

const submitCreateTenant = async () => {
    isSubmitting.value = true;
    try {
        await api.post('/super-admin/tenants', createForm.value);
        Swal.fire({
            icon: 'success',
            title: trans('super.tenant_created_title'),
            text: trans('super.tenant_created_msg'),
            timer: 2000,
            showConfirmButton: false,
        });
        showCreateModal.value = false;
        fetchTenants();
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: e.response?.data?.message || trans('super.tenant_create_failed'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

const openStatusModal = (tenant) => {
    selectedTenant.value = tenant;
    statusForm.value = {
        status: tenant.status || 'active',
        extend_days: 0,
    };
    showStatusModal.value = true;
};

const submitStatusChange = async () => {
    if (!selectedTenant.value) return;
    isSubmitting.value = true;
    try {
        await api.post(`/super-admin/tenants/${selectedTenant.value.id}/toggle-status`, statusForm.value);
        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: trans('super.status_updated_msg'),
            timer: 1500,
            showConfirmButton: false,
        });
        showStatusModal.value = false;
        fetchTenants();
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: e.response?.data?.message || trans('super.status_update_failed'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'active': return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
        case 'trial': return 'bg-amber-500/10 border-amber-500/30 text-amber-400';
        case 'suspended': return 'bg-rose-500/10 border-rose-500/30 text-rose-400';
        default: return 'bg-slate-500/10 border-slate-500/30 text-slate-400';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'active': return trans('super.status_active_badge');
        case 'trial': return trans('super.status_trial_badge');
        case 'suspended': return trans('super.status_suspended_badge');
        case 'expired': return trans('super.status_expired_badge');
        default: return status;
    }
};

onMounted(() => {
    fetchTenants();
});
</script>
