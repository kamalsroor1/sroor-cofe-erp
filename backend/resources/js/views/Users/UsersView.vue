<template>
  <SpaLayout>
    <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
            <Users class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-white">إدارة المستخدمين والموظفين</h1>
            <p class="text-xs text-slate-400">إدارة حسابات الكاشير، أمناء المخازن، والمديرين وتوزيع الصلاحيات</p>
          </div>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
          <router-link
            to="/roles"
            class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-bold text-xs rounded-xl shadow flex items-center gap-2 transition"
          >
            <ShieldCheck class="w-4 h-4 text-amber-400" />
            <span>مصفوفة الصلاحيات</span>
          </router-link>

          <button
            @click="openCreateModal"
            class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs rounded-xl shadow-lg shadow-amber-500/20 flex items-center gap-2 transition cursor-pointer"
          >
            <UserPlus class="w-4 h-4" />
            <span>إضافة موظف جديد</span>
          </button>
        </div>
      </div>

      <!-- Filters & Search -->
      <div class="p-4 bg-slate-950/80 rounded-2xl border border-slate-800 shadow-lg flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex-1 w-full relative">
          <Search class="w-4 h-4 text-slate-400 absolute start-3 top-3" />
          <input
            v-model="filters.search"
            @input="debouncedFetch"
            type="text"
            placeholder="بحث بالاسم، رقم الهاتف، أو البريد الإلكتروني..."
            class="w-full bg-slate-900 border border-slate-700 rounded-xl ps-9 pe-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
          />
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
          <select
            v-model="filters.role"
            @change="fetchUsers"
            class="bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-amber-500"
          >
            <option value="all">جميع الأدوار</option>
            <option v-for="r in rolesList" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>
      </div>

      <!-- Users Grid / Table -->
      <div class="bg-slate-950/80 rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
        <div v-if="isLoading" class="p-16 text-center">
          <div class="w-10 h-10 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-xs text-slate-400">جاري تحميل بيانات المستخدمين...</p>
        </div>

        <div v-else-if="users.length === 0" class="p-16 text-center">
          <Users class="w-12 h-12 text-slate-600 mx-auto mb-3" />
          <h3 class="text-sm font-bold text-slate-300 mb-1">لم يتم العثور على أي مستخدمين</h3>
          <p class="text-xs text-slate-500">جرب تعديل خيارات البحث أو قم بإضافة مستخدم جديد.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-start text-xs">
            <thead class="bg-slate-900/80 border-b border-slate-800 text-slate-400 font-bold">
              <tr>
                <th class="p-4 text-start">الموظف / الاسم</th>
                <th class="p-4 text-start">رقم الهاتف</th>
                <th class="p-4 text-start">الدور الوظيفي</th>
                <th class="p-4 text-start">الفرع الافتراضي</th>
                <th class="p-4 text-center">حالة النشاط</th>
                <th class="p-4 text-end">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono">
              <tr v-for="u in users" :key="u.id" class="hover:bg-slate-900/40 transition">
                <td class="p-4 font-sans font-bold text-white flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-amber-400 font-bold">
                    {{ u.name.charAt(0) }}
                  </div>
                  <div>
                    <div>{{ u.name }}</div>
                    <div class="text-[10px] text-slate-400 font-mono">{{ u.email || 'بدون بريد إلكتروني' }}</div>
                  </div>
                </td>

                <td class="p-4 text-slate-300 font-mono">{{ u.phone }}</td>

                <td class="p-4 font-sans">
                  <span
                    class="px-2.5 py-1 rounded-full text-[11px] font-bold border"
                    :class="getRoleBadgeClass(u.primary_role)"
                  >
                    {{ getRoleLabel(u.primary_role) }}
                  </span>
                </td>

                <td class="p-4 font-sans text-slate-300">{{ u.default_store_name }}</td>

                <td class="p-4 text-center font-sans">
                  <button
                    @click="toggleActive(u)"
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border transition cursor-pointer"
                    :class="u.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/20' : 'bg-rose-500/10 border-rose-500/30 text-rose-400 hover:bg-rose-500/20'"
                  >
                    {{ u.is_active ? 'نشط ✅' : 'معطل 🚫' }}
                  </button>
                </td>

                <td class="p-4 text-end font-sans">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click="openEditModal(u)"
                      class="p-1.5 bg-slate-900 hover:bg-slate-800 text-amber-400 border border-slate-700 rounded-lg transition"
                      title="تعديل"
                    >
                      <Edit2 class="w-3.5 h-3.5" />
                    </button>
                    <button
                      @click="deleteUser(u)"
                      class="p-1.5 bg-slate-900 hover:bg-rose-950/40 text-rose-400 border border-slate-700 hover:border-rose-800 rounded-lg transition"
                      title="حذف"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400 font-mono">
          <span>إجمالي المستخدمين: {{ pagination.total }}</span>
          <div class="flex items-center gap-2 font-sans">
            <button
              :disabled="pagination.current_page === 1"
              @click="changePage(pagination.current_page - 1)"
              class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 border border-slate-700 rounded-xl"
            >
              السابق
            </button>
            <span>صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
            <button
              :disabled="pagination.current_page === pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
              class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 border border-slate-700 rounded-xl"
            >
              التالي
            </button>
          </div>
        </div>
      </div>

      <!-- Create / Edit User Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-slate-950 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h2 class="text-base font-black text-white flex items-center gap-2">
              <UserPlus class="w-4 h-4 text-amber-400" />
              <span>{{ isEditing ? 'تعديل بيانات المستخدم' : 'إضافة موظف جديد' }}</span>
            </h2>
            <button @click="showModal = false" class="text-slate-400 hover:text-white">✕</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-3.5 text-xs">
            <div>
              <label class="block text-slate-400 font-bold mb-1">الاسم بالكامل *</label>
              <input
                v-model="form.name"
                required
                type="text"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
                placeholder="مثال: أحمد مصطفى"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 font-bold mb-1">رقم الهاتف *</label>
                <input
                  v-model="form.phone"
                  required
                  type="text"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500"
                  placeholder="010XXXXXXXX"
                />
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">البريد الإلكتروني (اختياري)</label>
                <input
                  v-model="form.email"
                  type="email"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500"
                  placeholder="user@example.com"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 font-bold mb-1">الدور الوظيفي *</label>
                <select
                  v-model="form.role"
                  required
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
                >
                  <option v-for="r in rolesList" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </div>

              <div>
                <label class="block text-slate-400 font-bold mb-1">الفرع الافتراضي</label>
                <select
                  v-model="form.default_store_id"
                  class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"
                >
                  <option :value="null">بدون تعيين فرع</option>
                  <option v-for="st in storesList" :key="st.id" :value="st.id">{{ st.name }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-slate-400 font-bold mb-1">
                {{ isEditing ? 'كلمة المرور الجديدة (اتركها فارغة إذا لم ترد التغيير)' : 'كلمة المرور *' }}
              </label>
              <input
                v-model="form.password"
                :required="!isEditing"
                type="password"
                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500"
                placeholder="••••••••"
              />
            </div>

            <div class="flex items-center gap-2 pt-1">
              <input
                v-model="form.is_active"
                type="checkbox"
                id="is_active_check"
                class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-amber-500"
              />
              <label for="is_active_check" class="text-slate-300 font-bold">الحساب نشط ويمكنه تسجيل الدخول</label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
              <button
                type="button"
                @click="showModal = false"
                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded-xl font-bold"
              >
                إلغاء
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="px-5 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black rounded-xl shadow-lg transition disabled:opacity-50"
              >
                {{ isSubmitting ? 'جاري الحفظ...' : (isEditing ? 'تحديث البيانات' : 'حفظ الموظف') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </SpaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import SpaLayout from '../../Layouts/SpaLayout.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import {
    Users,
    UserPlus,
    ShieldCheck,
    Search,
    Edit2,
    Trash2
} from 'lucide-vue-next';

const users = ref([]);
const rolesList = ref([]);
const storesList = ref([]);
const isLoading = ref(false);
const isSubmitting = ref(false);

const filters = ref({
    search: '',
    role: 'all',
    page: 1,
});

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = ref({
    name: '',
    phone: '',
    email: '',
    password: '',
    role: 'cashier',
    default_store_id: null,
    is_active: true,
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        filters.value.page = 1;
        fetchUsers();
    }, 300);
};

const fetchUsers = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/users', { params: filters.value });
        users.value = res.data?.data || [];
        rolesList.value = res.data?.roles || [];
        storesList.value = res.data?.stores || [];
        pagination.value = res.data?.pagination || pagination.value;
    } catch (e) {
        console.error('Failed to fetch users:', e);
    } finally {
        isLoading.value = false;
    }
};

const changePage = (page) => {
    filters.value.page = page;
    fetchUsers();
};

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
        name: '',
        phone: '',
        email: '',
        password: '',
        role: 'cashier',
        default_store_id: storesList.value[0]?.id || null,
        is_active: true,
    };
    showModal.value = true;
};

const openEditModal = (u) => {
    isEditing.value = true;
    editingId.value = u.id;
    form.value = {
        name: u.name,
        phone: u.phone,
        email: u.email || '',
        password: '',
        role: u.primary_role || 'cashier',
        default_store_id: u.default_store_id,
        is_active: u.is_active,
    };
    showModal.value = true;
};

const submitForm = async () => {
    isSubmitting.value = true;
    try {
        if (isEditing.value) {
            await api.put(`/users/${editingId.value}`, form.value);
            Swal.fire({ icon: 'success', title: 'تم التحديث', text: 'تم تحديث بيانات المستخدم بنجاح', timer: 1500, showConfirmButton: false });
        } else {
            await api.post('/users', form.value);
            Swal.fire({ icon: 'success', title: 'تمت الإضافة', text: 'تم إنشاء حساب الموظف بنجاح', timer: 1500, showConfirmButton: false });
        }
        showModal.value = false;
        fetchUsers();
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: e.response?.data?.message || 'تعذر حفظ البيانات' });
    } finally {
        isSubmitting.value = false;
    }
};

const toggleActive = async (u) => {
    try {
        const res = await api.patch(`/users/${u.id}/toggle-active`);
        u.is_active = res.data?.is_active;
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: e.response?.data?.message || 'تعذر تغيير حالة الحساب' });
    }
};

const deleteUser = async (u) => {
    const result = await Swal.fire({
        title: `حذف حساب ${u.name}؟`,
        text: 'هل أنت متأكد من حذف هذا المستخدم؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#334155',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/users/${u.id}`);
            Swal.fire({ icon: 'success', title: 'تم الحذف', timer: 1500, showConfirmButton: false });
            fetchUsers();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'خطأ', text: e.response?.data?.message || 'تعذر حذف الحساب' });
        }
    }
};

const getRoleLabel = (role) => {
    return matchRole(role);
};

const matchRole = (role) => {
    switch (role) {
        case 'admin': return 'مدير النظام 👑';
        case 'cashier': return 'كاشير 🛒';
        case 'storekeeper': return 'أمين مخزن 📦';
        case 'accountant': return 'محاسب 💼';
        default: return role;
    }
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'admin': return 'bg-purple-500/10 border-purple-500/30 text-purple-400';
        case 'cashier': return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
        case 'storekeeper': return 'bg-amber-500/10 border-amber-500/30 text-amber-400';
        case 'accountant': return 'bg-cyan-500/10 border-cyan-500/30 text-cyan-400';
        default: return 'bg-slate-500/10 border-slate-500/30 text-slate-400';
    }
};

onMounted(() => {
    fetchUsers();
});
</script>
