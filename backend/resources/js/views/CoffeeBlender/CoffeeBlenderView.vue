<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('inventory.blender_title')"
        :subtitle="$t('inventory.blender_subtitle')"
        :icon="'📦'"
      >
        <template #actions>
          <router-link
            to="/invoices"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
          >
            <ArrowRight class="w-4 h-4" />
            <span>{{ $t('nav.invoices_log') }}</span>
          </router-link>
        </template>
      </PageHeader>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: Blender Studio Workspace (Col span 8) -->
        <div class="lg:col-span-8 space-y-5">
          <!-- Blend Settings Card -->
          <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <h2 class="text-xs font-bold text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
              <span>⚙️</span>
              <span>{{ $t('inventory.blend_specs_title') }}</span>
            </h2>

            <div class="space-y-3">
              <!-- Blend Name -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.blend_name') }}</label>
                <input
                  v-model="blendName"
                  type="text"
                  class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  :placeholder="$t('inventory.blend_name_placeholder')"
                >
              </div>

              <!-- Target Weight Presets -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.target_weight') }}</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  <button
                    v-for="w in presetWeights"
                    :key="w.value"
                    type="button"
                    @click="setTargetWeight(w.value)"
                    class="py-2.5 px-3 rounded-xl border text-xs font-bold transition cursor-pointer text-center"
                    :class="targetWeightGrams === w.value ? 'bg-theme-primary text-white font-black border-theme-primary shadow-md' : 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-200 dark:hover:bg-slate-800'"
                  >
                    {{ w.label }}
                  </button>
                </div>
              </div>

              <!-- Custom Weight, Roast, Grind -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                <div class="space-y-1">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-400">{{ $t('inventory.custom_weight') }}</label>
                  <input
                    v-model.number="targetWeightGrams"
                    type="number"
                    min="1"
                    step="1"
                    class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  >
                </div>

                <div class="space-y-1">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-400">{{ $t('inventory.roast_type') }}</label>
                  <select
                    v-model="roastType"
                    class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  >
                    <option value="فاتح">{{ $t('inventory.roast_light') }}</option>
                    <option value="وسط">{{ $t('inventory.roast_medium') }}</option>
                    <option value="غامق">{{ $t('inventory.roast_dark') }}</option>
                    <option value="محروق / دبل">{{ $t('inventory.roast_double') }}</option>
                  </select>
                </div>

                <div class="space-y-1">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-400">{{ $t('inventory.grind_level') }}</label>
                  <select
                    v-model="grindLevel"
                    class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  >
                    <option value="تركي ناعم">{{ $t('inventory.grind_turkish') }}</option>
                    <option value="إسبريسو">{{ $t('inventory.grind_espresso') }}</option>
                    <option value="فرينش بريس">{{ $t('inventory.grind_french_press') }}</option>
                    <option value="حبوب بدون طحن">{{ $t('inventory.grind_beans') }}</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Components Formulation Card -->
          <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
              <h2 class="text-xs font-bold text-slate-700 dark:text-slate-400 flex items-center gap-2">
                <span>🫘</span>
                <span>{{ $t('inventory.raw_beans_components') }}</span>
              </h2>
              <span
                class="px-2.5 py-0.5 rounded-full text-xs font-mono font-black border"
                :class="totalPercentage === 100 ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
              >
                {{ $t('inventory.total_ratio_badge', { pct: totalPercentage }) }}
              </span>
            </div>

            <!-- Add Component Row -->
            <div class="flex items-center gap-2">
              <select
                v-model="selectedItemIdToAdd"
                class="flex-1 h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
              >
                <option :value="null">{{ $t('inventory.select_blend_item_prompt') }}</option>
                <option v-for="it in items" :key="it.id" :value="it">
                  {{ it.name }} ({{ it.code || '—' }}) — {{ $t('inventory.retail_price') }}: {{ it.price_retail || it.selling_price }} {{ $t('common.currency') }} | {{ $t('inventory.current_stock') }}: {{ it.current_stock }} {{ it.unit }}
                </option>
              </select>

              <button
                type="button"
                @click="addComponentRow"
                :disabled="!selectedItemIdToAdd"
                class="px-4 h-10 bg-theme-primary hover:bg-theme-hover text-white rounded-xl text-xs font-black transition disabled:opacity-30 cursor-pointer shrink-0 shadow-sm"
              >
                + {{ $t('common.add') }}
              </button>
            </div>

            <!-- Components List -->
            <div class="space-y-2.5">
              <div
                v-for="(comp, idx) in calculatedComponents"
                :key="comp.item_id"
                class="p-3.5 bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs"
              >
                <div class="sm:w-1/3 min-w-0">
                  <div class="font-bold text-slate-900 dark:text-white truncate">{{ comp.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono">
                    {{ formatMoney(comp.selling_price) }} {{ $t('common.currency') }} / {{ $t('inventory.unit_weight_short') }} ({{ $t('inventory.current_stock') }}: {{ comp.current_stock }} {{ $t('inventory.unit_weight_short') }})
                  </div>
                </div>

                <!-- Percentage Slider -->
                <div class="sm:w-1/3 flex items-center gap-2.5">
                  <input
                    v-model.number="components[idx].percentage"
                    type="range"
                    min="0"
                    max="100"
                    step="5"
                    class="flex-1 h-2 bg-slate-200 dark:bg-slate-800 accent-theme-primary cursor-pointer rounded-lg"
                  >
                  <div class="flex items-center gap-1 shrink-0">
                    <input
                      v-model.number="components[idx].percentage"
                      type="number"
                      min="0"
                      max="100"
                      class="w-12 h-7 text-center bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold text-theme-primary focus:outline-none"
                    >
                    <span class="text-slate-500 dark:text-slate-400 text-[10px]">%</span>
                  </div>
                </div>

                <!-- Calculated Grams & Price -->
                <div class="sm:w-1/3 flex items-center justify-between sm:justify-end gap-3 font-mono">
                  <div class="text-end">
                    <div class="font-black text-theme-primary text-xs">{{ comp.grams }} {{ $t('inventory.unit_gram') }}</div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ formatMoney(comp.price) }} {{ $t('common.currency') }}</div>
                  </div>

                  <button
                    type="button"
                    @click="removeComponentRow(idx)"
                    class="p-1.5 text-slate-500 hover:text-rose-400 rounded-lg transition cursor-pointer"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Extra Spices (Cardamom) -->
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.cardamom_spices') }}</label>
                <input
                  v-model.number="cardamomGrams"
                  type="number"
                  min="0"
                  step="1"
                  class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-mono font-bold text-emerald-400 focus:outline-none"
                  placeholder="0"
                >
              </div>

              <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.blend_notes') }}</label>
                <input
                  v-model="notes"
                  type="text"
                  class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none"
                  :placeholder="$t('inventory.notes_placeholder')"
                >
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Financial Breakdown & Direct Cashier (Col span 4) -->
        <div class="lg:col-span-4 space-y-5">
          <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4 sticky top-6">
            <h2 class="text-xs font-bold text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
              <span>📊</span>
              <span>{{ $t('inventory.blend_cost_summary') }}</span>
            </h2>

            <div class="space-y-2.5 font-mono text-xs">
              <div class="flex justify-between text-slate-400 font-sans">
                <span>{{ $t('inventory.total_weight_label') }}</span>
                <span class="font-mono text-white font-bold">{{ targetWeightGrams }} {{ $t('inventory.unit_gram') }} ({{ (targetWeightGrams / 1000).toFixed(3) }} {{ $t('inventory.unit_weight_short') }})</span>
              </div>

              <div class="flex justify-between text-slate-400 font-sans">
                <span>{{ $t('inventory.estimated_raw_cost') }}</span>
                <span class="font-mono text-rose-400">{{ formatMoney(totalCalculatedCost) }} {{ $t('common.currency') }}</span>
              </div>

              <div class="flex justify-between text-base font-black text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-sans">
                <span>{{ $t('inventory.suggested_retail_price') }}:</span>
                <span class="font-mono text-emerald-400 text-lg">{{ formatMoney(totalCalculatedPrice) }} {{ $t('common.currency') }}</span>
              </div>

              <div class="flex justify-between text-xs text-slate-400 font-sans">
                <span>{{ $t('inventory.profit_margin') }}:</span>
                <span class="font-mono font-bold text-amber-400">{{ profitMargin }}% ({{ formatMoney(totalCalculatedPrice - totalCalculatedCost) }} {{ $t('common.currency') }})</span>
              </div>
            </div>

            <!-- Customer Selection -->
            <div class="space-y-1.5 pt-3 border-t border-slate-200 dark:border-slate-800">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 font-sans">
                {{ $t('contacts.customer') }} <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="selectedCustomerId"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
              >
                <option v-for="c in customers" :key="c.id" :value="c.id">
                  {{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}
                </option>
              </select>
            </div>

            <!-- Issue Invoice Button -->
            <button
              type="button"
              @click="submitBlendInvoice"
              :disabled="isSubmitting || components.length === 0"
              class="w-full h-12 bg-theme-gradient text-white shadow-theme-primary rounded-2xl font-black text-xs shadow-xl shadow-theme-primary transition active:scale-[0.99] disabled:opacity-40 cursor-pointer flex items-center justify-center gap-2 font-sans"
            >
              <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <Zap v-else class="w-4 h-4 fill-white text-white" />
              <span>{{ $t('inventory.blend_invoice_btn') }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '../../Components/Common/PageHeader.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    ArrowRight,
    Trash2,
    Zap
} from 'lucide-vue-next';

const router = useRouter();

const items = ref([]);
const customers = ref([]);

const blendName = ref('تركيبة مجمعة مخصصة');
const targetWeightGrams = ref(250);
const selectedCustomerId = ref(null);
const selectedItemIdToAdd = ref(null);
const roastType = ref('وسط');
const grindLevel = ref('تركي ناعم');
const cardamomGrams = ref(0);
const notes = ref('');

const isSubmitting = ref(false);

const presetWeights = computed(() => [
    { label: trans('inventory.weight_125'), value: 125 },
    { label: trans('inventory.weight_250'), value: 250 },
    { label: trans('inventory.weight_500'), value: 500 },
    { label: trans('inventory.weight_1000'), value: 1000 },
]);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const components = ref([]);

const setTargetWeight = (grams) => {
    targetWeightGrams.value = grams;
};

const calculatedComponents = computed(() => {
    const target = Number(targetWeightGrams.value) || 0;
    return components.value.map(c => {
        const pct = Number(c.percentage) || 0;
        const grams = (target * pct) / 100;
        const kg = grams / 1000;
        const cost = kg * c.cost_price;
        const price = kg * c.selling_price;

        return {
            ...c,
            grams: Number(grams.toFixed(1)),
            kg: Number(kg.toFixed(3)),
            cost: Number(cost.toFixed(2)),
            price: Number(price.toFixed(2)),
        };
    });
});

const totalPercentage = computed(() => {
    return components.value.reduce((sum, c) => sum + (Number(c.percentage) || 0), 0);
});

const totalCalculatedCost = computed(() => {
    let cost = calculatedComponents.value.reduce((sum, c) => sum + c.cost, 0);
    if (cardamomGrams.value > 0) {
        cost += (Number(cardamomGrams.value) * 1.5);
    }
    return Number(cost.toFixed(2));
});

const totalCalculatedPrice = computed(() => {
    let price = calculatedComponents.value.reduce((sum, c) => sum + c.price, 0);
    if (cardamomGrams.value > 0) {
        price += (Number(cardamomGrams.value) * 2.5);
    }
    return Number(price.toFixed(2));
});

const profitMargin = computed(() => {
    if (totalCalculatedPrice.value <= 0) return 0;
    const profit = totalCalculatedPrice.value - totalCalculatedCost.value;
    return Number(((profit / totalCalculatedPrice.value) * 100).toFixed(1));
});

const loadDependencies = async () => {
    try {
        const [itemsRes, custRes] = await Promise.all([
            api.get('/items?per_page=100'),
            api.get('/customers?per_page=100'),
        ]);

        items.value = itemsRes.data?.data || [];
        customers.value = custRes.data?.data || [];

        if (customers.value.length > 0) {
            selectedCustomerId.value = customers.value[0].id;
        }

        // Initialize default formulation with first 2 items
        if (items.value.length >= 2) {
            components.value = [
                {
                    item_id: items.value[0].id,
                    name: items.value[0].name,
                    percentage: 60,
                    cost_price: Number(items.value[0].cost_price),
                    selling_price: Number(items.value[0].price_retail || items.value[0].selling_price),
                    current_stock: items.value[0].current_stock,
                },
                {
                    item_id: items.value[1].id,
                    name: items.value[1].name,
                    percentage: 40,
                    cost_price: Number(items.value[1].cost_price),
                    selling_price: Number(items.value[1].price_retail || items.value[1].selling_price),
                    current_stock: items.value[1].current_stock,
                },
            ];
        }
    } catch (error) {
        console.error('Failed to load blender dependencies:', error);
    }
};

const addComponentRow = () => {
    if (!selectedItemIdToAdd.value) return;
    const item = selectedItemIdToAdd.value;

    if (components.value.some(c => c.item_id === item.id)) {
        Swal.fire({ icon: 'info', title: trans('common.warning'), text: trans('inventory.item_already_added') });
        return;
    }

    components.value.push({
        item_id: item.id,
        name: item.name,
        percentage: 0,
        cost_price: Number(item.cost_price),
        selling_price: Number(item.price_retail || item.selling_price),
        current_stock: item.current_stock,
    });

    selectedItemIdToAdd.value = null;
};

const removeComponentRow = (idx) => {
    components.value.splice(idx, 1);
};

const submitBlendInvoice = async () => {
    if (components.value.length === 0) {
        Swal.fire({ icon: 'warning', title: trans('common.warning'), text: trans('inventory.blend_components_empty') });
        return;
    }

    if (totalPercentage.value !== 100) {
        const result = await Swal.fire({
            title: trans('inventory.ratio_warning_title'),
            text: trans('inventory.ratio_warning_text', { pct: totalPercentage.value }),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: trans('common.confirm'),
            cancelButtonText: trans('common.cancel'),
        });
        if (!result.isConfirmed) return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            blend_name: `${blendName.value}`,
            customer_id: selectedCustomerId.value,
            target_weight_grams: targetWeightGrams.value,
            roast_type: roastType.value,
            grind_level: grindLevel.value,
            cardamom_grams: cardamomGrams.value,
            notes: notes.value || null,
            components: calculatedComponents.value.map(c => ({
                item_id: c.item_id,
                grams: c.grams,
                unit_price: c.selling_price,
            })),
        };

        const response = await api.post('/coffee-blender/invoice', payload);
        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: response.data?.message || trans('inventory.blend_invoice_success'),
            timer: 1500,
            showConfirmButton: false,
        });

        router.push('/invoices');
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || error.response?.data?.message || trans('common.error'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    loadDependencies();
});
</script>
