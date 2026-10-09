<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Settings\SendTestTelegramAction;
use App\Actions\Settings\UpdateSettingsAction;
use App\DTOs\Settings\TelegramTestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SendTestTelegramRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\Settings\SettingSecrets;
use App\Services\Settings\TenantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class SettingController extends Controller
{
    public function __construct(
        private readonly UpdateSettingsAction $updateSettingsAction,
        private readonly TenantSettings $tenantSettings,
        private readonly SendTestTelegramAction $sendTestTelegramAction,
    ) {}

    /**
     * Get system settings dictionary
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('roles.manage') && ! $user->can('settings.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $tenant = function_exists('tenant') ? tenant() : null;
        $defaultName = $tenant?->name ?? __('auth.default_company_name');

        $settings = [
            'company_name' => Setting::get('company_name', $defaultName),
            'company_subtitle' => Setting::get('company_subtitle', ''),
            'company_phone' => Setting::get('company_phone', ''),
            'company_address' => Setting::get('company_address', ''),
            'invoice_footer_note' => Setting::get('invoice_footer_note', ''),
            'show_print_company_name' => Setting::getBool('show_print_company_name', true),
            'show_print_subtitle' => Setting::getBool('show_print_subtitle', true),
            'show_print_logo' => Setting::getBool('show_print_logo', true),
            'thermal_show_customer_balance' => Setting::getBool('thermal_show_customer_balance', true),
            'print_show_qr' => Setting::getBool('print_show_qr', true),
            'invoice_primary_color' => Setting::get('invoice_primary_color', 'emerald'),
            'system_theme_color' => Setting::get('system_theme_color', 'emerald'),
            // SETG-10: same list item create/update validate against.
            TenantSettings::KEY_INVENTORY_UNITS => implode(',', $this->tenantSettings->inventoryUnits()),
            'telegram_bot_token' => Setting::get('telegram_bot_token', ''),
            'telegram_chat_id' => Setting::get('telegram_chat_id', ''),
            TenantSettings::KEY_COMMERCIAL_REGISTER => Setting::get(TenantSettings::KEY_COMMERCIAL_REGISTER, ''),
            TenantSettings::KEY_TAX_REGISTRATION_NO => Setting::get(TenantSettings::KEY_TAX_REGISTRATION_NO, ''),
            'telegram_notifications_enabled' => Setting::getBool('telegram_notifications_enabled', true),
            ...$this->tenantSettings->toArray(),
        ];

        // SETG-7: secrets are write-only — never echo them, only whether one is stored.
        $settings = SettingSecrets::mask($settings);

        $stores = Store::where('is_active', true)->select('id', 'name', 'code')->get();
        $usersCount = User::count();

        $systemInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'db_driver' => config('database.default'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Local/Laragon',
        ];

        return response()->json([
            'success' => true,
            'settings' => $settings,
            'stores' => $stores,
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
        } catch (Throwable $e) {
            // SETG-7: log the cause server-side; never return the exception text (SQL, paths, secrets).
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('settings.settings_save_failed'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('settings.settings_saved_success'),
            'settings' => $updated,
        ], 200);
    }

    /**
     * Send test telegram notification
     */
    public function sendTestTelegram(SendTestTelegramRequest $request): JsonResponse
    {
        $result = $this->sendTestTelegramAction->execute(TelegramTestDTO::fromArray($request->validated()));

        return response()->json($result, 200);
    }
}
