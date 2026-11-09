/**
 * Whether the tenant's plan / overrides include a feature. Same rules as FeatureGate.vue;
 * unknown features (or no tenant info yet) are allowed, the server stays the authority.
 */
export function hasPlanFeature(tenant, feature) {
    if (!tenant || !feature) return true;
    if (Array.isArray(tenant.enabled_features) && tenant.enabled_features.includes(feature)) return true;
    const planFeatures = tenant.plan?.features;
    if (planFeatures && typeof planFeatures === 'object' && planFeatures[feature] !== undefined) {
        return Boolean(planFeatures[feature]);
    }
    if (tenant.features && typeof tenant.features === 'object' && tenant.features[feature] !== undefined) {
        return Boolean(tenant.features[feature]);
    }
    return true;
}

/**
 * First validation message (422), else the server message, else a translated fallback
 * for 403 / 404, else the generic fallback.
 */
export function mapApiError(error, { t, forbidden, featureUnavailable, notFound, fallback }) {
    const res = error?.response;
    const data = res?.data;
    if (res?.status === 422) {
        const first = Object.values(data?.errors || {})[0];
        const msg = Array.isArray(first) ? first[0] : first;
        return msg || data?.message || fallback;
    }
    if (res?.status === 403) {
        return data?.error_code === 'subscription.feature_unavailable' ? t(featureUnavailable) : t(forbidden);
    }
    if (res?.status === 404) return t(notFound);
    return data?.message || fallback;
}
