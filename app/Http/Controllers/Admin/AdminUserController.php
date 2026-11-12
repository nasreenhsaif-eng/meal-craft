<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\User;
use App\Support\AdminPortalPermission;
use Illuminate\Http\RedirectResponse;

class AdminUserController extends Controller
{
    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Admin,
            'admin_permissions' => AdminPortalPermission::sanitize($validated['admin_permissions'] ?? []),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', __('Admin portal user created.'));
    }

    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        if ($user->isCraftMasterGeneral()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', __('Craft Master General accounts cannot be edited here.'));
        }

        if (! $user->canAccessAdminPortal()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', __('That user is not an admin portal account.'));
        }

        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->admin_permissions = AdminPortalPermission::sanitize($validated['admin_permissions'] ?? []);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', __('Admin portal user updated.'));
    }
}
