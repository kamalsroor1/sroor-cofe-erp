<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Central\Data\SuperAdminMigrationResult;
use App\Actions\Central\SuperAdmins\LegacySuperAdminDirectory;
use App\Actions\Central\SuperAdmins\MigrateLegacySuperAdminsAction;
use App\Models\CentralUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * IDEN-1.6: move Phase 0 operators (central `users` + `super_admin` role) to CentralUser.
 *
 *   php artisan central:migrate-super-admins                        dry-run: list candidates
 *   php artisan central:migrate-super-admins --email=a@x --email=b@y  dry-run of that selection
 *   php artisan central:migrate-super-admins --email=a@x --execute    migrate exactly those
 *   php artisan central:migrate-super-admins --execute                ask per candidate (interactive)
 *
 * Nobody is promoted implicitly: without --email and without an interactive "yes" per
 * candidate nothing is written. Never prints a password hash or a token.
 */
final class MigrateSuperAdminsCommand extends Command
{
    protected $signature = 'central:migrate-super-admins
        {--email=* : Allowlist of legacy operator emails to migrate (repeatable)}
        {--execute : Write the changes (default is a dry run)}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.migrate_super_admins.description'));
    }

    public function handle(LegacySuperAdminDirectory $directory, MigrateLegacySuperAdminsAction $action): int
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            $this->error((string) __('console.migrate_super_admins.tenant_context'));

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $candidates = $directory->candidates();

        if ($candidates->isEmpty()) {
            $this->info((string) __('console.migrate_super_admins.no_candidates'));

            return self::SUCCESS;
        }

        $this->info((string) __($execute ? 'console.migrate_super_admins.mode_execute' : 'console.migrate_super_admins.mode_dry_run'));
        $this->listCandidates($candidates);

        $selected = $this->select($candidates, $execute);

        if ($selected === null) {
            return self::FAILURE;
        }

        if ($selected->isEmpty()) {
            $this->warn((string) __('console.migrate_super_admins.nothing_selected'));

            return self::SUCCESS;
        }

        if (! $execute) {
            foreach ($selected as $user) {
                $this->line((string) __('console.migrate_super_admins.would_migrate', ['id' => $user->getKey(), 'email' => $this->email($user)]));
            }
            $this->info((string) __('console.migrate_super_admins.dry_run_done'));

            return self::SUCCESS;
        }

        try {
            $results = $action->execute($selected);
        } catch (QueryException) {
            // Never echo a QueryException: its message carries the bound values (password hash).
            $this->error((string) __('console.migrate_super_admins.failed_database'));

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error((string) __('console.migrate_super_admins.failed', ['error' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->table(
            [
                (string) __('console.migrate_super_admins.col_legacy_id'),
                (string) __('console.migrate_super_admins.col_email'),
                (string) __('console.migrate_super_admins.col_status'),
                (string) __('console.migrate_super_admins.col_central_id'),
            ],
            array_map(static fn (SuperAdminMigrationResult $result): array => [
                $result->legacyUserId,
                $result->email,
                (string) __('console.migrate_super_admins.status.'.$result->status),
                $result->centralUserId ?? '-',
            ], $results),
        );

        $this->info((string) __('console.migrate_super_admins.done', [
            'count' => count(array_filter($results, static fn (SuperAdminMigrationResult $result): bool => $result->migrated())),
        ]));
        $this->info((string) __('console.migrate_super_admins.assertion_passed'));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $candidates
     */
    private function listCandidates(Collection $candidates): void
    {
        $this->table(
            [
                (string) __('console.migrate_super_admins.col_legacy_id'),
                (string) __('console.migrate_super_admins.col_name'),
                (string) __('console.migrate_super_admins.col_email'),
                (string) __('console.migrate_super_admins.col_in_central'),
            ],
            $candidates->map(fn (User $user): array => [
                $user->getKey(),
                (string) $user->getAttribute('name'),
                $this->email($user),
                (string) __($this->email($user) !== '' && CentralUser::query()->where('email', $this->email($user))->exists()
                    ? 'console.migrate_super_admins.yes'
                    : 'console.migrate_super_admins.no'),
            ])->all(),
        );
    }

    /**
     * --email allowlist, else (with --execute, interactive) one confirmation per candidate.
     * Returns null on an unusable selection (error already printed).
     *
     * @param  Collection<int, User>  $candidates
     * @return Collection<int, User>|null
     */
    private function select(Collection $candidates, bool $execute): ?Collection
    {
        $emails = collect((array) $this->option('email'))
            ->map(static fn (mixed $email): string => mb_strtolower(trim((string) $email)))
            ->filter(static fn (string $email): bool => $email !== '')
            ->unique()
            ->values();

        if ($emails->isNotEmpty()) {
            $selected = $candidates->filter(fn (User $user): bool => $emails->contains($this->email($user)))->values();

            foreach ($emails->diff($selected->map(fn (User $user): string => $this->email($user))) as $unknown) {
                $this->warn((string) __('console.migrate_super_admins.not_a_candidate', ['email' => $unknown]));
            }

            return $selected;
        }

        if (! $execute) {
            $this->line((string) __('console.migrate_super_admins.selection_hint'));

            return new Collection;
        }

        if (! $this->input->isInteractive()) {
            $this->error((string) __('console.migrate_super_admins.selection_required'));

            return null;
        }

        return $candidates->filter(fn (User $user): bool => $this->confirm(
            (string) __('console.migrate_super_admins.confirm_candidate', ['id' => $user->getKey(), 'email' => $this->email($user)]),
            false,
        ))->values();
    }

    private function email(User $user): string
    {
        return mb_strtolower(trim((string) $user->getAttribute('email')));
    }
}
