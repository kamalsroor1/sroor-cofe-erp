<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle status of a tenant (central `tenants.status`), IDEN-3.1.
 *
 * `tenants.status` is the source of truth for ACCESS; `subscriptions.status`
 * (App\Enums\Billing\SubscriptionStatus) is the billing record only. There is no
 * `expired` tenant status: an ended trial or subscription is `read_only`.
 *
 * Path (CTO decisions Q-L1 / Q-L4, 2026-10-08):
 *   trial ──(trial ends)──────────────────────────────▶ read_only
 *   active ─(subscription ends)─▶ past_due ─(7 days)──▶ read_only
 *   read_only ─(30 days)─▶ suspended ─(90 days)─▶ archived
 *   cancelled ─(90 days)─▶ archived
 * A verified payment (actor Billing) brings any non-archived tenant back to `active`.
 *
 * Never rename or remove a value: it is stored in `tenants.status` and in lifecycle events.
 */
enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    /**
     * The state machine: from → to → actors allowed to make that move.
     * Anything not listed (including every self-transition) is invalid.
     *
     * TODO(CTO): `archived` is terminal here (no restore path), super-admin may archive
     * only an already suspended/cancelled tenant, and a manually suspended tenant is
     * archived by the sweep after the same 90-day retention. Confirm before OPS-9/IDEN-3.6.
     *
     * @return array<string, array<string, list<TenantLifecycleActor>>>
     */
    public static function transitions(): array
    {
        $system = TenantLifecycleActor::System;
        $admin = TenantLifecycleActor::SuperAdmin;
        $billing = TenantLifecycleActor::Billing;

        return [
            self::Trial->value => [
                self::Active->value => [$billing],
                self::ReadOnly->value => [$system, $admin],
                self::Suspended->value => [$admin],
                self::Cancelled->value => [$admin],
            ],
            self::Active->value => [
                self::PastDue->value => [$system],
                self::ReadOnly->value => [$admin],
                self::Suspended->value => [$admin],
                self::Cancelled->value => [$admin],
            ],
            self::PastDue->value => [
                self::Active->value => [$billing],
                self::ReadOnly->value => [$system, $admin],
                self::Suspended->value => [$admin],
                self::Cancelled->value => [$admin],
            ],
            self::ReadOnly->value => [
                self::Active->value => [$billing],
                // One-time trial extension of a never-paid tenant (Q-L4); eligibility is
                // checked by TenantLifecyclePolicy::extendTrial().
                self::Trial->value => [$admin],
                self::Suspended->value => [$system, $admin],
                self::Cancelled->value => [$admin],
            ],
            self::Suspended->value => [
                self::Active->value => [$billing],
                self::ReadOnly->value => [$admin],
                self::Cancelled->value => [$admin],
                self::Archived->value => [$system, $admin],
            ],
            self::Cancelled->value => [
                self::Active->value => [$billing],
                self::Archived->value => [$system, $admin],
            ],
            self::Archived->value => [],
        ];
    }

    public function canTransitionTo(self $to, TenantLifecycleActor $actor): bool
    {
        $actors = self::transitions()[$this->value][$to->value] ?? [];

        return in_array($actor, $actors, true);
    }

    /**
     * Statuses this actor may move the tenant to, in declaration order.
     *
     * @return list<self>
     */
    public function allowedTargets(TenantLifecycleActor $actor): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $to): bool => $this->canTransitionTo($to, $actor),
        ));
    }

    public function accessLevel(): TenantAccessLevel
    {
        return match ($this) {
            self::Trial, self::Active, self::PastDue => TenantAccessLevel::Full,
            self::ReadOnly => TenantAccessLevel::ReadOnly,
            self::Suspended, self::Cancelled, self::Archived => TenantAccessLevel::Blocked,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Archived;
    }

    public function translationKey(): string
    {
        return 'subscription.statuses.'.$this->value;
    }

    public function label(?string $locale = null): string
    {
        return (string) __($this->translationKey(), [], $locale);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Value/label pairs for selects and API payloads, in declaration order.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
