<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Livewire\Traits\RequiresAuth;

#[Layout('components.layouts.app')]
#[Title('إدارة الأدوار ومصفوفة الصلاحيات | منظومة ERP')]
class RolePermissionManager extends Component
{
    use RequiresAuth;

    public $selectedRoleId = null;
    public $selectedRole = null;
    public $selectedPermissions = [];

    // Create New Role Modal
    public $showNewRoleModal = false;
    public $newRoleName = '';

    public function mount()
    {
        abort_if(!auth()->user()?->can('roles.manage'), 403, 'غير مصرح لك بالوصول لإدارة الصلاحيات والأدوار.');

        // Select the first role by default (admin or cashier)
        $firstRole = Role::where('name', 'cashier')->first() ?? Role::first();
        if ($firstRole) {
            $this->selectRole($firstRole->id);
        }
    }

    public function selectRole(int $roleId)
    {
        $this->selectedRoleId = $roleId;
        $this->selectedRole = Role::with('permissions')->find($roleId);

        if ($this->selectedRole) {
            $this->selectedPermissions = $this->selectedRole->permissions->pluck('name')->toArray();
        }
    }

    public function savePermissions()
    {
        if (!$this->selectedRole) return;

        // Admin role always keeps all permissions
        if ($this->selectedRole->name === 'admin') {
            $this->selectedRole->syncPermissions(Permission::all());
            $this->selectedPermissions = $this->selectedRole->permissions->pluck('name')->toArray();
            $this->dispatch('swal:toast', [
                'icon'  => 'info',
                'title' => 'دور المدير العام يمتلك كافة الصلاحيات بشكل دائم.'
            ]);
            return;
        }

        $this->selectedRole->syncPermissions($this->selectedPermissions);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->dispatch('swal:toast', [
            'icon'  => 'success',
            'title' => "تم حفظ وتحديث صلاحيات دور [{$this->getRoleDisplayName($this->selectedRole->name)}] بنجاح!"
        ]);
    }

    public function selectAll()
    {
        $this->selectedPermissions = Permission::pluck('name')->toArray();
    }

    public function unselectAll()
    {
        $this->selectedPermissions = [];
    }

    public function createRole()
    {
        $this->validate([
            'newRoleName' => 'required|string|max:50|unique:roles,name',
        ], [
            'newRoleName.required' => 'يرجى إدخال اسم الدور الجديد.',
            'newRoleName.unique'   => 'هذا الدور موجود بالفعل.',
        ]);

        $role = Role::create([
            'name'       => strtolower(trim($this->newRoleName)),
            'guard_name' => 'web',
        ]);

        $this->showNewRoleModal = false;
        $this->newRoleName = '';
        $this->selectRole($role->id);

        $this->dispatch('swal:toast', [
            'icon'  => 'success',
            'title' => "تم إنشاء الدور الجديد [{$role->name}] بنجاح."
        ]);
    }

    public function getRoleDisplayName(string $roleName): string
    {
        return \App\Enums\UserRole::getFormatted($roleName);
    }

    public function getPermissionGroups(): array
    {
        return [
            'pos' => [
                'title' => '🛒 نقاط البيع والمبيعات (POS & Sales)',
                'color' => 'emerald',
                'items' => [
                    'pos.access'        => 'الوصول لشاشة نقطة البيع (POS)',
                    'invoices.view'     => 'عرض سجل فواتير المبيعات',
                    'invoices.create'   => 'إنشاء واعتماد فواتير مبيعات',
                    'invoices.edit'     => 'تعديل الفواتير المعتمدة',
                    'invoices.cancel'   => 'إلغاء الفواتير وعكس أثر المخزون',
                    'invoices.delete'   => 'أرشفة وحذف فواتير المبيعات',
                    'invoices.discount' => 'صلاحية منح خصومات للعملاء',
                ]
            ],
            'inventory' => [
                'title' => '📦 الأصناف والمخزون (Inventory)',
                'color' => 'amber',
                'items' => [
                    'items.view'      => 'عرض قائمة الأصناف والأسعار',
                    'items.create'    => 'إضافة أصناف جديدة للمخزون',
                    'items.edit'      => 'تعديل بيانات وأسعار الأصناف',
                    'items.delete'    => 'أرشفة وحذف الأصناف',
                    'items.view_cost' => 'رؤية سعر التكلفة وهوامش الربح',
                ]
            ],
            'purchases' => [
                'title' => '📥 المشتريات والتوريدات (Purchases)',
                'color' => 'blue',
                'items' => [
                    'purchases.view'   => 'عرض سجل فواتير المشتريات',
                    'purchases.create' => 'تسجيل وتوريد مشتريات للمخزن',
                    'purchases.delete' => 'أرشفة فواتير المشتريات',
                ]
            ],
            'stores' => [
                'title' => '🏬 الفروع والتحويلات المخزنية (Stores & Transfers)',
                'color' => 'indigo',
                'items' => [
                    'stores.manage'    => 'إدارة الفروع وتعيين الموظفين',
                    'transfers.view'   => 'عرض أذونات التحويل المخزني',
                    'transfers.create' => 'إنشاء أذونات تحويل وشحن العربات',
                ]
            ],
            'contacts' => [
                'title' => '👥 العملاء والموردين (Contacts)',
                'color' => 'cyan',
                'items' => [
                    'customers.manage'    => 'إدارة دليل العملاء والمديونيات',
                    'customers.statement' => 'عرض وتصدير كشف حساب عميل',
                    'suppliers.manage'    => 'إدارة دليل الموردين وحساباتهم',
                    'suppliers.statement' => 'عرض وتصدير كشف حساب مورد',
                ]
            ],
            'finance' => [
                'title' => '💰 المالية واليومية والمصروفات (Financials)',
                'color' => 'purple',
                'items' => [
                    'daily_journal.view'        => 'عرض اليومية النقدية والدرج والشفتات',
                    'daily_journal.close_shift' => 'فتح وتقفيل ورديات الكاشير واليومية',
                    'expenses.manage'           => 'تسجيل وتعديل وحذف المصروفات',
                    'returns.manage'            => 'إدارة مرتجعات المبيعات والمشتريات',
                ]
            ],
            'admin' => [
                'title' => '⚙️ الإدارة والتقارير وسلة المحذوفات (Administration)',
                'color' => 'rose',
                'items' => [
                    'reports.view' => 'عرض تقارير الأرباح والمبيعات ومقارنة الفروع',
                    'trash.access' => 'الوصول لسلة المحذوفات المركزية واسترجاع البيانات',
                    'roles.manage' => 'إدارة المستخدمين والأدوار ومصفوفة الصلاحيات',
                    'logs.view'    => 'عرض وفحص سجل العمليات والرقابة الذاتية',
                ]
            ],
        ];
    }

    public function render()
    {
        $roles = Role::withCount('users', 'permissions')->get();
        $permissionGroups = $this->getPermissionGroups();

        return view('livewire.auth.role-permission-manager', [
            'roles'            => $roles,
            'permissionGroups' => $permissionGroups,
        ]);
    }
}
