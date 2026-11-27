<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ConsumeTelescopeLinkAction;
use App\Enums\MonitoringTool;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET /telescope-access?n=…&t=…&expires=…&signature=… (behind the `signed` middleware).
 * Redeems a single-use link from IssueTelescopeLinkAction, opens a `central_web` session and
 * redirects to the dashboard named by the signed `t` (telescope | pulse | horizon; a link
 * issued before `t` existed opens Telescope).
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

        $tool = MonitoringTool::tryFrom((string) $request->query('t', MonitoringTool::Telescope->value));

        if ($tool === null || ! $tool->isEnabled()) {
            abort(404);
        }

        $user = $this->consumeTelescopeLinkAction->execute((string) $request->query('n', ''));

        if ($user === null) {
            abort(403, __('auth.telescope_forbidden'));
        }

        // The operator's own session guard (IDEN-1.1), never the tenant-side `web` guard.
        // The Telescope, Pulse and Horizon gates all read this guard.
        auth('central_web')->login($user);
        $request->session()->regenerate();

        return redirect($tool->path());
    }
}
