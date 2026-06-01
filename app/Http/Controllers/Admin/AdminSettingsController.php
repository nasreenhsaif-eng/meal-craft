<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function editProfile(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Settings/Profile', [
            'profile' => [
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
                'emailVerified' => $user?->hasVerifiedEmail() ?? true,
            ],
        ]);
    }

    public function updateProfile(UpdateAdminProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()
            ->route('admin.settings.profile')
            ->with('success', 'Profile updated.');
    }

    public function editSecurity(): Response
    {
        return Inertia::render('Admin/Settings/Security');
    }

    public function updatePassword(UpdateAdminPasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return redirect()
            ->route('admin.settings.security')
            ->with('success', 'Password updated.');
    }

    public function editAppearance(): Response
    {
        return Inertia::render('Admin/Settings/Appearance');
    }
}
