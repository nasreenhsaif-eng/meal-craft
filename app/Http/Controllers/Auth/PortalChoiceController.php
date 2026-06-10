<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
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

        return view('auth.portal-choice', [
            'portalChoiceConfig' => [
                'userName' => $user?->name ?? '',
                'onboardingHref' => route('onboarding.show', ['step' => OnboardingStep::Gender->value]),
                'adminHref' => route('admin.dashboard'),
            ],
        ]);
    }
}
