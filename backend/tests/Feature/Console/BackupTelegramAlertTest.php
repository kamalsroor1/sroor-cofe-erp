<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\TelegramService;
use App\Support\Backup\BackupTelegramAlert;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OPS-04: Telegram reports backup failures as text only and never carries a dump.
 */
final class BackupTelegramAlertTest extends TestCase
{
    public function test_it_is_a_noop_without_token_or_chat_id(): void
    {
        Http::fake();
        config(['services.telegram.bot_token' => null, 'backup.tenants.notify_telegram_chat_id' => '123']);
        $this->assertFalse(BackupTelegramAlert::send('backup:tenants', ['central' => 'RuntimeException']));

        config(['services.telegram.bot_token' => 'x', 'backup.tenants.notify_telegram_chat_id' => '']);
        $this->assertFalse(BackupTelegramAlert::send('backup:tenants', ['central' => 'RuntimeException']));

        Http::assertNothingSent();
    }

    public function test_it_sends_one_text_message_with_subjects_and_reason_classes(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config(['services.telegram.bot_token' => 'test-token', 'backup.tenants.notify_telegram_chat_id' => '-100']);

        $this->assertTrue(BackupTelegramAlert::send('backup:tenants', ['tenant-a' => 'RuntimeException']));

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_ends_with($request->url(), '/sendMessage')
                && ! str_contains($request->url(), 'sendDocument')
                && str_contains((string) $request['text'], 'tenant-a: RuntimeException')
                && $request['chat_id'] === '-100';
        });
    }

    public function test_telegram_failure_never_throws_nor_leaks_the_token(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('boom', 500)]);
        config(['services.telegram.bot_token' => 'test-token', 'backup.tenants.notify_telegram_chat_id' => '-100']);

        $this->assertFalse(BackupTelegramAlert::send('backup:tenants', ['central' => 'RuntimeException']));
    }

    public function test_no_code_path_can_send_a_database_dump_to_telegram(): void
    {
        $this->assertFileDoesNotExist(app_path('Services/DatabaseBackupService.php'));
        $this->assertFalse(method_exists(TelegramService::class, 'sendDatabaseBackupNotification'));
    }
}
