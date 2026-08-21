<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
            <Sliders class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-white">إعدادات النظام والمؤسسة</h1>
            <p class="text-xs text-slate-400">تخصيص الهوية التجارية، الفواتير والطباعة، والربط مع بوت تلجرام</p>
          </div>
        </div>

        <button
          @click="saveSettings"
          :disabled="isSaving"
          class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs rounded-xl shadow-lg shadow-amber-500/20 flex items-center gap-2 transition cursor-pointer disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          <span>{{ isSaving ? 'جاري الحفظ...' : 'حفظ الإعدادات' }}</span>
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-16 text-center">
        <div class="w-10 h-10 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400">جاري تحميل إعدادات النظام...</p>
      </div>

      <div v-else class="space-y-6">
        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 border-b border-slate-800 pb-2 overflow-x-auto">
          <button
            v-for="t in tabs"
            :key="t.id"
            @click="activeTab = t.id"
            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap cursor-pointer"
            :class="activeTab === t.id ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <span>{{ t.icon }}</span>
            <span>{{ t.label }}</span>
          </button>
        </div>

        <!-- Tab 1: Branding & Company Info -->
        <div v-show="activeTab === 'branding'" class="bg-slate-950/80 rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4 text-xs">
          <h2 class="text-sm font-bold text-white mb-3">🏢 الهوية التجارية وبيانات المؤسسة</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-slate-400 font-bold mb-1">اسم المؤسسة / المحمصة *</label>
              <input
                v-model="form.company_name"
                type="text"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
              />
            </div>

            <div>
              <label class="block text-slate-400 font-bold mb-1">الوصف الفرعي (يظهر أسفل الاسم)</label>
              <input
                v-model="form.company_subtitle"
                type="text"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
              />
            </div>

            <div>
              <label class="block text-slate-400 font-bold mb-1">رقم الهاتف الرسمي</label>
              <input
                v-model="form.company_phone"
                type="text"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500"
              />
            </div>

            <div>
              <label class="block text-slate-400 font-bold mb-1">العنوان الرئيسي</label>
              <input
                v-model="form.company_address"
                type="text"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
              />
            </div>
          </div>
        </div>

        <!-- Tab 2: Printing & Invoices -->
        <div v-show="activeTab === 'printing'" class="bg-slate-950/80 rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4 text-xs">
          <h2 class="text-sm font-bold text-white mb-3">🖨️ تخصيص الطباعة وإيصالات الفواتير</h2>

          <div class="space-y-3">
            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-800 cursor-pointer">
              <span class="text-slate-300 font-bold">طباعة اسم المؤسسة أعلى الإيصال</span>
              <input type="checkbox" v-model="form.show_print_company_name" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-500" />
            </label>

            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-800 cursor-pointer">
              <span class="text-slate-300 font-bold">طباعة الوصف الفرعي في الإيصال</span>
              <input type="checkbox" v-model="form.show_print_subtitle" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-500" />
            </label>

            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-800 cursor-pointer">
              <span class="text-slate-300 font-bold">طباعة رصيد ومديونية العميل أسفل الفاتورة الحرارية</span>
              <input type="checkbox" v-model="form.thermal_show_customer_balance" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-500" />
            </label>

            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-800 cursor-pointer">
              <span class="text-slate-300 font-bold">توليد رمز الاستجابة السريعة (QR Code) على الفاتورة</span>
              <input type="checkbox" v-model="form.print_show_qr" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-500" />
            </label>

            <div>
              <label class="block text-slate-400 font-bold mb-1">الملاحظة التذييلية للفواتير (Footer Note)</label>
              <textarea
                v-model="form.invoice_footer_note"
                rows="2"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Tab 3: Telegram Bot Notifications -->
        <div v-show="activeTab === 'telegram'" class="bg-slate-950/80 rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4 text-xs">
          <h2 class="text-sm font-bold text-white mb-3">🤖 الربط مع بوت تلجرام للإشعارات الفورية</h2>

          <div class="space-y-4">
            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-800 cursor-pointer">
              <span class="text-slate-300 font-bold">تفعيل إشعارات تلجرام اللحظية (المبيعات، النواقص، إغلاق الورديات)</span>
              <input type="checkbox" v-model="form.telegram_notifications_enabled" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-500" />
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-400 font-bold mb-1">رمز البوت (Bot Token)</label>
                <input
                  v-model="form.telegram_bot_token"
                  type="text"
                  placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">معرف القناة أو المحادثة (Chat ID)</label>
                <input
                  v-model="form.telegram_chat_id"
                  type="text"
                  placeholder="-1001234567890"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500"
                />
              </div>
            </div>

            <div class="pt-2">
              <button
                type="button"
                @click="sendTestTelegram"
                :disabled="isTestingTelegram || !form.telegram_chat_id"
                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-400 rounded-xl font-bold transition disabled:opacity-50"
              >
                {{ isTestingTelegram ? 'جاري الإرسال...' : 'إرسال إشعار تجريبي ✈️' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Tab 4: System Information -->
        <div v-show="activeTab === 'system'" class="bg-slate-950/80 rounded-2xl border border-slate-800 p-6 shadow-xl space-y-4 text-xs font-mono">
          <h2 class="text-sm font-bold text-white font-sans mb-3">⚙️ معلومات النظام والخادم</h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
              <span class="text-slate-400 block text-[10px] font-sans">إصدار PHP:</span>
              <span class="text-white font-bold">{{ systemInfo.php_version || '—' }}</span>
            </div>

            <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
              <span class="text-slate-400 block text-[10px] font-sans">إصدار Laravel:</span>
              <span class="text-white font-bold">{{ systemInfo.laravel_version || '—' }}</span>
            </div>

            <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
              <span class="text-slate-400 block text-[10px] font-sans">بيئة التشغيل:</span>
              <span class="text-emerald-400 font-bold">{{ systemInfo.environment || '—' }}</span>
            </div>

            <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
              <span class="text-slate-400 block text-[10px] font-sans">محرك قاعدة البيانات:</span>
              <span class="text-cyan-400 font-bold">{{ systemInfo.db_driver || '—' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import {
    Sliders,
    Save
} from 'lucide-vue-next';

const activeTab = ref('branding');
const isLoading = ref(false);
const isSaving = ref(false);
const isTestingTelegram = ref(false);

const tabs = [
    { id: 'branding', label: 'الهوية والمؤسسة', icon: '🏢' },
    { id: 'printing', label: 'الطباعة والفواتير', icon: '🖨️' },
    { id: 'telegram', label: 'إشعارات تلجرام', icon: '🤖' },
    { id: 'system', label: 'معلومات الخادم', icon: '⚙️' },
];

const form = ref({
    company_name: '',
    company_subtitle: '',
    company_phone: '',
    company_address: '',
    invoice_footer_note: '',
    show_print_company_name: true,
    show_print_subtitle: true,
    show_print_logo: true,
    thermal_show_customer_balance: true,
    print_show_qr: true,
    telegram_notifications_enabled: true,
    telegram_bot_token: '',
    telegram_chat_id: '',
});

const systemInfo = ref({});

const fetchSettings = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/settings');
        const s = res.data?.settings || {};
        form.value = {
            company_name: s.company_name || 'سرور كوفي',
            company_subtitle: s.company_subtitle || '',
            company_phone: s.company_phone || '',
            company_address: s.company_address || '',
            invoice_footer_note: s.invoice_footer_note || '',
            show_print_company_name: !!s.show_print_company_name,
            show_print_subtitle: !!s.show_print_subtitle,
            show_print_logo: !!s.show_print_logo,
            thermal_show_customer_balance: !!s.thermal_show_customer_balance,
            print_show_qr: !!s.print_show_qr,
            telegram_notifications_enabled: !!s.telegram_notifications_enabled,
            telegram_bot_token: s.telegram_bot_token || '',
            telegram_chat_id: s.telegram_chat_id || '',
        };
        systemInfo.value = res.data?.system_info || {};
    } catch (e) {
        console.error('Failed to load settings:', e);
    } finally {
        isLoading.value = false;
    }
};

const saveSettings = async () => {
    isSaving.value = true;
    try {
        await api.post('/settings', form.value);
        Swal.fire({
            icon: 'success',
            title: 'تم الحفظ',
            text: 'تم حفظ وتحديث إعدادات النظام بنجاح ✓',
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: e.response?.data?.message || 'تعذر حفظ الإعدادات',
        });
    } finally {
        isSaving.value = false;
    }
};

const sendTestTelegram = async () => {
    isTestingTelegram.value = true;
    try {
        const res = await api.post('/settings/telegram/test', {
            bot_token: form.value.telegram_bot_token,
            chat_id: form.value.telegram_chat_id,
        });
        if (res.data?.success) {
            Swal.fire({ icon: 'success', title: 'نجاح الإرسال', text: res.data.message });
        } else {
            Swal.fire({ icon: 'error', title: 'فشل الإرسال', text: res.data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: e.response?.data?.message || 'فشل إرسال الإشعار' });
    } finally {
        isTestingTelegram.value = false;
    }
};

onMounted(() => {
    fetchSettings();
});
</script>
