import { ref, computed, watch } from 'vue';

export function usePosOrders() {
    const orders = ref([]);
    const activeOrderId = ref('');

    const getStorageKey = () => {
        const tenant = localStorage.getItem('active_tenant') || localStorage.getItem('tenant_id') || 'central';
        const store = localStorage.getItem('current_store_id') || localStorage.getItem('active_store_id') || 'main';
        return `pos_multi_orders_${tenant}_${store}`;
    };

    const makeNewOrderObject = (orderNumber) => ({
        id: `ord_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`,
        number: orderNumber,
        title: `طلب #${orderNumber}`,
        createdAt: Date.now(),
        updatedAt: Date.now(),
        cart: [],
        selectedCustomerId: null,
        activePriceTier: 'retail',
        discountType: 'percentage',
        discountValue: '0',
        paymentType: 'cash',
        paymentMethod: 'cash',
        paidAmount: '0.000',
        cashReceived: '0.000',
        additionalExpenses: [],
        notes: '',
    });

    const saveOrders = () => {
        try {
            const key = getStorageKey();
            localStorage.setItem(key, JSON.stringify(orders.value));
            localStorage.setItem(`${key}_active`, activeOrderId.value);
        } catch (e) {
            console.error('[POS Orders] Failed to save orders to localStorage:', e);
        }
    };

    const loadOrders = () => {
        try {
            const key = getStorageKey();
            const raw = localStorage.getItem(key);
            const savedActiveId = localStorage.getItem(`${key}_active`);

            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    orders.value = parsed;
                    activeOrderId.value = (savedActiveId && parsed.some(o => o.id === savedActiveId))
                        ? savedActiveId
                        : parsed[0].id;
                    return;
                }
            }
        } catch (e) {
            console.error('[POS Orders] Failed to load orders from localStorage:', e);
        }

        // Initialize with first fresh order
        const firstOrder = makeNewOrderObject(1);
        orders.value = [firstOrder];
        activeOrderId.value = firstOrder.id;
        saveOrders();
    };

    const activeOrder = computed(() => {
        return orders.value.find((o) => o.id === activeOrderId.value) || orders.value[0] || null;
    });

    const createNewOrder = () => {
        const nextNum = orders.value.length > 0
            ? Math.max(...orders.value.map(o => o.number || 1)) + 1
            : 1;

        const newOrder = makeNewOrderObject(nextNum);
        orders.value.push(newOrder);
        activeOrderId.value = newOrder.id;
        saveOrders();
        return newOrder;
    };

    const switchOrder = (orderId) => {
        if (orders.value.some(o => o.id === orderId)) {
            activeOrderId.value = orderId;
            saveOrders();
        }
    };

    const closeOrder = (orderId) => {
        const idx = orders.value.findIndex(o => o.id === orderId);
        if (idx === -1) return;

        orders.value.splice(idx, 1);

        if (orders.value.length === 0) {
            const fresh = makeNewOrderObject(1);
            orders.value.push(fresh);
            activeOrderId.value = fresh.id;
        } else if (activeOrderId.value === orderId) {
            const nextIdx = Math.max(0, idx - 1);
            activeOrderId.value = orders.value[nextIdx].id;
        }

        saveOrders();
    };

    const clearActiveOrder = () => {
        if (!activeOrder.value) return;

        // If there are other orders open, remove this finished one and switch to next
        if (orders.value.length > 1) {
            closeOrder(activeOrderId.value);
        } else {
            // If it's the only order, reset its contents for a new transaction
            activeOrder.value.cart = [];
            activeOrder.value.selectedCustomerId = null;
            activeOrder.value.discountType = 'percentage';
            activeOrder.value.discountValue = '0';
            activeOrder.value.paymentType = 'cash';
            activeOrder.value.paymentMethod = 'cash';
            activeOrder.value.paidAmount = '0.000';
            activeOrder.value.cashReceived = '0.000';
            activeOrder.value.additionalExpenses = [];
            activeOrder.value.notes = '';
            activeOrder.value.updatedAt = Date.now();
            saveOrders();
        }
    };

    // Deep watcher to auto-save any changes to orders, carts, quantities, or discounts
    watch(
        orders,
        () => {
            saveOrders();
        },
        { deep: true }
    );

    return {
        orders,
        activeOrderId,
        activeOrder,
        loadOrders,
        saveOrders,
        createNewOrder,
        switchOrder,
        closeOrder,
        clearActiveOrder,
    };
}
