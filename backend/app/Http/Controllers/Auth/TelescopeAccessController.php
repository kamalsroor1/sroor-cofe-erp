<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ConsumeTelescopeLinkAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET /telescope-access?n=…&expires=…&signature=… (behind the `signed` middleware).
 * Redeems a single-use link from IssueTelescopeLinkAction and opens a web session.
 */
final class TelescopeAccessController extends Controller
{
    public function __construct(
        private readonly ConsumeTelescopeLinkAction $consumeTelescopeLinkAction
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            abort(404);
        }

        $user = $this->consumeTelescopeLinkAction->execute((string) $request->query('n', ''));

        if ($user === null) {
            abort(403, __('auth.telescope_forbidden'));
        }

        auth('web')->login($user);
        $request->session()->regenerate();

        return redirect('/telescope');
    }
}
