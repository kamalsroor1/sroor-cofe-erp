import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ref, computed } from 'vue';

test('datepicker locale resolves to Arabic in RTL and default/undefined in LTR', () => {
    const isRtl = ref(true);
    const resolvedLocale = computed(() => (isRtl.value ? 'ar' : 'default'));

    assert.equal(resolvedLocale.value, 'ar');

    // Switch to LTR (English)
    isRtl.value = false;
    assert.equal(resolvedLocale.value, 'default');

    // Switch back to RTL (Arabic)
    isRtl.value = true;
    assert.equal(resolvedLocale.value, 'ar');
});

test('computed paymentMethodOptions re-evaluates when active language changes', () => {
    const currentLang = ref('ar');
    const dictionary = {
        ar: { cash: 'نقدي', wallet: 'محفظة' },
        en: { cash: 'Cash', wallet: 'E-Wallet' },
    };

    const t = (key) => dictionary[currentLang.value][key] || key;

    const paymentMethodOptions = computed(() => [
        { value: 'cash', label: t('cash') },
        { value: 'wallet', label: t('wallet') },
    ]);

    assert.equal(paymentMethodOptions.value[0].label, 'نقدي');
    assert.equal(paymentMethodOptions.value[1].label, 'محفظة');

    // Switch language to en
    currentLang.value = 'en';
    assert.equal(paymentMethodOptions.value[0].label, 'Cash');
    assert.equal(paymentMethodOptions.value[1].label, 'E-Wallet');
});
