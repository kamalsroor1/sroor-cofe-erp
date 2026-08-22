<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-950/80 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
            <Layers class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ $t('super.plans_page_title') }}</h1>
            <p class="text-xs text-slate-400">{{ $t('super.plans_page_subtitle') }}</p>
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

          <router-link
            to="/super-admin/tenants"
            class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/20 flex items-center gap-2 transition"
          >
            <Building2 class="w-4 h-4" />
            <span>{{ $t('super.tenants_management') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-16 text-center">
        <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400">{{ $t('super.loading_plans') }}</p>
      </div>

      <!-- Plans Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div
          v-for="plan in plans"
          :key="plan.id"
          class="bg-white dark:bg-slate-950/80 border rounded-3xl p-6 shadow-xl flex flex-col justify-between relative space-y-6"
          :class="plan.is_popular ? 'border-amber-500/50 ring-1 ring-amber-500/30' : 'border-slate-200 dark:border-slate-800'"
        >
          <!-- Popular Badge -->
          <div v-if="plan.is_popular" class="absolute -top-3 start-1/2 -translate-x-1/2 px-3 py-0.5 bg-amber-500 text-slate-950 font-black text-[10px] rounded-full uppercase tracking-wider">
            {{ $t('super.popular_badge') }}
          </div>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-base font-black text-white">{{ plan.name }}</h3>
                <span class="text-[10px] text-slate-500 font-mono">Slug: {{ plan.slug }}</span>
              </div>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                :class="plan.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
              >
                {{ plan.is_active ? $t('super.plan_enabled') : $t('super.plan_disabled') }}
              </span>
            </div>

            <!-- Pricing -->
            <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-200 dark:border-slate-800 text-center space-y-1">
              <div class="text-2xl font-black text-amber-400 font-mono">
                {{ formatMoney(plan.price_monthly) }} <span class="text-xs text-slate-400 font-tajawal">{{ $t('common.currency') }} / {{ $t('super.per_month') }}</span>
              </div>
              <div class="text-xs text-slate-400 font-mono">
                {{ $t('super.yearly_rate', { amount: formatMoney(plan.price_yearly) }) }}
              </div>
            </div>

            <!-- Limits List -->
            <div class="space-y-2 text-xs text-slate-300">
              <div class="flex items-center justify-between py-1.5 border-b border-slate-200 dark:border-slate-800/80">
                <span class="text-slate-400">{{ $t('super.max_users_label') }}</span>
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ plan.max_users }}</span>
              </div>
              <div class="flex items-center justify-between py-1.5 border-b border-slate-200 dark:border-slate-800/80">
                <span class="text-slate-400">{{ $t('super.max_stores_label') }}</span>
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ plan.max_stores }}</span>
              </div>
              <div class="flex items-center justify-between py-1.5 border-b border-slate-200 dark:border-slate-800/80">
                <span class="text-slate-400">{{ $t('super.max_items_label') }}</span>
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ plan.max_items }}</span>
              </div>
              <div class="flex items-center justify-between py-1.5">
                <span class="text-slate-400">{{ $t('super.monthly_invoices_label') }}</span>
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ plan.max_invoices_per_month }}</span>
              </div>
            </div>
          </div>

          <!-- Edit Button -->
          <button
            @click="openEditModal(plan)"
            class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-900 dark:text-slate-200 hover:text-white font-bold text-xs rounded-xl shadow transition cursor-pointer"
          >
            {{ $t('super.edit_prices_and_limits_btn') }}
          </button>
        </div>
      </div>

      <!-- Edit Plan Modal -->
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <h2 class="text-base font-black text-white">{{ $t('super.edit_plan_modal_title', { name: editForm.name }) }}</h2>
            <button @click="showEditModal = false" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
          </div>

          <form @submit.prevent="submitEditPlan" class="space-y-3.5 text-xs font-tajawal">
            <div>
              <label class="block text-slate-400 font-bold mb-1">{{ $t('super.plan_name_label') }}</label>
              <input
                v-model="editForm.name"
                required
                type="text"
                class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-theme-primary"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.monthly_price_label') }}</label>
                <input
                  v-model="editForm.price_monthly"
                  required
                  type="number"
                  step="0.01"
                  class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-theme-primary"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.yearly_price_label') }}</label>
                <input
                  v-model="editForm.price_yearly"
                  required
                  type="number"
                  step="0.01"
                  class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-theme-primary"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.users_limit_label') }}</label>
                <input
                  v-model="editForm.max_users"
                  required
                  type="number"
                  class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-white font-mono"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.stores_limit_label') }}</label>
                <input
                  v-model="editForm.max_stores"
                  required
                  type="number"
                  class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-white font-mono"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.items_limit_label') }}</label>
                <input
                  v-model="editForm.max_items"
                  required
                  type="number"
                  class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-white font-mono"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">{{ $t('super.invoices_limit_label') }}</label>
                <input
                  v-model="editForm.max_invoices_per_month"
                  required
                  type="number"
                  class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-white font-mono"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2">
              <label class="flex items-center gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 cursor-pointer">
                <input type="checkbox" v-model="editForm.is_active" class="w-4 h-4 rounded text-amber-500 focus:ring-theme-primary" />
                <span class="text-slate-300 font-bold">{{ $t('super.plan_active_checkbox') }}</span>
              </label>

              <label class="flex items-center gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 cursor-pointer">
                <input type="checkbox" v-model="editForm.is_popular" class="w-4 h-4 rounded text-amber-500 focus:ring-theme-primary" />
                <span class="text-slate-300 font-bold">{{ $t('super.popular_plan_checkbox') }}</span>
              </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
              <button
                type="button"
                @click="showEditModal = false"
                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded-xl font-bold cursor-pointer"
              >
                {{ $t('common.cancel') }}
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="px-5 py-2 bg-theme-gradient text-white font-black shadow-theme-primary font-black rounded-xl shadow-lg transition disabled:opacity-50 cursor-pointer"
              >
                {{ isSubmitting ? $t('common.loading') : $t('common.save') }}
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
    Layers,
    Crown,
    Building2
} from 'lucide-vue-next';

const plans = ref([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const showEditModal = ref(false);
const selectedPlan = ref(null);

const editForm = ref({
    name: '',
    price_monthly: 0,
    price_yearly: 0,
    max_users: 1,
    max_stores: 1,
    max_items: 100,
    max_invoices_per_month: 1000,
    is_active: true,
    is_popular: false,
    features: {},
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const fetchPlans = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/super-admin/plans');
        plans.value = res.data?.data?.plans || res.data?.plans || [];
    } catch (e) {
        console.error('Failed to load plans:', e);
    } finally {
        isLoading.value = false;
    }
};

const openEditModal = (plan) => {
    selectedPlan.value = plan;
    editForm.value = {
        name: plan.name,
        price_monthly: plan.price_monthly,
        price_yearly: plan.price_yearly,
        max_users: plan.max_users,
        max_stores: plan.max_stores,
        max_items: plan.max_items,
        max_invoices_per_month: plan.max_invoices_per_month,
        is_active: !!plan.is_active,
        is_popular: !!plan.is_popular,
        features: plan.features || {},
    };
    showEditModal.value = true;
};

const submitEditPlan = async () => {
    if (!selectedPlan.value) return;
    isSubmitting.value = true;
    try {
        await api.put(`/super-admin/plans/${selectedPlan.value.id}`, editForm.value);
        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: trans('super.plan_updated_success', { name: selectedPlan.value.name }),
            timer: 1500,
            showConfirmButton: false,
        });
        showEditModal.value = false;
        fetchPlans();
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: e.response?.data?.message || trans('common.error'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    fetchPlans();
});
</script>
