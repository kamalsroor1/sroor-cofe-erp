<template>
  <AppModal
    :show="show"
    :title="editingCustomer ? $t('contacts.edit_customer') : $t('contacts.add_customer')"
    @close="$emit('close')"
  >
    <form id="customer-form" @submit.prevent="$emit('save')" class="space-y-4 font-tajawal">
      <!-- Name & Phone Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.name"
          @update:model-value="$emit('update:field', 'name', $event)"
          :label="$t('contacts.customer_name')"
          :placeholder="$t('contacts.customer_name_placeholder')"
          :error="errors?.name?.[0] ?? null"
          required
        />

        <BaseInput
          :model-value="form.phone"
          @update:model-value="$emit('update:field', 'phone', $event)"
          :label="$t('contacts.phone')"
          :placeholder="$t('contacts.phone_placeholder')"
          :error="errors?.phone?.[0] ?? null"
          type="tel"
          dir="ltr"
          class="font-mono"
        />
      </div>

      <!-- Address & Tax Number Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.address"
          @update:model-value="$emit('update:field', 'address', $event)"
          :label="$t('contacts.address')"
          :placeholder="$t('contacts.address_placeholder')"
          :error="errors?.address?.[0] ?? null"
        />

        <BaseInput
          :model-value="form.tax_number"
          @update:model-value="$emit('update:field', 'tax_number', $event)"
          :label="$t('contacts.tax_number')"
          :placeholder="$t('contacts.tax_number_placeholder')"
          :error="errors?.tax_number?.[0] ?? null"
          class="font-mono"
        />
      </div>

      <!-- SHOP-03: credit limit (empty = no limit; a new customer starts with the shop default) -->
      <BaseInput
        :model-value="form.credit_limit"
        @update:model-value="$emit('update:field', 'credit_limit', $event)"
        :label="$t('contacts.credit_limit')"
        :placeholder="$t('contacts.credit_limit_placeholder')"
        :hint="$t('contacts.credit_limit_hint')"
        :error="errors?.credit_limit?.[0] ?? null"
        inputmode="decimal"
        autocomplete="off"
        dir="ltr"
        input-class="font-mono"
        data-testid="customer-credit-limit"
      />

      <!-- SAL-043: Customer Price Tier (Retail / Wholesale) -->
      <BaseSelect
        :model-value="form.price_tier || 'retail'"
        @update:model-value="$emit('update:field', 'price_tier', $event)"
        :label="$t('pos.price_tier')"
        :options="priceTierOptions"
        :searchable="false"
        :error="errors?.price_tier?.[0] ?? null"
      />

      <!-- Initial Balance (Only for new customers) -->
      <div v-if="!editingCustomer">
        <BaseNumberInput
          :model-value="form.opening_balance"
          @update:model-value="$emit('update:field', 'opening_balance', $event)"
          :label="$t('contacts.initial_balance')"
          :hint="$t('contacts.customer_initial_balance_hint')"
          :error="errors?.opening_balance?.[0] ?? null"
          :decimals="3"
        />
      </div>

      <!-- Notes Textarea -->
      <BaseTextarea
        :model-value="form.notes"
        @update:model-value="$emit('update:field', 'notes', $event)"
        :label="$t('common.notes')"
        :rows="2"
        :placeholder="$t('contacts.notes_placeholder')"
        :error="errors?.notes?.[0] ?? null"
      />
    </form>

    <template #footer>
      <div class="flex items-center justify-end gap-3">
        <BaseButton type="button" variant="secondary" size="md" @click="$emit('close')">
          {{ $t('common.cancel') }}
        </BaseButton>

        <BaseButton
          type="submit"
          form="customer-form"
          variant="primary"
          size="md"
          :loading="saving"
          class="font-black shadow-lg shadow-theme-primary/20"
        >
          {{ editingCustomer ? $t('common.save_changes') : $t('contacts.save_customer') }}
        </BaseButton>
      </div>
    </template>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

const priceTierOptions = computed(() => [
  { value: 'retail', label: t('pos.retail_tier') },
  { value: 'wholesale', label: t('pos.wholesale_tier') },
]);

defineProps({
  show: { type: Boolean, default: false },
  editingCustomer: { type: Object, default: null },
  form: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
});

defineEmits(['close', 'save', 'update:field']);
</script>
