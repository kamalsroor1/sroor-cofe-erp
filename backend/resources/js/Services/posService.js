import axios from 'axios';

/**
 * Service abstraction for POS API endpoints (DIP Principle)
 */
export const posService = {
    /**
     * Fetch last sold price for a specific customer & item
     */
    async getCustomerLastSoldPrice(customerId, itemId, storeId) {
        if (!customerId || !itemId) return null;
        try {
            const response = await axios.get('/pos/customer-last-price', {
                params: {
                    customer_id: customerId,
                    item_id: itemId,
                    store_id: storeId,
                },
            });
            return response.data?.last_price || null;
        } catch (error) {
            console.error('Failed to fetch last sold price', error);
            return null;
        }
    },

    /**
     * Submit and process POS invoice checkout
     */
    async processCheckout(payload) {
        const response = await axios.post('/pos/invoices', payload, {
            headers: { Accept: 'application/json' },
        });
        return response.data;
    },
};
