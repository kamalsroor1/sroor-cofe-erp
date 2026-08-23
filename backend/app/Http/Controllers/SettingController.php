<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\DatabaseBackupService;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = (string)$request->input('tab', 'branding');

        return Inertia::render('Settings/Index', [
            'active_tab' => $tab,
            'settings'   => [
                'company_name'                  => Setting::get('company_name', config('app.name', 'منظومة ERP')),
                'company_subtitle'              => Setting::get('company_subtitle', ''),
                'company_phone'                 => Setting::get('company_phone', ''),
                'company_address'               => Setting::get('company_address', ''),
                'invoice_footer_note'           => Setting::get('invoice_footer_note', 'شكراً لتعاملكم معنا - البضاعة المباعة ترد وتستبدل خلال 14 يوماً'),
                'show_print_company_name'       => Setting::getBool('show_print_company_name', true),
                'show_print_subtitle'           => Setting::getBool('show_print_subtitle', true),
                'show_print_logo'               => Setting::getBool('show_print_logo', true),
                'thermal_show_customer_balance' => Setting::getBool('thermal_show_customer_balance', true),
                'print_show_qr'                 => Setting::getBool('print_show_qr', true),
                'invoice_primary_color'         => Setting::get('invoice_primary_color', 'amber'),
                'system_theme_color'            => Setting::get('system_theme_color', 'amber'),
                'telegram_bot_token'            => Setting::get('telegram_bot_token', config('services.telegram.bot_token', '')),
                'telegram_chat_id'              => Setting::get('telegram_chat_id', config('services.telegram.chat_id', '')),
                'telegram_notifications_enabled'=> Setting::getBool('telegram_notifications_enabled', true),
            ],
            'system_info' => [
                'php_version'     => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment'     => app()->environment(),
                'db_driver'       => config('database.default'),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Local/Laragon',
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('logo_light_file')) {
            $file = $request->file('logo_light_file');
            @copy($file->getRealPath(), public_path('logo-light.png'));
            Setting::set('logo_light_v', (string)time());
            if (!file_exists(public_path('logo.png'))) {
                @copy($file->getRealPath(), public_path('logo.png'));
            }
        }

        if ($request->hasFile('logo_dark_file')) {
            $file = $request->file('logo_dark_file');
            @copy($file->getRealPath(), public_path('logo-dark.png'));
            Setting::set('logo_dark_v', (string)time());
            if (!file_exists(public_path('logo.png'))) {
                @copy($file->getRealPath(), public_path('logo.png'));
            }
        }

        if ($request->hasFile('logo_file')) {
            $file = $request->file('logo_file');
            @copy($file->getRealPath(), public_path('logo.png'));
            Setting::set('logo_v', (string)time());
            if (!file_exists(public_path('logo-light.png'))) {
                @copy($file->getRealPath(), public_path('logo-light.png'));
            }
            if (!file_exists(public_path('logo-dark.png'))) {
                @copy($file->getRealPath(), public_path('logo-dark.png'));
            }
        }

        $excludeKeys = ['logo_file', 'logo_light_file', 'logo_dark_file'];

        foreach ($validated as $key => $value) {
            if (in_array($key, $excludeKeys, true)) continue;
            if (is_bool($value)) {
                Setting::set($key, $value ? '1' : '0');
            } else {
                Setting::set($key, (string)($value ?? ''));
            }
        }

        Setting::clearCache();

        return redirect()->back()->with('success', __('nav.settings_saved_success'));
    }

    public function sendTestTelegram(Request $request, TelegramService $telegramService): RedirectResponse
    {
        $token = $request->input('bot_token');
        $chatId = $request->input('chat_id');

        if ($token) Setting::set('telegram_bot_token', trim((string)$token));
        if ($chatId) Setting::set('telegram_chat_id', trim((string)$chatId));

        $res = $telegramService->sendTestNotification(trim((string)$chatId));

        if ($res['success']) {
            return redirect()->back()->with('success', $res['message']);
        }
        return redirect()->back()->with('error', $res['message']);
    }

    public function sendDailySummaryTelegram(TelegramService $telegramService): RedirectResponse
    {
        $res = $telegramService->sendDailySummaryNotification();
        if ($res['success']) {
            return redirect()->back()->with('success', __('nav.telegram_daily_summary_sent'));
        }
        return redirect()->back()->with('error', $res['message']);
    }

    public function sendLowStockTelegram(TelegramService $telegramService): RedirectResponse
    {
        $res = $telegramService->sendLowStockNotification(previewSample: true);
        if ($res['success']) {
            return redirect()->back()->with('success', __('nav.telegram_low_stock_sent'));
        }
        return redirect()->back()->with('error', $res['message']);
    }

    public function sendOverdueShiftTelegram(TelegramService $telegramService): RedirectResponse
    {
        $res = $telegramService->sendOverdueShiftNotification(previewSample: true);
        if ($res['success']) {
            return redirect()->back()->with('success', __('nav.telegram_overdue_shift_sent'));
        }
        return redirect()->back()->with('error', $res['message']);
    }

    public function sendBackupTelegram(TelegramService $telegramService): RedirectResponse
    {
        $res = $telegramService->sendDatabaseBackupNotification();
        if ($res['success']) {
            return redirect()->back()->with('success', __('nav.telegram_backup_sent'));
        }
        return redirect()->back()->with('error', $res['message']);
    }

    public function downloadBackup(DatabaseBackupService $backupService): BinaryFileResponse
    {
        $gzPath = $backupService->createSqlGzBackup();
        $fileName = basename($gzPath);

        return response()->download($gzPath, $fileName)->deleteFileAfterSend(true);
    }

    public function clearCache(): RedirectResponse
    {
        try {
            Artisan::call('optimize:clear');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            Setting::clearCache();

            return redirect()->back()->with('success', __('nav.cache_cleared_success'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', __('nav.cache_clear_error', ['message' => $e->getMessage()]));
        }
    }
}