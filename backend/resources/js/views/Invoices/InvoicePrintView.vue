<template>
  <div class="print-wrapper min-h-screen bg-slate-200 dark:bg-slate-950 py-6 px-2 font-tajawal text-slate-900 select-none flex flex-col items-center justify-start print:bg-white print:p-0 print:m-0 print:min-h-0" dir="rtl">
    
    <!-- Action Bar (Hidden on Print) -->
    <div class="no-print w-full max-w-[80mm] mb-4 flex items-center justify-between gap-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2.5 rounded-2xl shadow-lg">
      <button
        type="button"
        @click="triggerPrint"
        class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black transition flex items-center justify-center gap-1.5 cursor-pointer shadow-xs active:scale-95"
      >
        <Printer class="w-4 h-4" />
        <span>طباعة الإيصال (F9)</span>
      </button>

      <button
        type="button"
        @click="goBack"
        class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer active:scale-95"
      >
        <ArrowRight class="w-4 h-4" />
        <span>عودة</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="no-print p-8 text-center text-slate-600 dark:text-slate-300 font-bold">
      <div class="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      <p class="text-xs font-bold">جاري تجهيز إيصال الفاتورة...</p>
    </div>

    <!-- 80mm Thermal Receipt Container -->
    <div
      v-else-if="invoice"
      id="receipt-print-area"
      class="receipt-card bg-white text-black p-3.5 shadow-xl rounded-lg border border-slate-300 print:shadow-none print:border-none print:rounded-none print:p-1"
    >
      <!-- 1. Header / Company Info -->
      <div class="text-center space-y-1 pb-2 border-b-2 border-dashed border-black">
        <h1 class="text-lg font-black tracking-tight leading-tight text-black">{{ companyName }}</h1>
        <p v-if="companySubtitle" class="text-[11px] font-bold text-black">{{ companySubtitle }}</p>
        <div class="text-[10px] font-bold text-black font-mono mt-0.5">
          {{ invoice.store_name || activeStoreName }}
        </div>
      </div>

      <!-- 2. Invoice Meta Details -->
      <div class="py-2 border-b-2 border-dashed border-black text-xs space-y-1 font-mono text-black">
        <div class="flex justify-between items-center">
          <span class="font-bold">رقم الفاتورة:</span>
          <span class="font-black text-xs">{{ invoice.invoice_number }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span><strong>التاريخ:</strong> {{ invoice.invoice_date }}</span>
          <span><strong>الوقت:</strong> {{ invoiceTime }}</span>
        </div>
        <div class="flex justify-between items-center font-tajawal">
          <span class="font-bold">العميل:</span>
          <span class="font-black">{{ customerName }}</span>
        </div>
        <div v-if="customerPhone" class="flex justify-between items-center">
          <span class="font-bold">الهاتف:</span>
          <span class="font-mono">{{ customerPhone }}</span>
        </div>
        <div v-if="invoice.cashier_name" class="flex justify-between items-center font-tajawal">
          <span>الكاشير:</span>
          <span>{{ invoice.cashier_name }}</span>
        </div>
      </div>

      <!-- 3. Items Table -->
      <div class="py-2 border-b-2 border-dashed border-black">
        <table class="w-full text-xs text-start border-collapse text-black">
          <thead>
            <tr class="border-b-2 border-black text-xs font-black">
              <th class="text-start py-1">الصنف</th>
              <th class="text-center py-1 w-9">الكمية</th>
              <th class="text-center py-1 w-14">السعر</th>
              <th class="text-end py-1 w-16">الإجمالي</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-black/30">
            <tr v-for="item in invoiceItems" :key="item.id" class="py-1">
              <td class="py-1.5 font-bold leading-tight text-[11px]">
                {{ item.name }}
              </td>
              <td class="text-center font-mono font-bold py-1.5">{{ formatMoney(item.quantity) }}</td>
              <td class="text-center font-mono py-1.5">{{ formatMoney(item.unit_price) }}</td>
              <td class="text-end font-mono font-black py-1.5">{{ formatMoney(item.total_price) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 4. Financial Totals -->
      <div class="py-2 border-b-2 border-dashed border-black text-xs space-y-1 text-black">
        <div class="flex justify-between items-center">
          <span class="font-bold">المجموع الفرعي:</span>
          <span class="font-mono font-bold">{{ formatMoney(calculatedSubtotal) }} ج.م</span>
        </div>

        <div v-if="calculatedDiscount > 0" class="flex justify-between items-center">
          <span class="font-bold">الخصم:</span>
          <span class="font-mono font-bold">- {{ formatMoney(calculatedDiscount) }} ج.م</span>
        </div>

        <div v-if="calculatedShipping > 0" class="flex justify-between items-center">
          <span class="font-bold">مصاريف إضافية / شحن:</span>
          <span class="font-mono font-bold">+ {{ formatMoney(calculatedShipping) }} ج.م</span>
        </div>

        <!-- GIANT NET TOTAL -->
        <div class="flex justify-between items-center text-sm font-black pt-1.5 border-t-2 border-black">
          <span class="text-sm font-black">الصافي المطلوب:</span>
          <span class="font-mono text-base font-black">{{ formatMoney(calculatedNetTotal) }} ج.م</span>
        </div>

        <div class="flex justify-between items-center text-xs pt-1 border-t border-dashed border-black/50">
          <span class="font-bold">طريقة السداد:</span>
          <span class="font-black">{{ formatPaymentType(invoice.payment_type || invoice.invoice_type) }}</span>
        </div>

        <div v-if="parseFloat(invoice.paid_amount) > 0" class="flex justify-between items-center text-xs">
          <span>المبلغ المدفوع:</span>
          <span class="font-mono font-black">{{ formatMoney(invoice.paid_amount) }} ج.م</span>
        </div>

        <div v-if="parseFloat(invoice.remaining_amount) > 0" class="flex justify-between items-center text-xs font-bold">
          <span>المتبقي (آجل):</span>
          <span class="font-mono font-black">{{ formatMoney(invoice.remaining_amount) }} ج.م</span>
        </div>
      </div>

      <!-- 5. Footer -->
      <div class="pt-2 text-center text-xs space-y-1 font-bold text-black">
        <p class="text-xs font-black">شكراً لتعاملكم معنا!</p>
        <p class="text-[9px]">البضاعة المباعة ترد وتستبدل خلال 14 يوم بالفاتورة</p>
        <div class="pt-0.5 text-[8px] font-mono text-slate-800">تمت الطباعة بواسطة منظومة سـرور ERP</div>
      </div>

    </div>

    <!-- Error State -->
    <div v-else class="no-print p-8 text-center text-rose-400 font-bold bg-white dark:bg-slate-900 rounded-2xl border border-rose-500/30 max-w-sm">
      <p class="text-xs">تعذر العثور على بيانات الفاتورة رقم #{{ invoiceId }}</p>
      <button type="button" @click="goBack" class="mt-3 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-bold">
        العودة للـ POS
      </button>
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

const customerName = computed(() => {
    return invoice.value?.customer?.name || invoice.value?.customer_name || 'عميل نقدي سريع';
});

const customerPhone = computed(() => {
    return invoice.value?.customer?.phone || invoice.value?.customer_phone || '';
});

const invoiceTime = computed(() => {
    if (!invoice.value?.formatted_created_at && !invoice.value?.created_at) return '';
    try {
        const d = new Date(invoice.value.formatted_created_at || invoice.value.created_at);
        return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    } catch {
        return '';
    }
});

const invoiceItems = computed(() => {
    if (!invoice.value?.items || !Array.isArray(invoice.value.items)) return [];
    return invoice.value.items.map(it => {
        const qty = parseFloat(it.quantity) || 1;
        const price = parseFloat(it.unit_price) || 0;
        const total = parseFloat(it.total_price) || (qty * price);
        return {
            id: it.id,
            name: it.name || it.item_name || it.item?.name || 'صنف',
            quantity: qty,
            unit_price: price,
            total_price: total
        };
    });
});

const calculatedSubtotal = computed(() => {
    if (invoice.value?.subtotal !== undefined && invoice.value?.subtotal !== null) {
        return parseFloat(invoice.value.subtotal) || 0;
    }
    return invoiceItems.value.reduce((sum, it) => sum + it.total_price, 0);
});

const calculatedDiscount = computed(() => {
    return parseFloat(invoice.value?.discount_amount) || 0;
});

const calculatedShipping = computed(() => {
    return parseFloat(invoice.value?.shipping_cost) || 0;
});

const calculatedNetTotal = computed(() => {
    if (invoice.value?.net_total !== undefined && invoice.value?.net_total !== null) {
        const val = parseFloat(invoice.value.net_total);
        if (val > 0) return val;
    }
    if (invoice.value?.net_amount !== undefined && invoice.value?.net_amount !== null) {
        const val = parseFloat(invoice.value.net_amount);
        if (val > 0) return val;
    }
    const sub = calculatedSubtotal.value;
    const disc = calculatedDiscount.value;
    const ship = calculatedShipping.value;
    return Math.max(0, sub - disc + ship);
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatPaymentType = (type) => {
    switch (type) {
        case 'cash': return 'كاش نقدي';
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
        const data = res.data?.data;
        if (data) {
            invoice.value = data;
        }

        // Auto trigger print after 400ms rendering
        nextTick(() => {
            setTimeout(() => {
                window.print();
            }, 450);
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
  width: 78mm;
  max-width: 78mm;
  min-height: 80mm;
  font-family: 'Cairo', 'Tajawal', sans-serif !important;
}

@media print {
  @page {
    size: 80mm auto;
    margin: 0mm !important;
    padding: 0mm !important;
  }
  
  html, body, #app, .print-wrapper {
    background: #ffffff !important;
    background-color: #ffffff !important;
    color: #000000 !important;
    padding: 0 !important;
    margin: 0 auto !important;
    width: 80mm !important;
    max-width: 80mm !important;
    min-height: auto !important;
  }
  
  .no-print {
    display: none !important;
  }
  
  .receipt-card {
    width: 72mm !important;
    max-width: 72mm !important;
    padding: 1mm !important;
    margin: 0 auto !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    background: #ffffff !important;
    color: #000000 !important;
  }
  
  * {
    color: #000000 !important;
    text-shadow: none !important;
    background-color: transparent !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>