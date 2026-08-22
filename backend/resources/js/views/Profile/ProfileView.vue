<template>
  <div class="space-y-6 max-w-3xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex items-center gap-3 bg-white dark:bg-slate-950/80 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl">
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
          <User class="w-5 h-5" />
        </div>
        <div>
          <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ $t('profile.title') }}</h1>
          <p class="text-xs text-slate-400">{{ $t('profile.subtitle') }}</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-16 text-center">
        <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400">{{ $t('profile.profile_loading') }}</p>
      </div>

      <form v-else @submit.prevent="submitProfile" class="space-y-6">
        <!-- Personal Information Card -->
        <div class="bg-white dark:bg-slate-950/80 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xl space-y-4 text-xs">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-2">👤 {{ $t('profile.basic_info') }}</h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">{{ $t('profile.full_name') }}</label>
              <input
                v-model="form.name"
                required
                type="text"
                class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:border-theme-primary"
              />
            </div>

            <div>
              <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">{{ $t('profile.phone_for_login') }}</label>
              <input
                v-model="form.phone"
                required
                type="text"
                class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-theme-primary"
              />
            </div>

            <div class="sm:col-span-2">
              <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">{{ $t('profile.email_optional') }}</label>
              <input
                v-model="form.email"
                type="email"
                class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-theme-primary"
              />
            </div>
          </div>
        </div>

        <!-- Security / Password Card -->
        <div class="bg-white dark:bg-slate-950/80 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xl space-y-4 text-xs">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-2">🔐 {{ $t('profile.security_password_title') }}</h2>
          <p class="text-[11px] text-slate-500">{{ $t('profile.password_leave_blank_hint') }}</p>

          <div class="space-y-3">
            <div>
              <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">{{ $t('profile.current_password') }}</label>
              <input
                v-model="form.current_password"
                type="password"
                placeholder="••••••••"
                class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-theme-primary"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">{{ $t('profile.new_password') }}</label>
                <input
                  v-model="form.new_password"
                  type="password"
                  placeholder="••••••••"
                  class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-theme-primary"
                />
              </div>

              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">{{ $t('profile.confirm_new_password') }}</label>
                <input
                  v-model="form.new_password_confirmation"
                  type="password"
                  placeholder="••••••••"
                  class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-theme-primary"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Preferences Card -->
        <div class="bg-white dark:bg-slate-950/80 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xl space-y-4 text-xs">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-2">🎨 {{ $t('profile.theme_pref') }}</h2>

          <div class="grid grid-cols-2 gap-4">
            <button
              type="button"
              @click="form.theme_preference = 'dark'"
              class="p-4 rounded-xl border text-center transition cursor-pointer"
              :class="form.theme_preference === 'dark' ? 'bg-amber-500/10 border-amber-500 text-amber-400' : 'bg-slate-900 border-slate-700 text-slate-400'"
            >
              <div class="text-lg mb-1">🌙</div>
              <div class="font-bold">{{ $t('profile.theme_dark_slate') }}</div>
            </button>

            <button
              type="button"
              @click="form.theme_preference = 'light'"
              class="p-4 rounded-xl border text-center transition cursor-pointer"
              :class="form.theme_preference === 'light' ? 'bg-amber-500/10 border-amber-500 text-amber-400' : 'bg-slate-900 border-slate-700 text-slate-400'"
            >
              <div class="text-lg mb-1">☀️</div>
              <div class="font-bold">{{ $t('profile.theme_light_shell') }}</div>
            </button>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-6 py-2.5 bg-theme-gradient text-white font-black shadow-theme-primary text-xs rounded-xl shadow-lg shadow-theme-primary transition cursor-pointer disabled:opacity-50"
          >
            {{ isSubmitting ? $t('profile.saving_profile') : $t('profile.save_changes') }}
          </button>
        </div>
      </form>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import { User } from 'lucide-vue-next';

const isLoading = ref(false);
const isSubmitting = ref(false);

const form = ref({
    name: '',
    phone: '',
    email: '',
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
    theme_preference: 'dark',
});

const fetchProfile = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/profile');
        const u = res.data?.data || {};
        form.value.name = u.name || '';
        form.value.phone = u.phone || '';
        form.value.email = u.email || '';
        form.value.theme_preference = u.theme_preference || 'dark';
    } catch (e) {
        console.error('Failed to load profile:', e);
    } finally {
        isLoading.value = false;
    }
};

const submitProfile = async () => {
    isSubmitting.value = true;
    try {
        await api.put('/profile', form.value);
        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: trans('profile.profile_updated_success'),
            timer: 1500,
            showConfirmButton: false,
        });
        form.value.current_password = '';
        form.value.new_password = '';
        form.value.new_password_confirmation = '';
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: e.response?.data?.message || trans('common.error'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    fetchProfile();
});
</script>
