<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function show(): Response
    {
        $user = Auth::user();

        return Inertia::render('Profile/Show', [
            'user' => [
                'id'               => $user->id,
                'name'             => $user->name,
                'phone'            => $user->phone,
                'email'            => $user->email,
                'theme_preference' => $user->theme_preference ?: 'dark',
                'role'             => $user->roles->first()?->name ?: 'cashier',
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        if (!empty($validated['new_password'])) {
            if (!Hash::check((string)$validated['current_password'], $user->password)) {
                return redirect()->back()->withErrors(['current_password' => __('auth.current_password_incorrect')]);
            }
            $user->password = Hash::make((string)$validated['new_password']);
        }

        $user->name = $validated['name'];
        $user->phone = $validated['phone'];
        $user->email = $validated['email'] ?? null;
        $user->theme_preference = $validated['theme_preference'];
        $user->save();

        return redirect()->back()->with('success', __('auth.profile_updated'));
    }
}