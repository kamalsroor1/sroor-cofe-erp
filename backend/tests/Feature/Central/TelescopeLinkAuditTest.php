<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Models\ActivityLog;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3G: issuing a Telescope link is audited in the CENTRAL audit log, with the
 * CentralUser as causer, and no longer through ActivityLogService (whose row could be
 * attributed to a `users` row that shares the operator's id). The nonce is never logged.
 */
final class TelescopeLinkAuditTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const URL = '/api/v1/super-admin/telescope-link';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    public function test_issuing_a_link_records_a_central_audit_row_for_the_operator(): void
    {
        $operator = $this->centralSuperAdmin();
        // A legacy `users` row with the same primary key must not be credited with the action.
        $this->legacyUsersTableSuperAdmin(['id' => $operator->getKey()]);
        $activityBefore = ActivityLog::query()->count();

        $url = (string) $this->postJson(self::URL, [], $this->centralHeaders($operator))
            ->assertOk()
            ->json('data.url');

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TelescopeLinkIssued->value)->sole();
        $this->assertSame(CentralUser::class, $log->causer_type);
        $this->assertSame((int) $operator->getKey(), (int) $log->causer_id);
        $this->assertArrayHasKey('expires_at', $log->properties ?? []);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $nonce = (string) ($query['n'] ?? '');
        $this->assertNotSame('', $nonce);
        $this->assertStringNotContainsString($nonce, (string) json_encode($log->properties));

        $this->assertSame($activityBefore, ActivityLog::query()->count(), 'no tenant-side activity row any more');
    }

    public function test_a_refused_issue_records_nothing(): void
    {
        $this->postJson(self::URL, [], $this->centralHeaders($this->centralSupport()))->assertForbidden();

        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TelescopeLinkIssued->value)->count());
    }

    public function test_every_audit_event_has_an_ar_and_en_label(): void
    {
        foreach (CentralAuditEvent::cases() as $event) {
            foreach (['ar', 'en'] as $locale) {
                $this->assertNotSame($event->translationKey(), $event->label($locale), "{$locale}: {$event->value}");
            }
        }
    }
}
