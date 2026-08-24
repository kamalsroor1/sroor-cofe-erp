import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useTrans } from './useTrans';
import DarkSwal from '../helpers/alert';

export function useSuperAdminUnits() {
    const { t } = useTrans();

    const units = ref(['قطعة', 'علبة', 'كرتونة', 'كجم', 'جرام', 'شيكارة', 'طرد', 'دستة', 'باكت', 'حبة', 'لتر', 'مل', 'متر', 'طقم', 'زوج', 'باليتة']);
    const presets = ['قطعة', 'علبة', 'كرتونة', 'كجم', 'جرام', 'شيكارة', 'طرد', 'دستة', 'باكت', 'حبة', 'لتر', 'مل', 'متر', 'طقم', 'زوج', 'باليتة', 'صندوق', 'رول', 'برميل', 'شريحة'];
    const newUnitInput = ref('');
    const isLoading = ref(false);
    const isSaving = ref(false);

    const fetchUnits = async () => {
        isLoading.value = true;
        try {
            const res = await api.get('/super-admin/units');
            if (res.data?.units && Array.isArray(res.data.units) && res.data.units.length > 0) {
                units.value = res.data.units;
            }
        } catch (e) {
            console.error('Failed to load super admin units:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const addCustomUnit = () => {
        const u = newUnitInput.value.trim();
        if (!u) return;
        if (!units.value.includes(u)) {
            units.value.push(u);
        }
        newUnitInput.value = '';
    };

    const addPreset = (p) => {
        if (!units.value.includes(p)) {
            units.value.push(p);
        }
    };

    const removeUnit = (idx) => {
        units.value.splice(idx, 1);
    };

    const saveUnits = async () => {
        if (units.value.length === 0) {
            DarkSwal.fire({
                icon: 'warning',
                title: t('common.error'),
                text: t('super.at_least_one_system_unit'),
            });
            return;
        }

        isSaving.value = true;
        try {
            await api.post('/super-admin/units', {
                units: units.value,
            });

            DarkSwal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('super.units_updated_success'),
                timer: 1500,
                showConfirmButton: false,
            });
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('super.units_save_error'),
            });
        } finally {
            isSaving.value = false;
        }
    };

    onMounted(() => {
        fetchUnits();
    });

    return {
        units,
        presets,
        newUnitInput,
        isLoading,
        isSaving,
        fetchUnits,
        addCustomUnit,
        addPreset,
        removeUnit,
        saveUnits,
    };
}
