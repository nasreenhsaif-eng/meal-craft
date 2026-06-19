<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\AdminConsultationPreviewProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ConsultationCraftedForYouController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;
        $isCustomer = $user?->isCustomer() === true;
        $isAdminPreview = $user?->isAdmin() === true && ! $isCustomer;

        $consultationConfig = [
            'closeHref' => $isCustomer ? route('app.home') : route('admin.dashboard'),
            'homeHref' => $isCustomer ? route('app.home') : route('admin.dashboard'),
            'summaryHref' => route('app.meal-plan', absolute: false),
            'loginUrl' => route('login'),
            'signOutUrl' => route('sign-out'),
            'csrfToken' => csrf_token(),
            'isCustomerAccount' => $isCustomer,
            'isAdminPreview' => $isAdminPreview,
            'pageEyebrow' => $isCustomer ? 'Your plan' : 'Admin / Consultation',
            'adaptedMenuUrl' => route('api.menu.adapted', absolute: false),
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
