import axios from 'axios';

/**
 * Service abstraction for Customer management API endpoints (DIP Principle)
 */
export const customerService = {
    /**
     * Fast registration for customer from POS cashier
     */
    async quickCreate(data) {
        const response = await axios.post('/pos/customers', data, {
            headers: { Accept: 'application/json' },
        });
        return response.data;
    },
};
