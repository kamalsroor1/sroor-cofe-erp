<?php

declare(strict_types=1);

namespace App\Actions\Central\SuperAdmins;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * IDEN-1.6, read-only queries over the Phase 0 operators: rows of the CENTRAL `users`
 * table holding a role named `super_admin` on ANY guard (spatie `model_has_roles`).
 * Every query is pinned to the central connection by name; never use inside a tenant.
 */
final class LegacySuperAdminDirectory
{
    public const ROLE = 'super_admin';

    /**
     * Legacy operators, ordered by id.
     *
     * @return Collection<int, User>
     */
    public function candidates(): Collection
    {
        $userIds = $this->assignments()->pluck('model_id')->unique()->values()->all();

        if ($userIds === []) {
            return new Collection;
        }

        return User::on($this->connection())->whereKey($userIds)->orderBy('id')->get();
    }

    /**
     * How many `super_admin` role assignments (any guard) the given legacy users still hold.
     *
     * @param  list<int>  $userIds
     */
    public function remainingAssignments(array $userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        return $this->assignments()->whereIn('model_id', $userIds)->count();
    }

    /**
     * Removes every `super_admin` role assignment (any guard) of one legacy user.
     */
    public function revoke(int $userId): int
    {
        return $this->assignments()->where('model_id', $userId)->delete();
    }

    public function connection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }

    private function assignments(): Builder
    {
        $connection = DB::connection($this->connection());

        return $connection->table('model_has_roles')
            ->whereIn('role_id', $connection->table('roles')->where('name', self::ROLE)->select('id'))
            ->where('model_type', (new User)->getMorphClass());
    }
}
