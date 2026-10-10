<?php

declare(strict_types=1);

namespace App\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Used space of the volume holding storage/ (uploads, logs, backup temp files).
 *
 * Replaces spatie's UsedDiskSpaceCheck, which shells out to `df`: this one uses PHP's
 * disk_total_space()/disk_free_space(), so it behaves the same on the VPS, in CI and in
 * the deploy smoke test.
 */
final class DiskSpaceCheck extends Check
{
    private int $warnAbove = 80;

    private int $failAbove = 90;

    private ?string $path = null;

    public function warnWhenUsedSpaceIsAbovePercentage(int $percentage): self
    {
        $this->warnAbove = $percentage;

        return $this;
    }

    public function failWhenUsedSpaceIsAbovePercentage(int $percentage): self
    {
        $this->failAbove = $percentage;

        return $this;
    }

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function run(): Result
    {
        $path = $this->path ?? storage_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (! is_float($total) || $total <= 0.0 || ! is_float($free)) {
            return Result::make()->failed((string) __('console.health.disk_unknown'));
        }

        $percent = (int) round(100 * ($total - $free) / $total);
        $result = Result::make()->meta(['used_percent' => $percent])->shortSummary($percent.'%');
        $message = (string) __('console.health.disk_usage', ['percent' => $percent]);

        if ($percent > $this->failAbove) {
            return $result->failed($message);
        }
        if ($percent > $this->warnAbove) {
            return $result->warning($message);
        }

        return $result->ok();
    }
}
