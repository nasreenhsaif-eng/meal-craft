<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\AdminConsultationPreviewProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ConsultationCraftedForYouController extends Controller
{
    public function __invoke(Request $request): View|Response
    {
        // Soft Inertia visits must full-reload into this Blade page.
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        $user = $request->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;
        $isCustomer = $user?->isCustomer() === true;
        $isAdminPreview = $user?->isAdmin() === true && ! $isCustomer;

        $editDraft = null;

        if ($request->query('edit') === '1' && $request->session()->has('consultation_edit_draft')) {
            /** @var array<string, mixed> $editDraft */
            $editDraft = $request->session()->pull('consultation_edit_draft');
        }

        // Without a submitted meal plan, /app redirects here again — send BACK to onboarding.
        $customerAppHome = route('app.home', absolute: false);
        $customerOnboardingBack = route('onboarding.show', [
            'step' => OnboardingStep::FoodFilters->value,
        ], absolute: false);
        $customerLanding = $isCustomer && $user?->shouldLandOnWelcomeBack()
            ? $customerAppHome
            : $customerOnboardingBack;

        $backHref = $isCustomer ? $customerLanding : null;

        $consultationConfig = [
            'backHref' => $backHref,
            'closeHref' => $isCustomer ? $customerLanding : route('admin.dashboard'),
            'homeHref' => $isCustomer ? $customerLanding : route('admin.dashboard'),
            'summaryHref' => route('app.meal-plan', absolute: false),
            'deliveryUrl' => route('checkout.delivery', absolute: false),
            'loginUrl' => route('login'),
            'signOutUrl' => route('sign-out'),
            'csrfToken' => csrf_token(),
            'isCustomerAccount' => $isCustomer,
            'isAdminPreview' => $isAdminPreview,
            'pageEyebrow' => $isCustomer ? 'Your plan' : 'Consultation',
            'adaptedMenuUrl' => route('api.menu.adapted', absolute: false),
            'mealDetailViewUrlTemplate' => '/api/meals/{id}/detail-view',
            'mealLibraryRevision' => Meal::libraryRevisionTimestamp(),
            'planTiers' => UserPlanCalculator::planTiers(),
            'planTier' => $profile?->daily_calorie_target !== null
                ? (int) UserPlanCalculator::snapToPlanTier((float) $profile->daily_calorie_target)
                : null,
            'editDraft' => $editDraft,
            'dietProtocol' => $profile?->diet_protocol ?? 'balanced',
            'sex' => $profile?->sex?->value,
            'activityLevel' => $profile?->activity_level?->value,
            'dailyCalorieTarget' => $profile?->daily_calorie_target !== null
                ? (int) $profile->daily_calorie_target
                : null,
            'resumeChooseMeals' => $request->boolean('choose_meals'),
        ];

        return view('pages.consultation.crafted-for-you', [
            'consultationConfig' => $consultationConfig,
        ]);
    }
}
