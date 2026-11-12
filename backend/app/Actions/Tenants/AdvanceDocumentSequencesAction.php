<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Support\Database\TransactionRetry;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * BKP-02: after a shop is restored from a backup, the document counters resume from the last
 * number in the BACKUP, so numbers printed after it (and lost with the data) would be handed out
 * again to different documents. This pushes every counter of the CURRENT tenant forward:
 *
 *  - every existing `document_sequences` row gets `last_value + margin`;
 *  - for every dated scope family ("INV-MAIN-20261110" = family "INV-MAIN"), a row seeded with
 *    `margin` is created for each business day from the newest day in the backup up to today
 *    (at most MAX_DAYS), because a day that began after the backup has no row and would restart
 *    at 1.
 *
 * Runs on the tenant connection: call it inside `$tenant->run()`. Idempotent in effect (calling
 * it twice only widens the gap). The margin must exceed the documents issued per scope between
 * the backup and the incident (RPO is ~24 h, see docs/07-operations/backup-restore.md).
 */
final class AdvanceDocumentSequencesAction
{
    private const TABLE = 'document_sequences';

    private const MAX_DAYS = 31;

    public function __construct(private readonly TenantClock $clock) {}

    /**
     * @return array{bumped: int, created: int}
     */
    public function execute(int $margin): array
    {
        if ($margin < 1) {
            throw new InvalidArgumentException('The sequence margin must be at least 1.');
        }

        /** @var array{bumped: int, created: int} */
        return TransactionRetry::run(function () use ($margin): array {
            $rows = DB::table(self::TABLE)->orderBy('id')->lockForUpdate()->get(['id', 'scope', 'last_value']);

            $bumped = 0;
            /** @var array<string, string> $newestDay family => YYYYMMDD */
            $newestDay = [];
            foreach ($rows as $row) {
                DB::table(self::TABLE)->where('id', $row->id)->update([
                    'last_value' => (int) $row->last_value + $margin,
                    'updated_at' => now(),
                ]);
                $bumped++;

                if (preg_match('/^(.+)-(\d{8})$/', (string) $row->scope, $m) === 1
                    && ($newestDay[$m[1]] ?? '') < $m[2]) {
                    $newestDay[$m[1]] = $m[2];
                }
            }

            $today = CarbonImmutable::parse($this->clock->businessDate())->startOfDay();
            $created = 0;
            foreach ($newestDay as $family => $day) {
                $from = CarbonImmutable::createFromFormat('Ymd', $day);
                if (! $from instanceof CarbonImmutable) {
                    continue;
                }
                $from = $from->startOfDay()->addDay();
                if ($from->lt($today->subDays(self::MAX_DAYS))) {
                    $from = $today->subDays(self::MAX_DAYS);
                }
                for ($date = $from; $date->lte($today); $date = $date->addDay()) {
                    $created += DB::table(self::TABLE)->insertOrIgnore([
                        'scope' => $family.'-'.$date->format('Ymd'),
                        'last_value' => $margin,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return ['bumped' => $bumped, 'created' => $created];
        });
    }
}
