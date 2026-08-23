<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Livewire\Traits\RequiresAuth;

#[Layout('components.layouts.app')]
#[Title('إدارة المستخدمين وصلاحيات الكاشير | منظومة ERP')]
class UserManager extends Component
{
    use WithPagination, RequiresAuth;

    public string $search = '';
    public string $filterRole = 'all';

    // Modal state
    public bool $showUserModal = false;
    public ?int $editingUserId = null;

    // Form fields
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = 'cashier'; // admin, cashier, storekeeper, accountant
    public bool $is_active = true;

    public function mount()
    {
        abort_if(!auth()->user()?->can('roles.manage'), 403, 'غير مصرح لك بالوصول لإدارة المستخدمين والصلاحيات.');
    }

    protected function rules(): array
    {
        $userId = $this->editingUserId;

        return [
            'name'      => ['required', 'string', 'max:255'],
            'phone'     => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->whereNull('deleted_at')->ignore($userId)],
            'email'     => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($userId)],
            'password'  => [$userId ? 'nullable' : 'required', 'string', 'min:6'],
            'role'      => ['required', 'string', 'exists:roles,name'],
            'is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'     => 'يرجى إدخال اسم المستخدم.',
            'phone.required'    => 'يرجى إدخال رقم الهاتف للدخول.',
            'phone.unique'      => 'رقم الهاتف هذا مسجل بالفعل لمستخدم آخر.',
            'email.email'       => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique'      => 'هذا البريد الإلكتروني مسجل بالفعل لمستخدم آخر.',
            'password.required' => 'يرجى تحديد كلمة المرور للمستخدم الجديد.',
            'password.min'      => 'كلمة المرور يجب ألا تقل عن 6 أحرف.',
            'role.required'     => 'يرجى اختيار الصلاحية.',
        ];
    }

    public function openCreateModal()
    {
        $this->resetValidation();
        $this->reset(['editingUserId', 'name', 'phone', 'email', 'password', 'password_confirmation', 'role', 'is_active']);
        $this->role = 'cashier';
        $this->is_active = true;
        $this->showUserModal = true;
    }

    public function openEditModal(int $id)
    {
        $this->resetValidation();
        $user = User::findOrFail($id);
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->phone = $user->phone ?? '';
        $this->email = $user->email ?? '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->role = $user->roles->first()?->name ?? 'cashier';
        $this->is_active = (bool) $user->is_active;
        $this->showUserModal = true;
    }

    public function saveUser()
    {
        $this->validate();

        if ($this->editingUserId) {
            $user = User::findOrFail($this->editingUserId);
            $data = [
                'name'      => $this->name,
                'phone'     => $this->phone,
                'email'     => !empty($this->email) ? $this->email : "{$this->phone}@sroor.com",
                'is_active' => $this->is_active,
            ];

            if (!empty($this->password)) {
                $data['password'] = Hash::make($this->password);
            }

            $user->update($data);
            $user->syncRoles([$this->role]);

            $this->dispatch('swal:toast', [
                'type'  => 'success',
                'title' => 'تم تعديل المستخدم!',
                'text'  => "تم تحديث بيانات المستخدم {$user->name} بنجاح."
            ]);
        } else {
            $user = User::create([
                'name'      => $this->name,
                'phone'     => $this->phone,
                'email'     => !empty($this->email) ? $this->email : "{$this->phone}@sroor.com",
                'password'  => Hash::make($this->password),
                'is_active' => $this->is_active,
            ]);

            $user->syncRoles([$this->role]);

            $this->dispatch('swal:toast', [
                'type'  => 'success',
                'title' => 'تم إنشاء المستخدم!',
                'text'  => "تم إضافة المستخدم الجديد {$user->name} بنجاح."
            ]);
        }

        $this->showUserModal = false;
        $this->reset(['editingUserId', 'name', 'phone', 'email', 'password', 'password_confirmation']);
    }

    public function toggleUserStatus(int $id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deactivating own account
        if ($user->id === auth()->id()) {
            $this->dispatch('swal:toast', [
                'type'  => 'error',
                'title' => 'غير مسموح!',
                'text'  => 'لا يمكنك تعطيل حسابك الحالي المسجل به الدخول.'
            ]);
            return;
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $statusText = $user->is_active ? 'تفعيل' : 'تعطيل';
        $this->dispatch('swal:toast', [
            'type'  => 'info',
            'title' => "تم {$statusText} الحساب!",
            'text'  => "تم تحديث حالة حساب {$user->name} بنجاح."
        ]);
    }

    public function render()
    {
        $query = User::with('roles')
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            });

        $users = $query->latest()->paginate(10);
        $availableRoles = \Spatie\Permission\Models\Role::all();

        return view('livewire.auth.user-manager', [
            'users'          => $users,
            'availableRoles' => $availableRoles,
        ]);
    }
}
