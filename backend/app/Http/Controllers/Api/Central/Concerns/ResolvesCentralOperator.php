<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Concerns;

use App\Models\CentralUser;
use Illuminate\Http\Request;

trait ResolvesCentralOperator
{
    /** AuthenticateCentral guarantees an active CentralUser on every authenticated routes/central.php route. */
    private function operator(Request $request): CentralUser
    {
        $user = $request->user();
        abort_unless($user instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $user;
    }
}
