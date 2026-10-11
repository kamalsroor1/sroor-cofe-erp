import axios from 'axios';
import Swal from 'sweetalert2';
import { trans } from '../helpers/trans';
import { isNetworkError, reportRequestFailure, reportServerReachable } from '../helpers/connectivity';
import { storeAccessRetryConfig } from '../helpers/storeAccessRecovery';
import { isCentralAppContext } from './centralApi';

// Tenant client only. The platform console uses Services/centralApi.js (IDEN-1.9); on the admin
// host (central mode) this client never sends a tenant token, store or tenant header.
const isCentralMode = isCentralAppContext();

// 1. Create centralized Axios instance
const apiClient = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
    timeout: 30000,
});

// 2. Request Interceptor: Attach Auth Token, Store ID, Tenant, and Locale
apiClient.interceptors.request.use(
    (config) => {
        if (isCentralMode) {
            config.headers['X-Locale'] = localStorage.getItem('app_locale') || 'ar';
            return config;
        }

        // Auth Token
        const token = localStorage.getItem('auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        // Active Store Context
        const storeId = localStorage.getItem('current_store_id');
        if (storeId) {
            config.headers['X-Store-Id'] = storeId;
        }

        // Tenant Resolution Header (if customized)
        const tenantId = localStorage.getItem('tenant_id');
        if (tenantId) {
            config.headers['X-Tenant'] = tenantId;
        }

        // Locale
        const locale = localStorage.getItem('app_locale') || 'ar';
        config.headers['X-Locale'] = locale;

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// 3. Response Interceptor: Global Error Handling & Toast Notifications
apiClient.interceptors.response.use(
    (response) => {
        reportServerReachable();
        return response;
    },
    (error) => {
        // Feeds the offline banner (OFFL-1): any HTTP answer means the server is reachable.
        if (error.response) reportServerReachable();
        else reportRequestFailure(error);

        // Stale active branch (403 store_access_denied): forget it and retry once without
        // X-Store-Id, so the API falls back to the user's own store (no alert, no lockout).
        const storeRetry = storeAccessRetryConfig(error, localStorage);
        if (storeRetry) {
            return apiClient(storeRetry);
        }

        const status = error.response ? error.response.status : null;
        const data = error.response ? error.response.data : null;
        const message = data?.message || error.message || trans('common.unexpected_error');

        // 401 Unauthorized: Session Expired / Invalid Token
        if (status === 401 && !isCentralMode) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            localStorage.removeItem('auth_store');
            localStorage.removeItem('current_store_id');

            // Prevent redirect loop if already on login page
            const currentPath = window.location.pathname;
            if (!currentPath.includes('/login')) {
                if (window.spaRouter) {
                    window.spaRouter.replace({
                        name: 'login',
                        query: { redirect: currentPath !== '/' ? currentPath : undefined },
                    });
                } else {
                    window.location.href = '/login';
                }
            }
        }

        // 403 Forbidden: Insufficient Permissions
        if (status === 403) {
            Swal.fire({
                icon: 'warning',
                title: trans('common.permission_alert'),
                text: message,
                confirmButtonText: trans('common.ok'),
                confirmButtonColor: '#f59e0b',
                background: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff',
                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#0f172a',
            });
        }

        // 422 Validation Error: Format errors
        if (status === 422 && data?.errors) {
            const firstError = Object.values(data.errors).flat()[0] || message;
            error.userMessage = firstError;
        } else if (isNetworkError(error)) {
            error.userMessage = trans('connectivity.network_error');
        } else {
            error.userMessage = message;
        }

        return Promise.reject(error);
    }
);

export default apiClient;
export { apiClient as api };
