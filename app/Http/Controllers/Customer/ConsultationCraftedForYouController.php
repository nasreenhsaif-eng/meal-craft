<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ConsultationCraftedForYouController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $user?->customerProfile;
        $isCustomerApp = $user?->isCustomer() === true && $profile?->onboarding_completed_at !== null;

        $consultationConfig = [
            'closeHref' => $isCustomerApp ? route('app.home') : route('admin.dashboard'),
            'homeHref' => $isCustomerApp ? route('app.home') : route('admin.dashboard'),
            'pageEyebrow' => $isCustomerApp ? 'Your plan' : 'Admin / Consultation',
            'adaptedMenuUrl' => url('/api/menu/adapted'),
            'planTiers' => UserPlanCalculator::planTiers(),
            'planTier' => $profile?->daily_calorie_target !== null
                ? (int) UserPlanCalculator::snapToPlanTier((float) $profile->daily_calorie_target)
                : null,
        ];

        return view('pages.consultation.crafted-for-you', [
            'consultationConfig' => $consultationConfig,
        ]);
    }
}
