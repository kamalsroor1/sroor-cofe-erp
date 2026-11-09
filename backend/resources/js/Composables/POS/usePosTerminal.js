import { ref, computed, onMounted, nextTick } from 'vue';
import versionData from '../../version.json';
import { useAppConfigStore } from '../../stores/appConfig';
import { useAuthStore } from '../../stores/auth';
import { useDesktopHardware } from '../useDesktopHardware';
import { useAudioFeedback } from '../useAudioFeedback';
import { useFormatters } from '../useFormatters';
import { usePosOrders } from '../usePosOrders';
import { useConnectivity } from '../useConnectivity';
import { usePosOrderFields } from './usePosOrderFields';
import { usePosCatalog } from './usePosCatalog';
import { usePosCustomer } from './usePosCustomer';
import { usePosItemSearch } from './usePosItemSearch';
import { usePosPayment } from './usePosPayment';
import { usePosCart } from './usePosCart';
import { usePosReceipt } from './usePosReceipt';
import { usePosSubmit } from './usePosSubmit';
import { usePosKeyboard } from './usePosKeyboard';

/**
 * Wires every POS composable together for the POS screen and returns what the view renders.
 */
export function usePosTerminal() {
    const authStore = useAuthStore();
    const appConfigStore = useAppConfigStore();
    const hardware = useDesktopHardware();
    const sounds = useAudioFeedback();
    const { formatMoney, formatDate, formatDateTime } = useFormatters();
    // OFFL-1: no offline queue yet (end of Phase 2) — every server write is blocked while offline.
    const { isOnline } = useConnectivity();

    const appVersion = ref(versionData?.version || '1.0.10');
    const headerRef = ref(null);
    const focusSearch = () => headerRef.value?.focusSearch();
    const lastCreatedInvoice = ref(null);

    const orderApi = usePosOrders();
    const { orders, activeOrderId, activeOrder } = orderApi;
    const fields = usePosOrderFields(activeOrder);

    const customer = usePosCustomer({ selectedCustomerId: fields.selectedCustomerId, isOnline });
    const catalog = usePosCatalog({ authStore, customers: customer.customers });
    const payment = usePosPayment({
        fields,
        activeOrder,
        activeOrderId,
        isWalkInCustomer: customer.isWalkInCustomer,
    });
    const cartOps = usePosCart({
        fields,
        orderApi,
        multiPayments: payment.multiPayments,
        activePriceTier: fields.activePriceTier,
        focusSearch,
        playScanBeep: sounds.playScanBeep,
        formatMoney,
    });
    const search = usePosItemSearch({
        items: catalog.items,
        addToCart: cartOps.addToCart,
        focusSearch,
        onMiss: sounds.playErrorTone,
    });
    const { printLastInvoice } = usePosReceipt({
        lastCreatedInvoice,
        paymentType: fields.paymentType,
        isDesktop: hardware.isDesktop,
        printThermalReceipt: hardware.printThermalReceipt,
        openCashDrawer: hardware.openCashDrawer,
        playDrawerSound: sounds.playDrawerSound,
        appConfigStore,
        formatDate,
        formatDateTime,
    });
    const { isSubmitting, showSuccessModal, lastChangeAmount, submitInvoice } = usePosSubmit({
        fields,
        activeOrder,
        activeStore: catalog.activeStore,
        isOnline,
        buildPayment: payment.buildPayment,
        buildPayloadLines: payment.buildPayloadLines,
        saveOrders: orderApi.saveOrders,
        clearActiveOrder: orderApi.clearActiveOrder,
        focusSearch,
        printLastInvoice,
        lastCreatedInvoice,
        sounds,
    });

    usePosKeyboard({
        focusSearch,
        createOrder: cartOps.handleCreateOrder,
        toggleCatalog: catalog.toggleCatalog,
        submit: submitInvoice,
        canSubmit: () => !isSubmitting.value && !showSuccessModal.value && fields.cart.value.length > 0,
        showSuccessModal,
    });

    const cartEmpty = computed(() => fields.cart.value.length === 0);

    onMounted(() => {
        orderApi.loadOrders();
        catalog.fetchInitialData();
        nextTick(focusSearch);
    });

    return {
        authStore,
        headerRef,
        appVersion,
        lastCreatedInvoice,
        isOnline,
        orders,
        activeOrderId,
        fields,
        customer,
        catalog,
        payment,
        cartOps,
        search,
        printLastInvoice,
        isSubmitting,
        showSuccessModal,
        lastChangeAmount,
        submitInvoice,
        cartEmpty,
        orderApi,
    };
}
