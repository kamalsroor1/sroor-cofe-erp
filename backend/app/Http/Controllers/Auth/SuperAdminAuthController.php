<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class SuperAdminAuthController extends Controller
{
    /**
     * Show the Super Admin dedicated login page
     */
    public function showLogin(): Response
    {
        return Inertia::render('SuperAdmin/Auth/Login', [
            'platform_name' => config('app.name', 'مخزني ERP'),
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
        $phoneOrEmail = (string)$credentials['phone'];

        $user = User::where(function ($q) use ($phoneOrEmail) {
            $q->where('phone', $phoneOrEmail)
              ->orWhere('email', $phoneOrEmail);
        })->where('is_active', true)->first();

        if (!$user || !Hash::check((string)$credentials['password'], $user->password)) {
            $request->hitRateLimit();

            throw ValidationException::withMessages([
                'phone' => __('auth.failed'),
            ]);
        }

        // Strict Check: User MUST have admin role or be authorized for Super Admin
        if (!$user->hasRole('admin') && !$user->can('super_admin.access')) {
            $request->hitRateLimit();

            throw ValidationException::withMessages([
                'phone' => __('super.unauthorized_super_admin_access'),
            ]);
        }

        $request->clearRateLimit();

        Auth::login($user, (bool)($credentials['remember'] ?? true));
        $request->session()->regenerate();

        return redirect()->intended(route('super.dashboard'));
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
