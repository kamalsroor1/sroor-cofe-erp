<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\DTOs\Auth\LoginDTO;
use App\Actions\Auth\LoginAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction
    ) {}

    /**
     * Serve the Vue 3 SPA shell; vue-router renders the login view.
     */
    public function create(): View
    {
        return view('app');
    }

    /**
     * Handle an incoming authentication request via Single Action & DTO.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $dto = LoginDTO::fromRequest($request);

        $authenticated = $this->loginAction->execute($dto);

        if (!$authenticated) {
            $request->hitRateLimit();

            throw ValidationException::withMessages([
                'phone' => __('auth.failed'),
            ]);
        }

        $request->clearRateLimit();
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
