import { ref, computed } from 'vue';

export function usePOSCart(selectedCustomerRef) {
    const cart = ref([]);
    const discountType = ref('fixed'); // 'fixed' | 'percentage'
    const discountValue = ref(0);
    const paymentType = ref('cash'); // 'cash' | 'credit' | 'partial'
    const paymentMethod = ref('cash'); // 'cash' | 'instapay' | 'e_wallet' | 'visa' | 'bank_transfer'
    const paidAmount = ref(0);
    const shippingCost = ref(0);
    const additionalExpenses = ref([]);
    const invoiceNotes = ref('');

    // Calculations
    const subtotal = computed(() => {
        return cart.value.reduce((sum, item) => sum + Number(item.unit_price) * Number(item.quantity), 0);
    });

    const discountAmount = computed(() => {
        if (discountType.value === 'percentage') {
            const pct = Math.min(Math.max(Number(discountValue.value || 0), 0), 100);
            return (subtotal.value * pct) / 100;
        }
        return Math.min(Number(discountValue.value || 0), subtotal.value);
    });

    const expensesTotal = computed(() => {
        const listSum = additionalExpenses.value.reduce((sum, exp) => sum + Number(exp.amount || 0), 0);
        return listSum + Number(shippingCost.value || 0);
    });

    const netTotal = computed(() => {
        return Math.max(subtotal.value - discountAmount.value + expensesTotal.value, 0);
    });

    const remainingAmount = computed(() => {
        return Math.max(netTotal.value - Number(paidAmount.value || 0), 0);
    });

    const changeDue = computed(() => {
        if (paymentType.value !== 'cash') return 0;
        return Math.max(Number(paidAmount.value || 0) - netTotal.value, 0);
    });

    const autoSetCashPaid = () => {
        if (paymentType.value === 'cash') {
            paidAmount.value = netTotal.value;
        } else if (paymentType.value === 'credit') {
            paidAmount.value = 0;
        }
    };

    const addItem = (item, qty = 1, lastSoldPrice = null) => {
        const isWholesale = selectedCustomerRef.value?.price_tier === 'wholesale';
        const price = isWholesale ? item.price_wholesale : item.price_retail;

        const existingIndex = cart.value.findIndex((ci) => ci.item_id === item.id);
        if (existingIndex > -1) {
            cart.value[existingIndex].quantity = Number((cart.value[existingIndex].quantity + qty).toFixed(3));
        } else {
            cart.value.unshift({
                item_id: item.id,
                name: item.name,
                code: item.code,
                unit: item.unit,
                unit_price: Number(price),
                quantity: Number(qty),
                last_sold_price: lastSoldPrice,
            });
        }

        autoSetCashPaid();
    };

    const removeItem = (index) => {
        cart.value.splice(index, 1);
        autoSetCashPaid();
    };

    const clearCart = () => {
        cart.value = [];
        discountValue.value = 0;
        paidAmount.value = 0;
        shippingCost.value = 0;
        additionalExpenses.value = [];
        invoiceNotes.value = '';
    };

    return {
        cart,
        discountType,
        discountValue,
        paymentType,
        paymentMethod,
        paidAmount,
        shippingCost,
        additionalExpenses,
        invoiceNotes,
        subtotal,
        discountAmount,
        expensesTotal,
        netTotal,
        remainingAmount,
        changeDue,
        autoSetCashPaid,
        addItem,
        removeItem,
        clearCart,
    };
}
