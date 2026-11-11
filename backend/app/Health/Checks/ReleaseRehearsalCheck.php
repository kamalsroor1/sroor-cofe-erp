<?php

declare(strict_types=1);

namespace App\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * [CTO-2026-11-11] First-deploy rehearsal marker. `scripts/ops/render-env.sh --rehearsal`
 * writes RELEASE_REHEARSAL=true into the production .env when SMTP, Telegram or the Google
 * Drive OAuth credentials are not configured yet (MAIL_MAILER=log, BACKUP_DISKS=local).
 *
 * Monitoring only (never in the deploy gate, which must not warn): a warning on every
 * `health:check` run while the marker is on, naming what is relaxed. Warnings never send
 * mail (config/health.php only_on_failure), so this cannot become an alert loop.
 */
final class ReleaseRehearsalCheck extends Check
{
    public static function enabled(): bool
    {
        return filter_var(config('health.sroor.release_rehearsal', false), FILTER_VALIDATE_BOOL);
    }

    public function run(): Result
    {
        $result = Result::make();

        if (! self::enabled()) {
            return $result->ok();
        }

        $relaxed = [];
        if (config('mail.default') === 'log') {
            $relaxed[] = 'MAIL_MAILER=log';
        }
        $disks = (array) config('backup.tenants.disks', []);
        if (! in_array('google', $disks, true)) {
            $relaxed[] = 'BACKUP_DISKS='.implode(',', array_map('strval', $disks));
        }

        return $result
            ->shortSummary((string) __('console.health.release_rehearsal_short'))
            ->warning((string) __('console.health.release_rehearsal', [
                'relaxed' => $relaxed === [] ? '-' : implode(', ', $relaxed),
            ]));
    }
}
