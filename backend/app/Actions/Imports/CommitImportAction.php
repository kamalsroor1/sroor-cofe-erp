<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\DTOs\Imports\CommitImportDTO;
use App\Enums\ImportType;
use App\Enums\LimitResource;
use App\Models\Item;
use App\Services\Entitlements\PlanEnforcer;
use App\Services\Import\ImportAnalyzer;
use App\Services\Import\ImportContext;
use App\Services\Import\ImportHandlerFactory;
use App\Services\Import\ImportRow;
use App\Services\Import\ImportSessionStore;
use App\Support\PartyLimitCounter;
use App\Support\TenantCache;
use App\Support\TenantClock;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CommitImportAction
{
    /** Upper bound of one commit (PHP time limit and lock lifetime base). */
    private const TIME_LIMIT_SECONDS = 300;

    public function __construct(
        private readonly ImportAnalyzer $analyzer,
        private readonly ImportHandlerFactory $handlers,
        private readonly ImportSessionStore $sessions,
        private readonly PlanEnforcer $planEnforcer,
        private readonly TenantClock $clock,
    ) {}

    /**
     * Re-parse and re-validate the stored file (the preview is never trusted), then write every
     * valid row in ONE transaction: any failure rolls the whole import back.
     *
     * @return array{created: int, skipped: int, duplicates: int, errors: list<array{row_number: int, errors: list<string>}>}
     */
    public function execute(CommitImportDTO $dto): array
    {
        // A big import can outlive the default 30 s PHP limit (php-fpm/CLI); the transaction is all or nothing.
        set_time_limit(self::TIME_LIMIT_SECONDS);

        // One commit per tenant and import type at a time: a double click, a retry, or a second
        // token of the same file (two tabs) while the first run is still writing is refused
        // instead of writing twice (the duplicate check of each run cannot see the other's
        // uncommitted rows). The token is looked up INSIDE the lock, so a run that waited behind
        // a finished one finds it consumed (404) or its rows already present (duplicates).
        $lock = Cache::lock(TenantCache::key('import-commit:'.$dto->type->value), self::TIME_LIMIT_SECONDS + 60);
        if (! $lock->get()) {
            throw new HttpException(409, (string) __('import.errors.already_running'));
        }

        try {
            return $this->commit($dto);
        } finally {
            $lock->release();
        }
    }

    /**
     * Plan limit for the whole batch (items; PLAN-11 customers / suppliers), counted under the
     * tenant lock inside the import transaction, before anything is written: an import that would
     * pass the limit is refused as a whole (403 subscription.limit_reached), never cut in half.
     * Every valid row is counted as a new record (an imported row that looks like the walk-in
     * customer is not special-cased: the check stays conservative).
     */
    private function assertWithinPlanLimit(ImportType $type, int $adding): void
    {
        match ($type) {
            ImportType::Items => $this->planEnforcer->assertWithinLimit(LimitResource::Items, fn (): int => Item::query()->count(), $adding),
            ImportType::Customers => $this->planEnforcer->assertWithinLimit(LimitResource::Customers, fn (): int => PartyLimitCounter::customers(), $adding),
            ImportType::Suppliers => $this->planEnforcer->assertWithinLimit(LimitResource::Suppliers, fn (): int => PartyLimitCounter::suppliers(), $adding),
            ImportType::Categories => null,
        };
    }

    /**
     * @return array{created: int, skipped: int, duplicates: int, errors: list<array{row_number: int, errors: list<string>}>}
     */
    private function commit(CommitImportDTO $dto): array
    {
        $session = $this->sessions->find($dto->token, $dto->type, $dto->userId);
        if ($session === null) {
            throw new HttpException(404, (string) __('import.errors.token_not_found'));
        }

        $analysis = $this->analyzer->analyze($dto->type, $session['absolute_path'], $session['extension']);

        $invalid = $analysis->withStatus(ImportRow::STATUS_INVALID);
        if ($invalid !== [] && ! $dto->skipInvalid) {
            throw ValidationException::withMessages([
                'skip_invalid' => __('import.errors.invalid_rows_present', ['count' => count($invalid)]),
            ]);
        }

        // IMP-01: a typo in a category name must not silently become a new category.
        if ($analysis->newCategories !== [] && ! $dto->confirmNewCategories) {
            $separator = (string) __('import.list_separator');

            throw ValidationException::withMessages([
                'confirm_new_categories' => __('import.errors.new_categories_unconfirmed', [
                    'count' => count($analysis->newCategories),
                    'names' => implode($separator, array_slice($analysis->newCategories, 0, 10)).(count($analysis->newCategories) > 10 ? $separator.'…' : ''),
                ]),
            ]);
        }

        $valid = array_map(static fn (ImportRow $row): array => $row->data, $analysis->withStatus(ImportRow::STATUS_VALID));
        $duplicates = count($analysis->withStatus(ImportRow::STATUS_DUPLICATE));

        $context = new ImportContext($dto->userId, $dto->storeId, $this->clock->businessDate());
        $handler = $this->handlers->make($dto->type);

        try {
            $created = DB::transaction(function () use ($dto, $valid, $handler, $context): int {
                if ($valid !== []) {
                    $this->assertWithinPlanLimit($dto->type, count($valid));
                }

                return $handler->write($valid, $context);
            });
        } catch (UniqueConstraintViolationException) {
            // A record created concurrently took a code/name the preview saw as free: whole import rolled back.
            throw ValidationException::withMessages(['file' => __('import.errors.conflict')]);
        } catch (QueryException $exception) {
            // IMP-01: all or nothing. Any database failure mid-write rolled every chunk back; say so
            // clearly (the details go to the log, never to the response).
            report($exception);

            throw ValidationException::withMessages(['file' => __('import.errors.commit_failed')]);
        }

        $this->sessions->forget($dto->token, $session['path']);

        return [
            'created' => $created,
            'skipped' => count($invalid) + $duplicates,
            'duplicates' => $duplicates,
            'errors' => array_map(
                static fn (ImportRow $row): array => ['row_number' => $row->rowNumber, 'errors' => $row->errors],
                $invalid,
            ),
        ];
    }
}
