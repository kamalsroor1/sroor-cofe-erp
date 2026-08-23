import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useCoffeeBlender() {
    const router = useRouter();
    const { t } = useTrans();

    const items = ref([]);
    const customers = ref([]);

    const blendName = ref('تركيبة مجمعة مخصصة');
    const targetWeightGrams = ref(250);
    const selectedCustomerId = ref(null);
    const selectedItemIdToAdd = ref(null);
    const roastType = ref('وسط');
    const grindLevel = ref('تركي ناعم');
    const cardamomGrams = ref(0);
    const notes = ref('');
    const isSubmitting = ref(false);
    const components = ref([]);

    const presetWeights = computed(() => [
        { label: t('inventory.weight_125'), value: 125 },
        { label: t('inventory.weight_250'), value: 250 },
        { label: t('inventory.weight_500'), value: 500 },
        { label: t('inventory.weight_1000'), value: 1000 },
    ]);

    const roastOptions = computed(() => [
        { value: 'فاتح', label: t('inventory.roast_light') },
        { value: 'وسط', label: t('inventory.roast_medium') },
        { value: 'غامق', label: t('inventory.roast_dark') },
        { value: 'محروق / دبل', label: t('inventory.roast_double') },
    ]);

    const grindOptions = computed(() => [
        { value: 'تركي ناعم', label: t('inventory.grind_turkish') },
        { value: 'إسبريسو', label: t('inventory.grind_espresso') },
        { value: 'فرينش بريس', label: t('inventory.grind_french_press') },
        { value: 'حبوب بدون طحن', label: t('inventory.grind_beans') },
    ]);

    const itemOptions = computed(() => {
        return items.value.map(it => ({
            value: it.id,
            label: `${it.name} (${it.code || '—'}) — ${t('inventory.retail_price')}: ${it.price_retail || it.selling_price} ${t('common.currency')} | ${t('inventory.current_stock')}: ${it.current_stock} ${it.unit}`,
            raw: it,
        }));
    });

    const customerOptions = computed(() => {
        return customers.value.map(c => ({
            value: c.id,
            label: `${c.name} ${c.phone ? `(${c.phone})` : ''}`,
        }));
    });

    const setTargetWeight = (grams) => {
        targetWeightGrams.value = grams;
    };

    const calculatedComponents = computed(() => {
        const target = Number(targetWeightGrams.value) || 0;
        return components.value.map(c => {
            const pct = Number(c.percentage) || 0;
            const grams = (target * pct) / 100;
            const kg = grams / 1000;
            const cost = kg * c.cost_price;
            const price = kg * c.selling_price;

            return {
                ...c,
                grams: Number(grams.toFixed(1)),
                kg: Number(kg.toFixed(3)),
                cost: Number(cost.toFixed(2)),
                price: Number(price.toFixed(2)),
            };
        });
    });

    const totalPercentage = computed(() => {
        return components.value.reduce((sum, c) => sum + (Number(c.percentage) || 0), 0);
    });

    const totalCalculatedCost = computed(() => {
        let cost = calculatedComponents.value.reduce((sum, c) => sum + c.cost, 0);
        if (cardamomGrams.value > 0) {
            cost += (Number(cardamomGrams.value) * 1.5);
        }
        return Number(cost.toFixed(2));
    });

    const totalCalculatedPrice = computed(() => {
        let price = calculatedComponents.value.reduce((sum, c) => sum + c.price, 0);
        if (cardamomGrams.value > 0) {
            price += (Number(cardamomGrams.value) * 2.5);
        }
        return Number(price.toFixed(2));
    });

    const profitMargin = computed(() => {
        if (totalCalculatedPrice.value <= 0) return 0;
        const profit = totalCalculatedPrice.value - totalCalculatedCost.value;
        return Number(((profit / totalCalculatedPrice.value) * 100).toFixed(1));
    });

    const loadDependencies = async () => {
        try {
            const [itemsRes, custRes] = await Promise.all([
                api.get('/items?per_page=100'),
                api.get('/customers?per_page=100'),
            ]);

            items.value = itemsRes.data?.data || [];
            customers.value = custRes.data?.data || [];

            if (customers.value.length > 0) {
                selectedCustomerId.value = customers.value[0].id;
            }

            // Initialize default formulation with first 2 items if empty
            if (components.value.length === 0 && items.value.length >= 2) {
                components.value = [
                    {
                        item_id: items.value[0].id,
                        name: items.value[0].name,
                        percentage: 60,
                        cost_price: Number(items.value[0].cost_price),
                        selling_price: Number(items.value[0].price_retail || items.value[0].selling_price),
                        current_stock: items.value[0].current_stock,
                    },
                    {
                        item_id: items.value[1].id,
                        name: items.value[1].name,
                        percentage: 40,
                        cost_price: Number(items.value[1].cost_price),
                        selling_price: Number(items.value[1].price_retail || items.value[1].selling_price),
                        current_stock: items.value[1].current_stock,
                    },
                ];
            }
        } catch (error) {
            console.error('Failed to load blender dependencies:', error);
        }
    };

    const addComponentRow = () => {
        if (!selectedItemIdToAdd.value) return;
        const item = items.value.find(it => it.id === selectedItemIdToAdd.value);
        if (!item) return;

        if (components.value.some(c => c.item_id === item.id)) {
            Swal.fire({ icon: 'info', title: t('common.warning'), text: t('inventory.item_already_added') });
            return;
        }

        components.value.push({
            item_id: item.id,
            name: item.name,
            percentage: 0,
            cost_price: Number(item.cost_price),
            selling_price: Number(item.price_retail || item.selling_price),
            current_stock: item.current_stock,
        });

        selectedItemIdToAdd.value = null;
    };

    const removeComponentRow = (idx) => {
        components.value.splice(idx, 1);
    };

    const updateComponentPercentage = ({ index, value }) => {
        if (components.value[index]) {
            components.value[index].percentage = Math.min(100, Math.max(0, Number(value) || 0));
        }
    };

    const submitBlendInvoice = async () => {
        if (components.value.length === 0) {
            Swal.fire({ icon: 'warning', title: t('common.warning'), text: t('inventory.blend_components_empty') });
            return;
        }

        if (totalPercentage.value !== 100) {
            const result = await Swal.fire({
                title: t('inventory.ratio_warning_title'),
                text: t('inventory.ratio_warning_text', { pct: totalPercentage.value }),
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
            });
            if (!result.isConfirmed) return;
        }

        isSubmitting.value = true;
        try {
            const payload = {
                blend_name: `${blendName.value}`,
                customer_id: selectedCustomerId.value,
                target_weight_grams: targetWeightGrams.value,
                roast_type: roastType.value,
                grind_level: grindLevel.value,
                cardamom_grams: cardamomGrams.value,
                notes: notes.value || null,
                components: calculatedComponents.value.map(c => ({
                    item_id: c.item_id,
                    grams: c.grams,
                    unit_price: c.selling_price,
                })),
            };

            const response = await api.post('/coffee-blender/invoice', payload);
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: response.data?.message || t('inventory.blend_invoice_success'),
                timer: 1500,
                showConfirmButton: false,
            });

            router.push('/invoices');
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.userMessage || error.response?.data?.message || t('common.error'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    onMounted(() => {
        loadDependencies();
    });

    return {
        items,
        customers,
        blendName,
        targetWeightGrams,
        selectedCustomerId,
        selectedItemIdToAdd,
        roastType,
        grindLevel,
        cardamomGrams,
        notes,
        isSubmitting,
        presetWeights,
        roastOptions,
        grindOptions,
        itemOptions,
        customerOptions,
        components,
        calculatedComponents,
        totalPercentage,
        totalCalculatedCost,
        totalCalculatedPrice,
        profitMargin,
        setTargetWeight,
        addComponentRow,
        removeComponentRow,
        updateComponentPercentage,
        submitBlendInvoice,
    };
}
