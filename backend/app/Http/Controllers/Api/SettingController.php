<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Settings\UpdateSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class SettingController extends Controller
{
    public function __construct(
        private readonly UpdateSettingsAction $updateSettingsAction
    ) {}

    /**
     * Get system settings dictionary
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('roles.manage') && !$user->can('settings.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $tenant = function_exists('tenant') ? tenant() : null;
        $defaultName = $tenant?->name ?? 'مؤسسة تجارية';

        $settings = [
            'company_name'                   => Setting::get('company_name', $defaultName),
            'company_subtitle'               => Setting::get('company_subtitle', ''),
            'company_phone'                  => Setting::get('company_phone', ''),
            'company_address'                => Setting::get('company_address', ''),
            'invoice_footer_note'            => Setting::get('invoice_footer_note', ''),
            'show_print_company_name'        => Setting::getBool('show_print_company_name', true),
            'show_print_subtitle'            => Setting::getBool('show_print_subtitle', true),
            'show_print_logo'                => Setting::getBool('show_print_logo', true),
            'thermal_show_customer_balance'  => Setting::getBool('thermal_show_customer_balance', true),
            'print_show_qr'                  => Setting::getBool('print_show_qr', true),
            'invoice_primary_color'          => Setting::get('invoice_primary_color', 'emerald'),
            'system_theme_color'             => Setting::get('system_theme_color', 'emerald'),
            'inventory_units'                => Setting::get('inventory_units', 'قطعة,علبة,كرتونة,كجم,جرام,شيكارة,طرد,دستة,لتر'),
            'telegram_bot_token'             => Setting::get('telegram_bot_token', ''),
            'telegram_chat_id'               => Setting::get('telegram_chat_id', ''),
            'telegram_notifications_enabled' => Setting::getBool('telegram_notifications_enabled', true),
        ];

        $stores = Store::where('is_active', true)->select('id', 'name', 'code')->get();
        $usersCount = User::count();

        $systemInfo = [
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment'     => app()->environment(),
            'db_driver'       => config('database.default'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Local/Laragon',
        ];

        return response()->json([
            'success'     => true,
            'settings'    => $settings,
            'stores'      => $stores,
            'users_count' => $usersCount,
            'system_info' => $systemInfo,
        ], 200);
    }

    /**
     * Update system settings via Form Request and Single Action
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        try {
            $updated = $this->updateSettingsAction->execute($request->validated());

            return response()->json([
                'success'  => true,
                'message'  => __('nav.settings_saved_success') ?: 'تم حفظ وتحديث إعدادات النظام بنجاح ✓',
                'settings' => $updated,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل حفظ الإعدادات: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Send test telegram notification
     */
    public function sendTestTelegram(Request $request, TelegramService $telegramService): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('roles.manage') && !$user->can('settings.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $token = $request->input('bot_token');
        $chatId = $request->input('chat_id');

        if ($token) {
            Setting::set('telegram_bot_token', trim((string)$token));
        }
        if ($chatId) {
            Setting::set('telegram_chat_id', trim((string)$chatId));
        }

        $res = $telegramService->sendTestNotification(trim((string)$chatId));

        return response()->json([
            'success' => (bool)$res['success'],
            'message' => $res['message'],
        ], 200);
    }
}
