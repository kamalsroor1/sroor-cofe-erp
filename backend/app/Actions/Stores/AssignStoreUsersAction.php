<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\Models\Store;

final class AssignStoreUsersAction
{
    /**
     * Synchronize assigned users to the store
     */
    public function execute(Store $store, array $userIds): Store
    {
        $store->users()->sync($userIds);

        return $store->load('users:id,name,email');
    }
}
