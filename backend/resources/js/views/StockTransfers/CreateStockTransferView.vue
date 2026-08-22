<template>
  <div class="max-w-4xl mx-auto space-y-6 font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('inventory.new_transfer')"
        :subtitle="$t('inventory.transfers_subtitle')"
        :icon="'🚚'"
      >
        <template #actions>
          <router-link
            to="/stock-transfers"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
          >
            <ArrowRight class="w-4 h-4" />
            <span>{{ $t('inventory.back_to_transfers') }}</span>
          </router-link>
        </template>
      </PageHeader>

      <form @submit.prevent="submitTransfer" class="space-y-6">
        <!-- Stores & Date Selection Card -->
        <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
          <h2 class="text-xs font-bold text-slate-400 border-b border-slate-800 pb-2 flex items-center gap-2">
            <span>🏢</span>
            <span>{{ $t('inventory.branches_and_date_section') }}</span>
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- From Store -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-slate-300">
                {{ $t('inventory.from_store_label') }} <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.from_store_id"
                required
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
                <option :value="null" disabled>{{ $t('inventory.choose_source_store') }}</option>
                <option v-for="s in stores" :key="s.id" :value="s.id">
                  {{ s.name }} ({{ s.type === 'warehouse' ? $t('inventory.store_type_warehouse_short') : $t('inventory.store_type_branch_short') }})
                </option>
              </select>
            </div>

            <!-- To Store -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-slate-300">
                {{ $t('inventory.to_store_label') }} <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.to_store_id"
                required
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
                <option :value="null" disabled>{{ $t('inventory.choose_dest_store') }}</option>
                <option v-for="s in stores" :key="s.id" :value="s.id" :disabled="s.id === form.from_store_id">
                  {{ s.name }} ({{ s.type === 'warehouse' ? $t('inventory.store_type_warehouse_short') : $t('inventory.store_type_branch_short') }})
                </option>
              </select>
            </div>

            <!-- Transfer Date -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-slate-300">
                {{ $t('inventory.transfer_date_label') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.transfer_date"
                type="date"
                required
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
            </div>

            <!-- Notes -->
            <div class="sm:col-span-3 space-y-1">
              <label class="block text-xs font-bold text-slate-300">{{ $t('inventory.blend_notes') }}</label>
              <input
                v-model="form.notes"
                type="text"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                :placeholder="$t('inventory.transfer_notes_placeholder')"
              >
            </div>
          </div>
        </div>

        <!-- Items Selection Card -->
        <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-2">
            <h2 class="text-xs font-bold text-slate-400 flex items-center gap-2">
              <span>📦</span>
              <span>{{ $t('inventory.transferred_items_section') }}</span>
            </h2>
          </div>

          <!-- Add Item Row -->
          <div class="flex items-center gap-2">
            <select
              v-model="selectedItemToAdd"
              class="flex-1 h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
            >
              <option :value="null">{{ $t('inventory.select_item_to_transfer') }}</option>
              <option v-for="it in items" :key="it.id" :value="it">
                {{ it.name }} ({{ it.code || '—' }}) — {{ $t('inventory.current_stock') }}: {{ it.current_stock }} {{ it.unit }}
              </option>
            </select>

            <button
              type="button"
              @click="addItemRow"
              :disabled="!selectedItemToAdd"
              class="px-4 h-10 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black transition disabled:opacity-30 cursor-pointer shrink-0"
            >
              {{ $t('returns.add_item_btn') }}
            </button>
          </div>

          <!-- Items Table -->
          <div v-if="form.items.length > 0" class="border border-slate-800 rounded-xl overflow-hidden">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-900 text-slate-400 border-b border-slate-800">
                  <th class="p-3 text-start font-bold">{{ $t('inventory.item_name') }}</th>
                  <th class="p-3 text-start font-bold">{{ $t('inventory.code') }}</th>
                  <th class="p-3 text-center font-bold w-36">{{ $t('inventory.transferred_qty_col') }}</th>
                  <th class="p-3 text-center font-bold w-12"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/50 font-sans">
                <tr v-for="(item, idx) in form.items" :key="item.item_id" class="hover:bg-slate-100 dark:hover:bg-slate-900/40">
                  <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">
                    <div>{{ item.name }}</div>
                  </td>
                  <td class="p-3 font-mono text-slate-400">
                    {{ item.code || '—' }}
                  </td>
                  <td class="p-3 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                      <input
                        v-model.number="item.quantity"
                        type="number"
                        step="0.001"
                        min="0.001"
                        class="w-24 h-8 px-2 text-center bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold text-amber-400 focus:outline-none"
                      >
                      <span class="text-slate-400 text-[10px] font-tajawal">{{ item.unit }}</span>
                    </div>
                  </td>
                  <td class="p-3 text-center">
                    <button
                      type="button"
                      @click="removeItemRow(idx)"
                      class="p-1.5 text-slate-500 hover:text-rose-400 rounded-lg transition cursor-pointer"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="p-8 text-center text-slate-500 text-xs font-bold border border-dashed border-slate-800 rounded-xl font-tajawal">
            {{ $t('inventory.no_items_in_transfer_prompt') }}
          </div>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="isSubmitting || form.items.length === 0"
          class="w-full h-12 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-2xl font-black text-xs shadow-xl shadow-amber-500/20 transition active:scale-[0.99] disabled:opacity-40 cursor-pointer flex items-center justify-center gap-2"
        >
          <span v-if="isSubmitting" class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
          <Truck v-else class="w-4 h-4" />
          <span>{{ $t('inventory.execute_transfer_now_btn') }}</span>
        </button>
      </form>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '../../Components/Common/PageHeader.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    ArrowRight,
    Trash2,
    Truck
} from 'lucide-vue-next';

const router = useRouter();

const stores = ref([]);
const items = ref([]);

const isSubmitting = ref(false);
const selectedItemToAdd = ref(null);

const form = reactive({
    from_store_id: null,
    to_store_id: null,
    transfer_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [],
});

const loadDependencies = async () => {
    try {
        const [storesRes, itemsRes] = await Promise.all([
            api.get('/stores'),
            api.get('/items?per_page=100'),
        ]);

        stores.value = storesRes.data?.data || [];
        items.value = itemsRes.data?.data || [];

        if (stores.value.length >= 2) {
            form.from_store_id = stores.value[0].id;
            form.to_store_id = stores.value[1].id;
        }
    } catch (error) {
        console.error('Failed to load transfer dependencies:', error);
    }
};

const addItemRow = () => {
    if (!selectedItemToAdd.value) return;
    const it = selectedItemToAdd.value;

    if (form.items.some(i => i.item_id === it.id)) {
        Swal.fire({ icon: 'info', title: trans('common.warning'), text: trans('inventory.item_already_in_transfer') });
        return;
    }

    form.items.push({
        item_id: it.id,
        name: it.name,
        code: it.code,
        unit: it.unit || 'كجم',
        quantity: 1,
    });

    selectedItemToAdd.value = null;
};

const removeItemRow = (idx) => {
    form.items.splice(idx, 1);
};

const submitTransfer = async () => {
    if (form.from_store_id === form.to_store_id) {
        Swal.fire({ icon: 'error', title: trans('common.error'), text: trans('inventory.same_store_transfer_error') });
        return;
    }
    if (form.items.length === 0) {
        Swal.fire({ icon: 'warning', title: trans('common.warning'), text: trans('inventory.add_at_least_one_item_transfer') });
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            from_store_id: form.from_store_id,
            to_store_id: form.to_store_id,
            transfer_date: form.transfer_date,
            notes: form.notes || null,
            items: form.items.map(it => ({
                item_id: it.item_id,
                quantity: parseFloat(it.quantity),
            })),
        };

        const response = await api.post('/transfers', payload);
        Swal.fire({
            icon: 'success',
            title: trans('inventory.transfer_executed_title'),
            text: response.data?.message || trans('inventory.transfer_executed_msg'),
            timer: 1500,
            showConfirmButton: false,
        });

        router.push('/stock-transfers');
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || trans('inventory.transfer_execution_failed'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    loadDependencies();
});
</script>
