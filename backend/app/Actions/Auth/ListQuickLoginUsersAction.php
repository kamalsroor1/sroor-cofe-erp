<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Picker list for the testing-only quick login: active, non-super-admin users,
 * id + name only (no phone/email), capped at 100.
 */
final class ListQuickLoginUsersAction
{
    private const LIMIT = 100;

    /**
     * @return Collection<int, User>
     */
    public function execute(): Collection
    {
        return User::query()
            ->select(['id', 'name'])
            ->where('is_active', true)
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'super_admin'))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
