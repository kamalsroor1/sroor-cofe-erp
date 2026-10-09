<?php

declare(strict_types=1);

namespace App\Actions\Central;

use App\Models\CentralUser;

/**
 * GET /api/v1/super-admin/auth/me (IDEN-1.3): the authenticated operator with the roles
 * and permissions of the `central` guard loaded for CentralUserResource.
 */
final class GetCentralProfileAction
{
    public function execute(CentralUser $user): CentralUser
    {
        return $user->loadMissing('roles.permissions', 'permissions');
    }
}
