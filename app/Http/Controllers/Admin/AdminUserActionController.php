<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateManagedAdminPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AdminUserActionController extends Controller
{
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if (! $request->user()?->isCraftMasterGeneral()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', __('Only the Craft Master General can change activation status.'));
        }

        if ($user->is($request->user())) {
            return redirect()->back()->with('error', __('You cannot change your own activation status from this table.'));
        }

        if ($user->isCraftMasterGeneral()) {
            return redirect()->back()->with('error', __('Craft Master General accounts cannot be deactivated here.'));
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        return redirect()->back()->with('success', __('User status updated.'));
    }

    public function updatePassword(UpdateManagedAdminPasswordRequest $request, User $user): RedirectResponse
    {
        if ($user->isCraftMasterGeneral()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', __('Craft Master General passwords cannot be changed here.'));
        }

        if (! $user->canAccessAdminPortal()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', __('That user is not an admin portal account.'));
        }

        $user->update([
            'password' => $request->validated('password'),
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', __('Password updated.'));
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        if (! $request->user()?->isCraftMasterGeneral()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', __('Only the Craft Master General can send password resets.'));
        }

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return redirect()->back()->with('error', __($status));
        }

        return redirect()->back()->with('success', __($status));
    }
}
