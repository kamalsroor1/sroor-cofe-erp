<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Livewire\Traits\RequiresAuth;

#[Layout('components.layouts.app')]
#[Title('الملف الشخصي وإعدادات الحساب | منظومة ERP')]
class Profile extends Component
{
    use RequiresAuth;

    // Personal Info
    public string $name = '';
    public string $email = '';
    public string $theme_preference = 'dark';

    // Security
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount()
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->theme_preference = $user->theme_preference ?? 'dark';
    }

    public function updateProfile()
    {
        $user = Auth::user();

        $this->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'theme_preference' => ['required', 'string', 'in:dark,light'],
        ], [
            'name.required'  => 'يرجى إدخال الاسم بالكامل.',
            'email.required' => 'يرجى إدخال البريد الإلكتروني.',
            'email.email'    => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique'   => 'هذا البريد الإلكتروني مسجل بالفعل لمستخدم آخر.',
        ]);

        $user->update([
            'name'             => $this->name,
            'email'            => $this->email,
            'theme_preference' => $this->theme_preference,
        ]);

        $this->dispatch('swal:toast', [
            'type'  => 'success',
            'title' => 'تم حفظ البيانات!',
            'text'  => 'تم تحديث البيانات الشخصية وتفضيل المظهر بنجاح.'
        ]);
        $this->dispatch('theme-changed', $this->theme_preference);
    }

    public function updatePassword()
    {
        $user = Auth::user();

        $this->validate([
            'current_password' => ['required', 'current_password'],
            'new_password'     => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'current_password.required'         => 'يرجى إدخال كلمة المرور الحالية.',
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
            'new_password.required'             => 'يرجى إدخال كلمة المرور الجديدة.',
            'new_password.min'                  => 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل.',
            'new_password.confirmed'            => 'تأكيد كلمة المرور غير متطابق.',
        ]);

        $user->update([
            'password' => Hash::make($this->new_password)
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        $this->dispatch('swal:toast', [
            'type'  => 'success',
            'title' => 'تم تغيير كلمة المرور!',
            'text'  => 'تم تحديث كلمة المرور بنجاح لأمان حسابك.'
        ]);
    }

    public function render()
    {
        return view('livewire.auth.profile', [
            'user' => Auth::user(),
        ])->layout('components.layouts.app', ['title' => 'الملف الشخصي وإعدادات الحساب']);
    }
}
