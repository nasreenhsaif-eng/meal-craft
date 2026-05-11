<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AdminUserActionController extends Controller
{
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()->back()->with('error', __('You cannot change your own activation status from this table.'));
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        return redirect()->back()->with('success', __('User status updated.'));
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return redirect()->back()->with('error', __($status));
        }

        return redirect()->back()->with('success', __($status));
    }
}
