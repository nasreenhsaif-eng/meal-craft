<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\AdminConsultationPreviewProfile;
use App\Support\ChiaBreakfastMeals;
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

        $editDraft = null;

        if ($request->query('edit') === '1' && $request->session()->has('consultation_edit_draft')) {
            /** @var array<string, mixed> $editDraft */
            $editDraft = $request->session()->pull('consultation_edit_draft');
        }

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
            'mealDetailViewUrlTemplate' => '/api/meals/{id}/detail-view',
            'planTiers' => UserPlanCalculator::planTiers(),
            'chiaBreakfastMealNames' => ChiaBreakfastMeals::mealNames(),
            'planTier' => $profile?->daily_calorie_target !== null
                ? (int) UserPlanCalculator::snapToPlanTier((float) $profile->daily_calorie_target)
                : null,
            'editDraft' => $editDraft,
        ];

        return view('pages.consultation.crafted-for-you', [
            'consultationConfig' => $consultationConfig,
        ]);
    }
}
