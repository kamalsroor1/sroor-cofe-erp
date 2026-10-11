<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Actions\Auth\IssueTelescopeLinkAction;
use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/super-admin/telescope-link
 * Returns a 60-second, single-use signed URL that opens Telescope in the browser.
 */
final class TelescopeLinkController extends Controller
{
    public function __construct(
        private readonly IssueTelescopeLinkAction $issueTelescopeLinkAction
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // AuthenticateCentral (routes/central.php) resolves the request user to a CentralUser.
        /** @var CentralUser $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $this->issueTelescopeLinkAction->execute($user),
                'expires_in' => IssueTelescopeLinkAction::TTL_SECONDS,
            ],
        ]);
    }
}
