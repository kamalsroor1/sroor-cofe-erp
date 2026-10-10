<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Console\Commands\BackupTenantsCommand;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * CTO decision D4: without BACKUP_ARCHIVE_PASSWORD the backup commands refuse to run
 * everywhere except local/testing (BackupTenantsCommand::UNENCRYPTED_ENVIRONMENTS, tightened
 * by the W2 lane 3I security audit). Same rule here: red in every other environment
 * (production, staging, a typo in APP_ENV...), a warning in local/testing only.
 * The password itself is never read out: only its presence and length are checked.
 */
final class BackupArchivePasswordCheck extends Check
{
    /** Same threshold as scripts/ops/check-env.sh. */
    public const MIN_LENGTH = 24;

    private bool $failuresOnly = false;

    /** Deploy gate: report a failure or ok, never a warning. */
    public function failuresOnly(bool $failuresOnly = true): self
    {
        $this->failuresOnly = $failuresOnly;

        return $this;
    }

    public function run(): Result
    {
        $result = Result::make();
        $password = BackupTenantsCommand::archivePassword();

        if ($password === null) {
            if (! app()->environment(BackupTenantsCommand::UNENCRYPTED_ENVIRONMENTS)) {
                return $result->failed((string) __('console.health.archive_password_missing'));
            }

            return $this->failuresOnly ? $result->ok() : $result->warning((string) __('console.health.archive_password_missing_dev'));
        }

        if (mb_strlen($password) < self::MIN_LENGTH && ! $this->failuresOnly) {
            return $result->warning((string) __('console.health.archive_password_short', ['min' => self::MIN_LENGTH]));
        }

        return $result->ok();
    }
}
