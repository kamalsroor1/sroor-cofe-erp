<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Enums\TenantAccessLevel;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Support\Tenancy\TenantLifecyclePolicy;
use App\Support\Tenancy\TenantLifecycleSnapshot;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * IDEN-3.1 wiring: config/tenant_lifecycle.php holds the CTO-approved durations, the
 * container builds the policy from it, and every lifecycle label/message exists in
 * lang/{ar,en}/subscription.php with identical key sets.
 */
class TenantLifecycleConfigTest extends TestCase
{
    public function test_config_holds_the_cto_approved_durations(): void
    {
        $this->assertSame(14, config('tenant_lifecycle.trial_days'));
        $this->assertSame(7, config('tenant_lifecycle.trial_extension_days'));
        $this->assertSame(7, config('tenant_lifecycle.past_due_grace_days'));
        $this->assertSame(30, config('tenant_lifecycle.read_only_days'));
        $this->assertSame(90, config('tenant_lifecycle.retention_days'));
        $this->assertSame([7, 3, 1], config('tenant_lifecycle.reminder_days'));
        $this->assertSame(TenantLifecyclePolicy::MAX_CATCH_UP_STEPS, config('tenant_lifecycle.sweep.max_catch_up_steps'));
    }

    public function test_container_builds_the_policy_from_config(): void
    {
        config(['tenant_lifecycle.read_only_days' => 10]);

        $policy = $this->app->make(TenantLifecyclePolicy::class);

        $decision = $policy->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::ReadOnly,
            statusChangedAt: CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC'),
        ), CarbonImmutable::parse('2026-10-11 00:00:00', 'UTC'));

        $this->assertSame(TenantStatus::Suspended, $decision->effectiveStatus);
    }

    public function test_status_actor_and_access_labels_exist_in_both_locales(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (TenantStatus::cases() as $status) {
                $this->assertNotSame($status->translationKey(), $status->label($locale), "{$locale}: {$status->translationKey()}");
                $key = 'subscription.state_messages.'.$status->value;
                $this->assertNotSame($key, __($key, ['days' => 3], $locale), "{$locale}: {$key}");
            }
            foreach (TenantLifecycleActor::cases() as $actor) {
                $this->assertNotSame($actor->translationKey(), $actor->label($locale), "{$locale}: {$actor->translationKey()}");
            }
            foreach (TenantAccessLevel::cases() as $access) {
                $this->assertNotSame($access->translationKey(), $access->label($locale), "{$locale}: {$access->translationKey()}");
            }
            foreach (['already_used', 'already_paid', 'not_eligible'] as $reason) {
                $key = 'subscription.trial_extension.'.$reason;
                $this->assertNotSame($key, __($key, [], $locale), "{$locale}: {$key}");
            }
        }
    }

    public function test_subscription_lang_files_have_identical_keys(): void
    {
        $ar = require lang_path('ar/subscription.php');
        $en = require lang_path('en/subscription.php');

        $this->assertSame($this->flatKeys($en), $this->flatKeys($ar));
    }

    public function test_options_expose_value_and_translated_label(): void
    {
        app()->setLocale('en');

        $options = TenantStatus::options();

        $this->assertCount(7, $options);
        $this->assertSame('trial', $options[0]['value']);
        $this->assertSame(__('subscription.statuses.trial'), $options[0]['label']);
    }

    /**
     * @param  array<array-key, mixed>  $array
     * @return list<string>
     */
    private function flatKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $path = $prefix.$key;
            if (is_array($value)) {
                $keys = [...$keys, ...$this->flatKeys($value, $path.'.')];
            } else {
                $keys[] = $path;
            }
        }

        sort($keys);

        return $keys;
    }
}
