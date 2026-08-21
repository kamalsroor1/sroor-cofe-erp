<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SuperAdminLoginAction;
use App\DTOs\Auth\LoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class SuperAdminAuthController extends Controller
{
    public function __construct(
        protected SuperAdminLoginAction $superAdminLoginAction
    ) {}

    /**
     * Show the Super Admin dedicated login page
     */
    public function showLogin(): Response
    {
        return Inertia::render('SuperAdmin/Auth/Login', [
            'platform_name'    => config('app.name', 'مخزني ERP'),
            'platform_version' => 'v2.5 Enterprise Hub',
        ]);
    }

    /**
     * Authenticate Super Admin into Central Platform
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $credentials = $request->validated();
        $dto = new LoginDTO(
            phone: (string)$credentials['phone'],
            password: (string)$credentials['password'],
            remember: (bool)($credentials['remember'] ?? true)
        );

        try {
            $this->superAdminLoginAction->execute($dto);
            $request->clearRateLimit();
            $request->session()->regenerate();

            return redirect()->intended(route('super.dashboard'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $request->hitRateLimit();
            throw $e;
        }
    }

    /**
     * Super Admin Logout
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super.login')->with('success', __('super.logout_success'));
    }
}
