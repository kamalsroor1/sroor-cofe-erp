<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Traits\RequiresAuth;
use App\Models\Setting;
use App\Services\TelegramService;
use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\Artisan;

#[Layout('components.layouts.app')]
#[Title('إعدادات النظام المتقدمة والطباعة | منظومة ERP')]
class SettingsIndex extends Component
{
    use RequiresAuth, WithFileUploads;

    public string $activeTab = 'branding'; // 'branding', 'telegram', 'backup', 'system'

    // Branding & Printing Settings
    public string $company_name = '';
    public string $company_subtitle = '';
    public string $invoice_footer_note = 'شكراً لتعاملكم معنا - البضاعة المباعة ترد وتستبدل خلال 14 يوماً';
    public bool $show_print_company_name = true;
    public bool $show_print_subtitle = true;
    public bool $show_print_logo = true;
    public bool $thermal_show_customer_balance = true;
    public bool $print_show_qr = true;
    public string $invoice_primary_color = 'amber';
    public $logo_file = null;

    // Telegram Bot Notifications
    public ?string $telegram_bot_token = '';
    public ?string $telegram_chat_id = '';
    public bool $telegram_notifications_enabled = true;
    public string $telegramStatusMessage = '';

    public function mount()
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح لك بالوصول إلى إعدادات النظام');

        // Load Branding Settings
        $this->company_name = Setting::get('company_name', config('app.name', 'منظومة ERP'));
        $this->company_subtitle = Setting::get('company_subtitle', '');
        $this->invoice_footer_note = Setting::get('invoice_footer_note', 'شكراً لتعاملكم معنا - البضاعة المباعة ترد وتستبدل خلال 14 يوماً');
        $this->show_print_company_name = Setting::getBool('show_print_company_name', true);
        $this->show_print_subtitle = Setting::getBool('show_print_subtitle', true);
        $this->show_print_logo = Setting::getBool('show_print_logo', true);
        $this->thermal_show_customer_balance = Setting::getBool('thermal_show_customer_balance', true);
        $this->print_show_qr = Setting::getBool('print_show_qr', true);
        $this->invoice_primary_color = Setting::get('invoice_primary_color', 'amber');

        // Load Telegram Settings
        $this->telegram_bot_token = (string)(Setting::get('telegram_bot_token') ?? config('services.telegram.bot_token') ?? '');
        $this->telegram_chat_id = (string)(Setting::get('telegram_chat_id') ?? config('services.telegram.chat_id') ?? '');
        $this->telegram_notifications_enabled = Setting::getBool('telegram_notifications_enabled', true);
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function updateBrandingSettings()
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح');

        $this->validate([
            'company_name'                   => ['required', 'string', 'max:255'],
            'company_subtitle'               => ['nullable', 'string', 'max:255'],
            'invoice_footer_note'            => ['nullable', 'string', 'max:500'],
            'show_print_company_name'        => ['boolean'],
            'show_print_subtitle'            => ['boolean'],
            'show_print_logo'                => ['boolean'],
            'thermal_show_customer_balance'  => ['boolean'],
            'print_show_qr'                  => ['boolean'],
            'invoice_primary_color'          => ['required', 'string', 'in:amber,emerald,blue,slate'],
            'logo_file'                      => ['nullable', 'image', 'max:3072'],
        ], [
            'company_name.required' => 'يرجى إدخال اسم المؤسسة أو النشاط.',
            'logo_file.image'       => 'الملف المرفوع يجب أن يكون صورة صالحة (PNG/JPG/WEBP).',
            'logo_file.max'         => 'حجم اللوجو يجب ألا يتجاوز 3 ميجابايت.',
        ]);

        if ($this->logo_file) {
            @copy($this->logo_file->getRealPath(), public_path('logo.png'));
            $this->reset('logo_file');
        }

        Setting::set('company_name', $this->company_name);
        Setting::set('company_subtitle', $this->company_subtitle ?? '');
        Setting::set('invoice_footer_note', $this->invoice_footer_note ?? '');
        Setting::set('show_print_company_name', $this->show_print_company_name ? '1' : '0');
        Setting::set('show_print_subtitle', $this->show_print_subtitle ? '1' : '0');
        Setting::set('show_print_logo', $this->show_print_logo ? '1' : '0');
        Setting::set('thermal_show_customer_balance', $this->thermal_show_customer_balance ? '1' : '0');
        Setting::set('print_show_qr', $this->print_show_qr ? '1' : '0');
        Setting::set('invoice_primary_color', $this->invoice_primary_color);

        $this->dispatch('swal:toast', [
            'type'  => 'success',
            'title' => 'تم حفظ إعدادات الهوية والطباعة!',
            'text'  => 'تم تحديث الشعار والبيانات العامة المطبوعة على الفواتير بنجاح.'
        ]);
    }

    public function updateTelegramSettings()
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح');

        Setting::set('telegram_bot_token', trim($this->telegram_bot_token));
        Setting::set('telegram_chat_id', trim($this->telegram_chat_id));
        Setting::set('telegram_notifications_enabled', $this->telegram_notifications_enabled ? '1' : '0');

        $this->dispatch('swal:toast', [
            'type'  => 'success',
            'title' => 'تم حفظ إعدادات تيليجرام!',
            'text'  => 'تم تحديث بيانات البوت ومعرف المحادثة والجروب بنجاح.'
        ]);
    }

    public function sendTestTelegramMessage(TelegramService $telegramService)
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح');

        Setting::set('telegram_bot_token', trim($this->telegram_bot_token));
        Setting::set('telegram_chat_id', trim($this->telegram_chat_id));

        $res = $telegramService->sendTestNotification(trim($this->telegram_chat_id));
        $this->telegramStatusMessage = ($res['success'] ? '✅ ' : '❌ ') . $res['message'];

        $this->dispatch('swal:toast', [
            'type'  => $res['success'] ? 'success' : 'error',
            'title' => $res['success'] ? 'تم إرسال الرسالة بنجاح!' : 'فشل الإرسال',
            'text'  => $res['message']
        ]);
    }

    public function sendDailySummaryTest(TelegramService $telegramService)
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح');

        $res = $telegramService->sendDailySummaryNotification();
        $this->telegramStatusMessage = ($res['success'] ? '✅ ' : '❌ ') . $res['message'];

        $this->dispatch('swal:toast', [
            'type'  => $res['success'] ? 'success' : 'error',
            'title' => $res['success'] ? 'تم إرسال تقرير اليومية!' : 'فشل الإرسال',
            'text'  => $res['message']
        ]);
    }

    public function sendLowStockTest(TelegramService $telegramService)
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح');

        $res = $telegramService->sendLowStockNotification(previewSample: true);
        $this->telegramStatusMessage = ($res['success'] ? '✅ ' : '❌ ') . $res['message'];

        $this->dispatch('swal:toast', [
            'type'  => $res['success'] ? 'success' : 'error',
            'title' => $res['success'] ? 'تم إرسال إنذار النواقص!' : 'فشل الإرسال',
            'text'  => $res['message']
        ]);
    }

    public function sendOverdueShiftTest(TelegramService $telegramService)
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح');

        $res = $telegramService->sendOverdueShiftNotification(previewSample: true);
        $this->telegramStatusMessage = ($res['success'] ? '✅ ' : '❌ ') . $res['message'];

        $this->dispatch('swal:toast', [
            'type'  => $res['success'] ? 'success' : 'error',
            'title' => $res['success'] ? 'تم إرسال إنذار الشفتات!' : 'فشل الإرسال',
            'text'  => $res['message']
        ]);
    }

    public function sendDatabaseBackupTest(TelegramService $telegramService)
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح');

        $res = $telegramService->sendDatabaseBackupNotification();
        $this->telegramStatusMessage = ($res['success'] ? '✅ ' : '❌ ') . $res['message'];

        $this->dispatch('swal:toast', [
            'type'  => $res['success'] ? 'success' : 'error',
            'title' => $res['success'] ? 'تم إرسال النسخة الاحتياطية!' : 'فشل الإرسال',
            'text'  => $res['message']
        ]);
    }

    public function downloadDatabaseBackupDirectly(DatabaseBackupService $backupService)
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح');

        $gzPath = $backupService->createSqlGzBackup();
        $fileName = basename($gzPath);

        return response()->download($gzPath, $fileName)->deleteFileAfterSend(true);
    }

    public function clearAndOptimizeCache()
    {
        abort_if(!auth()->user()?->hasRole('admin'), 403, 'غير مصرح');

        try {
            Artisan::call('optimize:clear');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            Setting::clearCache();

            $this->dispatch('swal:toast', [
                'type'  => 'success',
                'title' => 'تم تنظيف وإعادة بناء الكاش بنجاح!',
                'text'  => 'تم تسريع النظام وتحديث كافة ملفات التخزين المؤقت.'
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('swal:toast', [
                'type'  => 'error',
                'title' => 'حدث خطأ أثناء تنظيف الكاش',
                'text'  => $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.settings-index')
            ->layout('components.layouts.app', ['title' => 'إعدادات النظام والطباعة والنسخ الاحتياطي']);
    }
}
