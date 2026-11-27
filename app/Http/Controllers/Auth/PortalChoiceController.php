<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AdminConsultationPreviewProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalChoiceController extends Controller
{
    /**
     * Post-login workspace picker for staff accounts.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        if ($user !== null && $user->isAdmin()) {
            AdminConsultationPreviewProfile::resolve($user);
        }

        return view('auth.portal-choice', [
            'portalChoiceConfig' => [
                'userName' => $user?->name ?? '',
                'onboardingHref' => route('app.home'),
                'adminHref' => route('admin.dashboard'),
            ],
        ]);
    }
}
