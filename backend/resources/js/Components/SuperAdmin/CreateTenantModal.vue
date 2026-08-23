<template>
  <AppModal
    :show="show"
    :title="$t('super.create_tenant_modal_title')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-3.5 text-xs font-tajawal">
      <div>
        <BaseInput
          :model-value="form.name"
          @update:model-value="$emit('update:field', 'name', $event)"
          :label="$t('super.org_name_label')"
          :placeholder="$t('super.org_name_placeholder')"
          required
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <BaseInput
            :model-value="form.slug"
            @update:model-value="$emit('update:field', 'slug', $event)"
            :label="$t('super.slug_label')"
            :placeholder="$t('super.slug_placeholder')"
            class="font-mono"
            required
          />
        </div>

        <div>
          <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">{{ $t('super.selected_plan_label') }}</label>
          <select
            :value="form.plan_id"
            @change="$emit('update:field', 'plan_id', Number($event.target.value))"
            required
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:outline-none"
          >
            <option v-for="p in plansList" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <BaseInput
            :model-value="form.email"
            @update:model-value="$emit('update:field', 'email', $event)"
            :label="$t('super.admin_email_label')"
            :placeholder="$t('super.admin_email_placeholder')"
            type="email"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.phone"
            @update:model-value="$emit('update:field', 'phone', $event)"
            :label="$t('super.admin_phone_label')"
            :placeholder="$t('super.admin_phone_placeholder')"
            class="font-mono"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <BaseInput
            :model-value="form.password"
            @update:model-value="$emit('update:field', 'password', $event)"
            :label="$t('super.admin_password_label')"
            type="password"
            placeholder="••••••••"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.trial_days"
            @update:model-value="$emit('update:field', 'trial_days', Number($event))"
            :label="$t('super.trial_days_label')"
            type="number"
            class="font-mono"
          />
        </div>
      </div>

      <!-- Custom MySQL Database Credentials (for Hostinger Manual/Custom DBs) -->
      <div class="bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-theme-primary">🗄️ إعدادات قاعدة بيانات MySQL (لهوستنجر / اختياري)</span>
        </div>
        
        <div>
          <BaseInput
            :model-value="form.tenancy_db_name"
            @update:model-value="$emit('update:field', 'tenancy_db_name', $event)"
            label="اسم قاعدة البيانات الكامل"
            placeholder="مثلاً: u910151740_tenant_2m"
            class="font-mono"
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <BaseInput
              :model-value="form.tenancy_db_username"
              @update:model-value="$emit('update:field', 'tenancy_db_username', $event)"
              label="اسم مستخدم MySQL (إن كان مختلفاً)"
              placeholder="اتركه فارغاً للافتراضي"
              class="font-mono"
            />
          </div>

          <div>
            <BaseInput
              :model-value="form.tenancy_db_password"
              @update:model-value="$emit('update:field', 'tenancy_db_password', $event)"
              label="كلمة مرور MySQL (إن كانت مختلفة)"
              type="password"
              placeholder="اتركه فارغاً للافتراضية"
              class="font-mono"
            />
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
        <BaseButton
          type="button"
          variant="secondary"
          size="md"
          @click="$emit('close')"
        >
          {{ $t('common.cancel') }}
        </BaseButton>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="isSubmitting"
          class="bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-black shadow-lg"
        >
          {{ isSubmitting ? $t('super.provisioning_status') : $t('super.create_and_provision_btn') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  plansList: { type: Array, default: () => [] },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);
</script>
