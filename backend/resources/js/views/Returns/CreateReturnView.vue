<template>
  <SpaLayout>
    <div class="max-w-5xl mx-auto space-y-6 font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('returns.create_title')"
        :subtitle="$t('returns.create_subtitle')"
        :icon="'🔄'"
      >
        <template #actions>
          <router-link
            to="/returns"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
          >
            <ArrowRight class="w-4 h-4" />
            <span>العودة للمرتجعات</span>
          </router-link>
        </template>
      </PageHeader>

      <form @submit.prevent="submitReturn" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form (Col Span 2) -->
        <div class="lg:col-span-2 space-y-5">
          <!-- Return Type & Party Info -->
          <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <h2 class="text-xs font-bold text-slate-400 border-b border-slate-800 pb-2 flex items-center gap-2">
              <span>🔄</span>
              <span>نوع المرتجع والطرف المتعامل</span>
            </h2>

            <!-- Return Type Switcher -->
            <div class="grid grid-cols-2 gap-3">
              <button
                type="button"
                @click="onTypeChange('sales_return')"
                class="py-3 px-4 rounded-xl text-xs font-black transition border text-center cursor-pointer active:scale-95"
                :class="form.return_type === 'sales_return' ? 'bg-cyan-500 text-slate-950 border-cyan-400 shadow-md shadow-cyan-500/20' : 'bg-slate-900 text-slate-400 border-slate-800'"
              >
                <span>↩️ مرتجع مبيعات (من عميل)</span>
              </button>

              <button
                type="button"
                @click="onTypeChange('purchase_return')"
                class="py-3 px-4 rounded-xl text-xs font-black transition border text-center cursor-pointer active:scale-95"
                :class="form.return_type === 'purchase_return' ? 'bg-amber-500 text-slate-950 border-amber-400 shadow-md shadow-amber-500/20' : 'bg-slate-900 text-slate-400 border-slate-800'"
              >
                <span>↪️ مرتجع مشتريات (إلى مورد)</span>
              </button>
            </div>

            <!-- Fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
              <!-- Customer Field -->
              <div v-if="form.return_type === 'sales_return'" class="space-y-1">
                <label class="block text-xs font-bold text-slate-300">
                  العميل المسترجع منه <span class="text-rose-500">*</span>
                </label>
                <select
                  v-model="form.customer_id"
                  required
                  class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
                >
                  <option :value="null" disabled>اختر العميل...</option>
                  <option v-for="c in customers" :key="c.id" :value="c.id">
                    {{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}
                  </option>
                </select>
              </div>

              <!-- Supplier Field -->
              <div v-else class="space-y-1">
                <label class="block text-xs font-bold text-slate-300">
                  المورد المسترجع إليه <span class="text-rose-500">*</span>
                </label>
                <select
                  v-model="form.supplier_id"
                  required
                  class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
                >
                  <option :value="null" disabled>اختر المورد...</option>
                  <option v-for="s in suppliers" :key="s.id" :value="s.id">
                    {{ s.name }} {{ s.company_name ? `(${s.company_name})` : '' }}
                  </option>
                </select>
              </div>

              <!-- Return Date -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-300">
                  تاريخ المرتجع <span class="text-rose-500">*</span>
                </label>
                <input
                  v-model="form.return_date"
                  type="date"
                  required
                  class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
                >
              </div>

              <!-- Reason -->
              <div class="sm:col-span-2 space-y-1">
                <label class="block text-xs font-bold text-slate-300">سبب الإرجاع أو الملاحظات</label>
                <input
                  v-model="form.reason"
                  type="text"
                  class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                  placeholder="مثال: تلف بالعبوة، زيادة في الكمية، انتهاء صلاحية..."
                >
              </div>
            </div>
          </div>

          <!-- Items Selection Table -->
          <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
              <h2 class="text-xs font-bold text-slate-400 flex items-center gap-2">
                <span>📦</span>
                <span>أصناف المرتجع</span>
              </h2>
            </div>

            <!-- Add Item Row Selector -->
            <div class="flex items-center gap-2">
              <select
                v-model="selectedItemToAdd"
                class="flex-1 h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
                <option :value="null">اختر صنفاً لإضافته للمرتجع...</option>
                <option v-for="it in items" :key="it.id" :value="it">
                  {{ it.name }} ({{ it.code || '—' }}) — الرصيد: {{ it.current_stock }} {{ it.unit }}
                </option>
              </select>

              <button
                type="button"
                @click="addItemRow"
                :disabled="!selectedItemToAdd"
                class="px-4 h-10 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black transition disabled:opacity-30 cursor-pointer shrink-0"
              >
                + إضافة
              </button>
            </div>

            <!-- Items Table -->
            <div v-if="form.items.length > 0" class="border border-slate-800 rounded-xl overflow-hidden">
              <table class="w-full text-start text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-900 text-slate-400 border-b border-slate-800">
                    <th class="p-3 text-start font-bold">الصنف</th>
                    <th class="p-3 text-center font-bold w-28">الكمية</th>
                    <th class="p-3 text-end font-bold w-32">سعر الوحدة</th>
                    <th class="p-3 text-end font-bold w-32">الإجمالي</th>
                    <th class="p-3 text-center font-bold w-12"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50 font-sans">
                  <tr v-for="(item, idx) in form.items" :key="item.item_id" class="hover:bg-slate-900/40">
                    <td class="p-3 font-bold text-white">
                      <div>{{ item.name }}</div>
                      <span class="text-[10px] text-slate-500 font-mono">({{ item.unit }})</span>
                    </td>
                    <td class="p-3 text-center">
                      <input
                        v-model.number="item.quantity"
                        type="number"
                        step="0.001"
                        min="0.001"
                        class="w-20 h-8 px-2 text-center bg-slate-900 border border-slate-700 rounded-lg text-xs font-mono font-bold text-white focus:outline-none"
                      >
                    </td>
                    <td class="p-3 text-end">
                      <input
                        v-model.number="item.unit_price"
                        type="number"
                        step="0.001"
                        min="0"
                        class="w-24 h-8 px-2 text-end bg-slate-900 border border-slate-700 rounded-lg text-xs font-mono font-bold text-amber-400 focus:outline-none"
                      >
                    </td>
                    <td class="p-3 text-end font-mono font-bold text-rose-400">
                      {{ formatMoney(item.quantity * item.unit_price) }} ج.م
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

            <div v-else class="p-8 text-center text-slate-500 text-xs font-bold border border-dashed border-slate-800 rounded-xl">
              لم يتم إضافة أي أصناف للمرتجع بعد. اختر صنفاً من القائمة واضغط إضافة.
            </div>
          </div>
        </div>

        <!-- Sidebar Summary (Col Span 1) -->
        <div class="space-y-4">
          <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <h2 class="text-xs font-bold text-slate-400 border-b border-slate-800 pb-2 flex items-center gap-2">
              <span>📊</span>
              <span>الملخص المالي للمستند</span>
            </h2>

            <div class="space-y-2.5 font-mono text-xs">
              <div class="flex justify-between text-slate-300 font-sans">
                <span>عدد الأصناف:</span>
                <span class="font-mono text-white font-bold">{{ form.items.length }}</span>
              </div>

              <div class="flex justify-between text-base font-black text-white pt-2 border-t border-slate-800 font-sans">
                <span>إجمالي قيمة المرتجع:</span>
                <span class="font-mono text-rose-400">{{ formatMoney(netTotal) }} ج.م</span>
              </div>

              <!-- Refund cash from drawer -->
              <div class="pt-2 border-t border-slate-800 space-y-1">
                <label class="block text-xs font-bold text-slate-300 font-sans">
                  المبلغ المسترد نقداً من الخزينة:
                </label>
                <input
                  v-model="form.refund_amount"
                  type="number"
                  step="0.001"
                  min="0"
                  class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs font-mono font-bold text-emerald-400 focus:outline-none"
                  placeholder="0.00"
                >
                <span class="text-[10px] text-slate-500 font-sans">اتركه 0.00 إذا كان المرتجع تسوية حساب آجل</span>
              </div>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              :disabled="isSubmitting || form.items.length === 0"
              class="w-full h-12 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-2xl font-black text-xs shadow-xl shadow-amber-500/20 transition active:scale-[0.99] disabled:opacity-40 cursor-pointer flex items-center justify-center gap-2"
            >
              <span v-if="isSubmitting" class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <RotateCcw v-else class="w-4 h-4" />
              <span>حفظ واعتماد المرتجع 🔄</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </SpaLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import SpaLayout from '../../Layouts/SpaLayout.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import {
    ArrowRight,
    Trash2,
    RotateCcw
} from 'lucide-vue-next';

const router = useRouter();

const customers = ref([]);
const suppliers = ref([]);
const items = ref([]);

const isSubmitting = ref(false);
const selectedItemToAdd = ref(null);

const form = reactive({
    return_type: 'sales_return',
    customer_id: null,
    supplier_id: null,
    return_date: new Date().toISOString().split('T')[0],
    refund_amount: '0.000',
    reason: '',
    items: [],
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const netTotal = computed(() => {
    return form.items.reduce((sum, it) => sum + (parseFloat(it.quantity) || 0) * (parseFloat(it.unit_price) || 0), 0);
});

const loadFormDependencies = async () => {
    try {
        const [custRes, suppRes, itemRes] = await Promise.all([
            api.get('/customers?per_page=100'),
            api.get('/suppliers?per_page=100'),
            api.get('/items?per_page=100'),
        ]);

        customers.value = custRes.data?.data || [];
        suppliers.value = suppRes.data?.data || [];
        items.value = itemRes.data?.data || [];

        if (customers.value.length > 0) {
            form.customer_id = customers.value[0].id;
        }
        if (suppliers.value.length > 0) {
            form.supplier_id = suppliers.value[0].id;
        }
    } catch (error) {
        console.error('Failed to load return form data:', error);
    }
};

const onTypeChange = (type) => {
    form.return_type = type;
    form.items.forEach(line => {
        const it = items.value.find(i => i.id === line.item_id);
        if (it) {
            line.unit_price = type === 'sales_return' ? (parseFloat(it.price_retail || it.selling_price) || 0) : (parseFloat(it.cost_price) || 0);
        }
    });
};

const addItemRow = () => {
    if (!selectedItemToAdd.value) return;
    const it = selectedItemToAdd.value;

    if (form.items.some(i => i.item_id === it.id)) {
        Swal.fire({ icon: 'info', title: 'تنبيه', text: 'هذا الصنف مضاف بالفعل في قائمة المرتجع' });
        return;
    }

    const unitPrice = form.return_type === 'sales_return'
        ? (parseFloat(it.price_retail || it.selling_price) || 0)
        : (parseFloat(it.cost_price) || 0);

    form.items.push({
        item_id: it.id,
        name: it.name,
        unit: it.unit || 'كجم',
        quantity: 1,
        unit_price: unitPrice,
    });

    selectedItemToAdd.value = null;
};

const removeItemRow = (idx) => {
    form.items.splice(idx, 1);
};

const submitReturn = async () => {
    if (form.items.length === 0) {
        Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى إضافة صنف واحد على الأقل للمرتجع' });
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            return_type: form.return_type,
            customer_id: form.return_type === 'sales_return' ? form.customer_id : null,
            supplier_id: form.return_type === 'purchase_return' ? form.supplier_id : null,
            return_date: form.return_date,
            refund_amount: parseFloat(form.refund_amount) || 0,
            reason: form.reason || null,
            items: form.items.map(it => ({
                item_id: it.item_id,
                quantity: parseFloat(it.quantity),
                unit_price: parseFloat(it.unit_price),
            })),
        };

        const response = await api.post('/returns', payload);
        Swal.fire({
            icon: 'success',
            title: 'تم اعتماد المرتجع',
            text: response.data?.message || 'تم تسجيل المرتجع وتعديل الأرصدة بنجاح ✓',
            timer: 1500,
            showConfirmButton: false,
        });

        router.push('/returns');
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: error.userMessage || 'تعذر تسجيل مستند المرتجع',
        });
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    loadFormDependencies();
});
</script>
