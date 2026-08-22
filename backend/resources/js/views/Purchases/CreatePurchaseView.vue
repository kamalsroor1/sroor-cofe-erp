<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <router-link
            to="/purchases"
            class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-xs border border-slate-300 dark:border-slate-700 shrink-0"
            :title="$t('common.back')"
          >
            <ArrowRight class="w-5 h-5" />
          </router-link>
          <div>
            <h1 class="text-xl font-black text-slate-900 dark:text-white">
              {{ $t('purchases.new_purchase') }}
            </h1>
            <p class="text-xs text-slate-400 font-bold">
              {{ $t('purchases.create_subtitle') }}
            </p>
          </div>
        </div>
      </div>

      <form @submit.prevent="submitPurchase" class="space-y-6">
        <!-- Supplier & Metadata Card -->
        <div class="p-5 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-lg space-y-4">
          <h2 class="text-xs font-black text-amber-400 flex items-center gap-2">
            <Factory class="w-4 h-4" />
            <span>{{ $t('purchases.supplier_po_section') }}</span>
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Supplier Select -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('purchases.supplier') }} <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.supplier_id"
                required
                class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
              >
                <option value="">{{ $t('purchases.select_supplier') }}</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">
                  {{ s.name }} {{ s.company_name ? `(${s.company_name})` : '' }}
                </option>
              </select>
            </div>

            <!-- Purchase Date -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('purchases.purchase_date') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.purchase_date"
                type="date"
                required
                class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
              >
            </div>

            <!-- Supplier Invoice Ref -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('purchases.supplier_invoice_ref_label') }}
              </label>
              <input
                v-model="form.supplier_invoice_ref"
                type="text"
                class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
                placeholder="INV-SUP-1234..."
              >
            </div>
          </div>
        </div>

        <!-- Items Table Card -->
        <div class="p-5 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-lg space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-xs font-black text-amber-400 flex items-center gap-2">
              <Package class="w-4 h-4" />
              <span>{{ $t('purchases.supply_items_section') }}</span>
            </h2>

            <button
              type="button"
              @click="addItemLine"
              class="px-3 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
            >
              <Plus class="w-3.5 h-3.5" />
              <span>{{ $t('purchases.add_item_line') }}</span>
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                  <th class="p-3 text-start font-bold">{{ $t('purchases.item_material') }}</th>
                  <th class="p-3 text-center font-bold w-28">{{ $t('common.quantity') }}</th>
                  <th class="p-3 text-center font-bold w-32">{{ $t('inventory.purchase_price') }}</th>
                  <th class="p-3 text-end font-bold w-32">{{ $t('common.total') }}</th>
                  <th class="p-3 text-center font-bold w-12"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/50">
                <tr v-for="(line, idx) in form.items" :key="idx" class="hover:bg-slate-100 dark:hover:bg-slate-900/30">
                  <td class="p-2.5">
                    <select
                      v-model="line.item_id"
                      @change="onItemSelect(line)"
                      required
                      class="w-full h-10 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                    >
                      <option value="">{{ $t('purchases.select_item_from_list') }}</option>
                      <option v-for="it in availableItems" :key="it.id" :value="it.id">
                        {{ it.name }} ({{ it.code || $t('purchases.no_code') }}) - {{ it.unit }}
                      </option>
                    </select>
                  </td>

                  <td class="p-2.5">
                    <input
                      v-model="line.quantity"
                      type="number"
                      step="0.001"
                      min="0.001"
                      required
                      class="w-full h-10 px-2 text-center bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-amber-400 font-mono font-bold focus:ring-2 focus:ring-theme-primary focus:outline-none"
                      placeholder="1.000"
                    >
                  </td>

                  <td class="p-2.5">
                    <input
                      v-model="line.cost_price"
                      type="number"
                      step="0.001"
                      min="0"
                      required
                      class="w-full h-10 px-2 text-center bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-emerald-400 font-mono font-bold focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                      placeholder="0.00"
                    >
                  </td>

                  <td class="p-2.5 text-end font-mono font-black text-white text-sm">
                    {{ formatMoney((parseFloat(line.quantity) || 0) * (parseFloat(line.cost_price) || 0)) }} {{ $t('common.currency') }}
                  </td>

                  <td class="p-2.5 text-center">
                    <button
                      type="button"
                      @click="removeItemLine(idx)"
                      :disabled="form.items.length <= 1"
                      class="p-2 text-slate-500 hover:text-rose-400 rounded-lg transition-colors disabled:opacity-20 cursor-pointer"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Financial Summary Card -->
        <div class="p-5 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-lg grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div class="space-y-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('purchases.invoice_notes') }}
              </label>
              <textarea
                v-model="form.notes"
                rows="3"
                class="w-full p-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                :placeholder="$t('purchases.invoice_notes_placeholder')"
              ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">
                  {{ $t('purchases.paid_to_supplier') }}
                </label>
                <input
                  v-model="form.paid_amount"
                  type="number"
                  step="0.001"
                  min="0"
                  class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-emerald-400 font-mono font-bold focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                  placeholder="0.00"
                >
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">
                  {{ $t('purchases.discount_earned') }}
                </label>
                <input
                  v-model="form.discount_amount"
                  type="number"
                  step="0.001"
                  min="0"
                  class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-rose-400 font-mono font-bold focus:ring-2 focus:ring-rose-500 focus:outline-none"
                  placeholder="0.00"
                >
              </div>
            </div>
          </div>

          <!-- Total Calculation Ledger -->
          <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2.5 font-mono text-xs self-center">
            <div class="flex justify-between text-slate-300 font-sans font-tajawal">
              <span>{{ $t('purchases.items_total_value') }}</span>
              <span class="font-mono font-bold">{{ formatMoney(subtotal) }} {{ $t('common.currency') }}</span>
            </div>
            <div v-if="discount > 0" class="flex justify-between text-rose-400 font-sans font-tajawal">
              <span>{{ $t('purchases.discount_earned') }}</span>
              <span class="font-mono font-bold">-{{ formatMoney(discount) }} {{ $t('common.currency') }}</span>
            </div>
            <div class="flex justify-between text-base font-black text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-sans font-tajawal">
              <span>{{ $t('invoices.net_invoice') }}</span>
              <span class="font-mono text-emerald-400">{{ formatMoney(netTotal) }} {{ $t('common.currency') }}</span>
            </div>
            <div class="flex justify-between text-xs font-bold font-sans font-tajawal" :class="remaining > 0 ? 'text-rose-400' : 'text-slate-400'">
              <span>{{ $t('purchases.remaining_on_company') }}</span>
              <span class="font-mono">{{ formatMoney(remaining) }} {{ $t('common.currency') }}</span>
            </div>
          </div>
        </div>

        <!-- Submit Actions -->
        <div class="flex items-center justify-end gap-3">
          <router-link
            to="/purchases"
            class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
          >
            {{ $t('common.cancel') }}
          </router-link>

          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-7 py-2.5 bg-theme-gradient text-white font-black shadow-theme-primary rounded-xl text-xs font-black shadow-lg shadow-theme-primary disabled:opacity-50 cursor-pointer flex items-center gap-2"
          >
            <span v-if="isSubmitting" class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
            <span>{{ $t('purchases.confirm_and_supply_btn') }}</span>
          </button>
        </div>
      </form>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    ArrowRight,
    Factory,
    Package,
    Plus,
    Trash2
} from 'lucide-vue-next';

const router = useRouter();
const route = useRoute();

const suppliers = ref([]);
const availableItems = ref([]);
const isSubmitting = ref(false);

const form = reactive({
    supplier_id: '',
    purchase_date: new Date().toISOString().split('T')[0],
    supplier_invoice_ref: '',
    paid_amount: '0.000',
    discount_amount: '0.000',
    payment_method: 'cash',
    notes: '',
    items: [
        { item_id: '', quantity: '1.000', cost_price: '0.000' }
    ],
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => {
        const q = parseFloat(item.quantity) || 0;
        const c = parseFloat(item.cost_price) || 0;
        return sum + (q * c);
    }, 0);
});

const discount = computed(() => parseFloat(form.discount_amount) || 0);
const paid = computed(() => parseFloat(form.paid_amount) || 0);
const netTotal = computed(() => Math.max(0, subtotal.value - discount.value));
const remaining = computed(() => Math.max(0, netTotal.value - paid.value));

const addItemLine = () => {
    form.items.push({ item_id: '', quantity: '1.000', cost_price: '0.000' });
};

const removeItemLine = (idx) => {
    if (form.items.length > 1) {
        form.items.splice(idx, 1);
    }
};

const onItemSelect = (line) => {
    const item = availableItems.value.find(it => it.id === parseInt(line.item_id, 10));
    if (item) {
        line.cost_price = item.cost_price?.toString() || '0.000';
    }
};

const loadInitialData = async () => {
    try {
        const [supRes, itemRes] = await Promise.all([
            api.get('/suppliers', { params: { per_page: 100 } }),
            api.get('/items', { params: { per_page: 200 } }),
        ]);

        suppliers.value = supRes.data?.data || [];
        availableItems.value = itemRes.data?.data || [];

        // Check for smart reorder prefill
        if (route.query.prefill) {
            try {
                const prefilled = JSON.parse(route.query.prefill);
                if (Array.isArray(prefilled) && prefilled.length > 0) {
                    form.items = prefilled.map(p => ({
                        item_id: p.item_id || p.id,
                        quantity: (p.quantity || p.suggested_reorder_qty || 10).toString(),
                        cost_price: (p.cost_price || 0).toString(),
                    }));
                }
            } catch (err) {
                console.error('Error parsing prefill params:', err);
            }
        }
    } catch (error) {
        console.error('Failed to load form dependencies:', error);
    }
};

const submitPurchase = async () => {
    if (!form.supplier_id) {
        Swal.fire({ icon: 'warning', title: trans('common.warning'), text: trans('purchases.select_supplier_warning') });
        return;
    }

    const invalidLine = form.items.find(it => !it.item_id || parseFloat(it.quantity) <= 0);
    if (invalidLine) {
        Swal.fire({ icon: 'warning', title: trans('common.warning'), text: trans('purchases.invalid_line_warning') });
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            ...form,
            items: form.items.map(it => ({
                item_id: parseInt(it.item_id, 10),
                quantity: parseFloat(it.quantity),
                unit_cost: parseFloat(it.cost_price),
            })),
        };

        const response = await api.post('/purchases', payload);
        Swal.fire({
            icon: 'success',
            title: trans('purchases.supply_confirmed_title'),
            text: response.data?.message || trans('purchases.supply_confirmed_msg'),
        });
        router.push({ name: 'purchases.index' });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || trans('common.error'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(loadInitialData);
</script>
