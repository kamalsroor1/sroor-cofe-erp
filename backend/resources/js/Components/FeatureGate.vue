<script setup>
import { computed } from 'vue';
import { useAppConfigStore } from '@/stores/appConfig';

const props = defineProps({
    feature: {
        type: String,
        default: null,
    },
    showFallback: {
        type: Boolean,
        default: false,
    },
});

const appConfigStore = useAppConfigStore();

// Safely evaluate feature access across tenant overrides and plan definitions
const isAllowed = computed(() => {
    if (!props.feature) {
        return true;
    }

    const tenant = appConfigStore.tenant;
    if (!tenant) {
        return true;
    }

    // 1. Check manual feature overrides on tenant
    if (Array.isArray(tenant.enabled_features) && tenant.enabled_features.includes(props.feature)) {
        return true;
    }

    // 2. Check plan features map
    if (tenant.plan && tenant.plan.features && typeof tenant.plan.features === 'object') {
        if (tenant.plan.features[props.feature] !== undefined) {
            return Boolean(tenant.plan.features[props.feature]);
        }
    }

    // 3. Fallback to direct features if present
    if (tenant.features && typeof tenant.features === 'object' && tenant.features[props.feature] !== undefined) {
        return Boolean(tenant.features[props.feature]);
    }

    return true;
});
</script>

<template>
    <template v-if="isAllowed">
        <slot />
    </template>
    <template v-else-if="showFallback">
        <slot name="fallback">
            <div class="p-3.5 bg-amber-500/10 border border-amber-500/20 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 text-xs text-amber-500 font-tajawal shadow-xs">
                <div class="flex items-center gap-2">
                    <span class="text-sm">🔒</span>
                    <span class="font-bold">{{ $t('super.plan_upgrade_required') }}</span>
                </div>
                <router-link to="/super-admin/plans" class="h-8 px-3.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black rounded-xl text-xs flex items-center justify-center transition active:scale-95 shadow-xs">
                    {{ $t('super.upgrade_now') }}
                </router-link>
            </div>
        </slot>
    </template>
</template>
