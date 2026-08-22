<template>
  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 py-6 px-4 font-tajawal text-black select-none flex flex-col items-center justify-start" dir="rtl">
    
    <!-- Action Bar (Hidden on Print) -->
    <div class="no-print w-full max-w-sm mb-4 flex items-center justify-between gap-2 bg-white dark:bg-slate-900 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
      <button
        type="button"
        @click="triggerPrint"
        class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black transition flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
      >
        <Printer class="w-4 h-4" />
        <span>طباعة الآن</span>
      </button>

      <button
        type="button"
        @click="goBack"
        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer"
      >
        <ArrowRight class="w-4 h-4" />
        <span>عودة</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="no-print p-8 text-center text-slate-500 font-bold">
      <div class="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      جاري تحميل بيانات الفاتورة...
    </div>

    <!-- 80mm Thermal Receipt Container -->
    <div
      v-else-if="invoice"
      id="receipt-print-area"
      class="receipt-card bg-white text-black p-4 shadow-xl border border-slate-300 rounded-lg"
    >
      <!-- Header / Company Info -->
      <div class="text-center space-y-1 pb-2 border-b-2 border-dashed border-black">
        <h1 class="text-lg font-black tracking-tight leading-tight">{{ companyName }}</h1>
        <p v-if="companySubtitle" class="text-[11px] font-bold text-slate-700">{{ companySubtitle }}</p>
        <div class="text-[10px] font-mono text-slate-600">{{ activeStoreName }}</div>
      </div>

      <!-- Invoice Meta Details -->
      <div class="py-2 border-b-2 border-dashed border-black text-xs space-y-1">
        <div class="flex justify-between font-mono">
          <span><strong>رقم الفاتورة:</strong></span>
          <span class="font-black">{{ invoice.invoice_number }}</span>
        </div>
        <div class="flex justify-between font-mono">
          <span><strong>التاريخ:</strong> {{ invoice.invoice_date }}</span>
          <span><strong>الوقت:</strong> {{ invoiceTime }}</span>
        </div>
        <div class="flex justify-between">
          <span><strong>العميل:</strong></span>
          <span class="font-bold">{{ invoice.customer?.name || 'عميل نقدي' }}</span>
        </div>
        <div v-if="invoice.customer?.phone" class="flex justify-between font-mono text-[11px]">
          <span><strong>الهاتف:</strong></span>
          <span>{{ invoice.customer.phone }}</span>
        </div>
      </div>

      <!-- Items Table -->
      <div class="py-2 border-b-2 border-dashed border-black">
        <table class="w-full text-xs text-start border-collapse">
          <thead>
            <tr class="border-b border-black text-[11px] font-black">
              <th class="text-start py-1">الصنف</th>
              <th class="text-center py-1 w-12">الكمية</th>
              <th class="text-center py-1 w-16">السعر</th>
              <th class="text-end py-1 w-16">الإجمالي</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="item in invoice.items" :key="item.id" class="py-1">
              <td class="py-1.5 font-bold leading-tight">
                {{ item.item?.name || item.name }}
              </td>
              <td class="text-center font-mono font-bold py-1.5">{{ formatMoney(item.quantity) }}</td>
              <td class="text-center font-mono py-1.5">{{ formatMoney(item.unit_price) }}</td>
              <td class="text-end font-mono font-black py-1.5">{{ formatMoney(item.total_price || (item.quantity * item.unit_price)) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Financial Totals -->
      <div class="py-2 border-b-2 border-dashed border-black text-xs space-y-1">
        <div class="flex justify-between">
          <span>المجموع الفرعي:</span>
          <span class="font-mono font-bold">{{ formatMoney(invoice.subtotal) }} ج.م</span>
        </div>

        <div v-if="parseFloat(invoice.discount_amount) > 0" class="flex justify-between text-rose-700">
          <span>الخصم:</span>
          <span class="font-mono font-bold">- {{ formatMoney(invoice.discount_amount) }} ج.م</span>
        </div>

        <div v-if="parseFloat(invoice.shipping_cost) > 0" class="flex justify-between">
          <span>مصاريف إضافية / شحن:</span>
          <span class="font-mono font-bold">+ {{ formatMoney(invoice.shipping_cost) }} ج.م</span>
        </div>

        <div class="flex justify-between text-sm font-black pt-1 border-t border-black">
          <span>الصافي المطلوب:</span>
          <span class="font-mono text-base font-black">{{ formatMoney(invoice.net_amount) }} ج.م</span>
        </div>

        <div class="flex justify-between text-[11px] pt-1">
          <span>طريقة الدفع:</span>
          <span class="font-bold">{{ formatPaymentType(invoice.invoice_type || invoice.payment_type) }}</span>
        </div>

        <div v-if="parseFloat(invoice.paid_amount) > 0" class="flex justify-between text-[11px]">
          <span>المبلغ المدفوع:</span>
          <span class="font-mono font-bold">{{ formatMoney(invoice.paid_amount) }} ج.م</span>
        </div>

        <div v-if="parseFloat(invoice.remaining_amount) > 0" class="flex justify-between text-[11px] text-rose-700 font-bold">
          <span>المتبقي (آجل):</span>
          <span class="font-mono">{{ formatMoney(invoice.remaining_amount) }} ج.م</span>
        </div>
      </div>

      <!-- Footer Thank You -->
      <div class="pt-3 text-center text-xs space-y-1 font-bold text-slate-800">
        <p>شكراً لتعاملكم معنا!</p>
        <p class="text-[10px] text-slate-600">البضاعة المباعة ترد وتستبدل خلال 14 يوم بالفاتورة</p>
        <div class="pt-1 text-[9px] font-mono text-slate-500">تمت الطباعة بواسطة منظومة سـرور ERP</div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import { Printer, ArrowRight } from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();

const invoiceId = route.params.id;
const invoice = ref(null);
const isLoading = ref(true);

const companyName = ref('سرور كوفي');
const companySubtitle = ref('لتوريدات ومبيعات الهواتف والإلكترونيات');
const activeStoreName = ref('الفرع الرئيسي');

const invoiceTime = computed(() => {
    if (!invoice.value?.created_at) return '';
    try {
        const d = new Date(invoice.value.created_at);
        return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    } catch {
        return '';
    }
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatPaymentType = (type) => {
    switch (type) {
        case 'cash': return 'نقدي فوري';
        case 'credit': return 'آجل ذمم';
        case 'partial': return 'دفع جزئي';
        default: return type || 'نقدي';
    }
};

const triggerPrint = () => {
    window.print();
};

const goBack = () => {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push('/pos');
    }
};

const loadInvoiceData = async () => {
    isLoading.value = true;
    try {
        const res = await api.get(`/invoices/${invoiceId}`);
        invoice.value = res.data?.data;
        
        // Auto trigger print after rendering
        nextTick(() => {
            setTimeout(() => {
                window.print();
            }, 400);
        });
    } catch (e) {
        console.error('Failed to load invoice for printing:', e);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    loadInvoiceData();
});
</script>

<style scoped>
.receipt-card {
  width: 80mm;
  max-width: 80mm;
  min-height: 100mm;
}

@media print {
  body, html {
    background: #ffffff !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .no-print {
    display: none !important;
  }
  .receipt-card {
    width: 78mm !important;
    max-width: 78mm !important;
    padding: 2mm !important;
    margin: 0 auto !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 0 !important;
  }
  * {
    color: #000000 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>