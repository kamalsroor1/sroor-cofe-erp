<?php

declare(strict_types=1);

namespace App\Support\Perf;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Pulse;
use Laravel\Telescope\Telescope;

/**
 * PERF-04: run a bulk job with the request/query instrumentation switched off.
 *
 * With Telescope recording, the perf seed of 2026-11-12 took more than 5 minutes for 5,000
 * invoices instead of 1.5 s: every INSERT was captured, serialised and written back as a
 * telescope_entries row. While $callback runs:
 *  - Telescope and Pulse stop recording (each restored to its previous state afterwards);
 *  - the default connection has no event dispatcher, so no QueryExecuted / transaction event
 *    reaches any listener (Telescope, Pulse, debug listeners), and its query log is off;
 *  - Eloquent model events are muted (observers, activity/audit logging).
 *
 * Nightwatch is not installed in this application, so there is nothing to switch off for it.
 * Everything is restored in `finally`, also when $callback throws.
 */
final class QuietInstrumentation
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(Closure $callback): mixed
    {
        $telescopeWasRecording = class_exists(Telescope::class) && Telescope::isRecording();
        if ($telescopeWasRecording) {
            Telescope::stopRecording();
        }

        $pulse = class_exists(Pulse::class) && app()->bound(Pulse::class) ? app(Pulse::class) : null;

        /** @var Connection $connection */
        $connection = DB::connection();
        $dispatcher = $connection->getEventDispatcher();
        $queryLogWasOn = $connection->logging();
        $connection->disableQueryLog();
        $connection->unsetEventDispatcher();

        try {
            // Pulse::ignore() restores Pulse's own previous recording state (on or off).
            return $pulse !== null
                ? $pulse->ignore(fn (): mixed => Model::withoutEvents($callback))
                : Model::withoutEvents($callback);
        } finally {
            if ($dispatcher !== null) {
                $connection->setEventDispatcher($dispatcher);
            }
            if ($queryLogWasOn) {
                $connection->enableQueryLog();
            }
            if ($telescopeWasRecording) {
                Telescope::startRecording(false);
            }
        }
    }
}
