<?php

declare(strict_types=1);

namespace App\Enums;

use Laravel\Horizon\Horizon;

/**
 * The browser monitoring dashboards a platform operator can open from the console through
 * the single-use signed link (IssueTelescopeLinkAction -> /telescope-access). Each one is a
 * web route behind its own gate on the `central_web` session guard.
 */
enum MonitoringTool: string
{
    case Telescope = 'telescope';
    case Pulse = 'pulse';
    case Horizon = 'horizon';

    /** Path the access bridge redirects to after it opens the `central_web` session. */
    public function path(): string
    {
        return match ($this) {
            self::Telescope => '/'.trim((string) config('telescope.path', 'telescope'), '/'),
            self::Pulse => '/'.trim((string) config('pulse.path', 'pulse'), '/'),
            self::Horizon => '/'.trim((string) config('horizon.path', 'horizon'), '/'),
        };
    }

    /**
     * Whether the dashboard is served in this environment. Telescope is off in production
     * (production standard #3, enforced by scripts/ops/check-env.sh), so its route does not
     * exist there and the console must not offer it.
     */
    public function isEnabled(): bool
    {
        return match ($this) {
            self::Telescope => (bool) config('telescope.enabled', false),
            self::Pulse => (bool) config('pulse.enabled', true),
            // Horizon only supervises Redis queues (production); elsewhere its dashboard is dead.
            self::Horizon => class_exists(Horizon::class) && config('queue.default') === 'redis',
        };
    }

    /**
     * @return list<string>
     */
    public static function enabledValues(): array
    {
        return array_values(array_map(
            static fn (self $tool): string => $tool->value,
            array_filter(self::cases(), static fn (self $tool): bool => $tool->isEnabled()),
        ));
    }
}
