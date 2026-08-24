import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import { useAppConfigStore } from '../stores/appConfig';
import { useFormatters } from './useFormatters';
import { useTrans } from './useTrans';
import DarkSwal from '../helpers/alert';

export function useInvoiceShow() {
    const route = useRoute();
    const router = useRouter();
    const appConfigStore = useAppConfigStore();
    const { formatMoney } = useFormatters();
    const { t } = useTrans();

    const invoice = ref(null);
    const isLoading = ref(true);
    const error = ref(null);
    const activeMode = ref(route.query.mode || (route.name === 'invoices.print' ? 'thermal' : 'interactive'));
    
    // Cancellation state
    const showCancelModal = ref(false);
    const cancelReason = ref('');
    const isCancelling = ref(false);

    // Company Information Group
    const companyInfo = computed(() => ({
        name: appConfigStore.companyName || 'سرور كوفي ERP',
        subtitle: appConfigStore.companySubtitle || 'مؤسسة تجارية متخصصة',
        phone: appConfigStore.tenant?.phone || '01012345678',
        address: appConfigStore.tenant?.address || 'الفرع الرئيسي',
        commercialRegister: appConfigStore.tenant?.commercial_register || '123456',
        taxNumber: appConfigStore.tenant?.tax_number || '987-654-321',
    }));

    const invoiceItems = computed(() => {
        if (!invoice.value) return [];
        return invoice.value.items || invoice.value.invoice_items || [];
    });

    const invoicePayments = computed(() => {
        if (!invoice.value) return [];
        return invoice.value.payments || [];
    });

    // Customer Information Group
    const customerInfo = computed(() => {
        const raw = invoice.value?.customer || {};
        return {
            id: raw.id || invoice.value?.customer_id,
            name: invoice.value?.customer_name || raw.name || t('pos.general_walkin_customer'),
            phone: invoice.value?.customer_phone || raw.phone || '',
            balance: parseFloat(invoice.value?.customer_balance || raw.balance || 0),
            raw,
        };
    });

    const invoiceTime = computed(() => {
        if (!invoice.value?.formatted_created_at && !invoice.value?.created_at) return '';
        try {
            const dateStr = invoice.value.formatted_created_at || invoice.value.created_at;
            const d = new Date(dateStr);
            return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return '';
        }
    });

    const isCancelled = computed(() => invoice.value?.status === 'cancelled');

    const fetchInvoice = async () => {
        const id = route.params.id;
        if (!id) return;
        isLoading.value = true;
        error.value = null;
        try {
            const res = await api.get(`/invoices/${id}`);
            invoice.value = res.data?.data || res.data;
        } catch (e) {
            error.value = e.response?.data?.message || t('invoices.no_invoices_found');
            console.error('Failed to load invoice:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const triggerPrint = (mode = null) => {
        if (mode) activeMode.value = mode;
        setTimeout(() => {
            window.print();
        }, 150);
    };

    const copyInvoiceDetails = async () => {
        if (!invoice.value) return;
        const text = `🧾 فاتورة رقم: #${invoice.value.invoice_number}
🏢 المؤسسة: ${companyInfo.value.name}
👤 العميل: ${customerInfo.value.name}
📅 التاريخ: ${invoice.value.invoice_date}
💰 الصافي المطلوب: ${formatMoney(invoice.value.net_total)} ${t('common.currency')}
💵 المدفوع: ${formatMoney(invoice.value.paid_amount)} ${t('common.currency')}
📝 المتبقي: ${formatMoney(invoice.value.remaining_amount)} ${t('common.currency')}`;
        
        try {
            await navigator.clipboard.writeText(text);
            DarkSwal.fire({
                icon: 'success',
                title: t('invoices.copied_success'),
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500,
            });
        } catch (e) {
            console.warn('Failed to copy to clipboard', e);
        }
    };

    const openWhatsApp = () => {
        if (!invoice.value) return;
        const phone = customerInfo.value.phone?.replace(/[^0-9]/g, '') || '';
        const text = encodeURIComponent(`مرحباً بك ${customerInfo.value.name}،
مرفق تفاصيل فاتورتك رقم #${invoice.value.invoice_number} من ${companyInfo.value.name}:
الإجمالي الصافي: ${formatMoney(invoice.value.net_total)} ${t('common.currency')}
المدفوع: ${formatMoney(invoice.value.paid_amount)} ${t('common.currency')}
المتبقي: ${formatMoney(invoice.value.remaining_amount)} ${t('common.currency')}
شكراً لتعاملكم معنا! ☕`);

        const url = phone ? `https://wa.me/${phone}?text=${text}` : `https://wa.me/?text=${text}`;
        window.open(url, '_blank');
    };

    const confirmCancelInvoice = async () => {
        if (!cancelReason.value.trim()) {
            DarkSwal.fire({
                icon: 'warning',
                title: t('common.error'),
                text: t('invoices.cancel_reason_label'),
            });
            return;
        }

        isCancelling.value = true;
        try {
            await api.post(`/invoices/${invoice.value.id}/cancel`, {
                cancellation_reason: cancelReason.value,
            });

            DarkSwal.fire({
                icon: 'success',
                title: t('common.done'),
                text: t('invoices.cancelled_success', { number: invoice.value.invoice_number }),
            });

            showCancelModal.value = false;
            cancelReason.value = '';
            await fetchInvoice();
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('common.error_occurred'),
            });
        } finally {
            isCancelling.value = false;
        }
    };

    const goBack = () => {
        if (window.history.length > 1) {
            router.back();
        } else {
            router.push('/invoices');
        }
    };

    onMounted(async () => {
        await fetchInvoice();
        if (route.query.autoprint === 'true') {
            triggerPrint();
        }
    });

    return {
        invoice,
        isLoading,
        error,
        activeMode,
        showCancelModal,
        cancelReason,
        isCancelling,
        companyInfo,
        customerInfo,
        invoiceItems,
        invoicePayments,
        invoiceTime,
        isCancelled,
        fetchInvoice,
        triggerPrint,
        copyInvoiceDetails,
        openWhatsApp,
        confirmCancelInvoice,
        goBack,
    };
}
