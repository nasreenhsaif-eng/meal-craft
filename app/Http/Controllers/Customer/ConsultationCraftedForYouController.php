<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Api\ConsultationResumeDraftController;
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
        $resumeDraft = null;

        if ($request->query('edit') === '1' && $request->session()->has('consultation_edit_draft')) {
            /** @var array<string, mixed> $editDraft */
            $editDraft = $request->session()->pull('consultation_edit_draft');
        }

        // Home → Choose your meals → Receive: start a fresh Partner/DIY path (ignore payment resume).
        $forceReceive = $request->boolean('receive');
        $fulfillmentQuery = $request->query('fulfillment');
        $initialFulfillmentChoice = in_array($fulfillmentQuery, ['diy', 'partner_kitchen'], true)
            ? $fulfillmentQuery
            : null;

        if ($forceReceive) {
            $request->session()->forget(ConsultationResumeDraftController::SESSION_RESUME_KEY);
        }

        $resumeChooseMeals = ! $forceReceive && (
            $request->boolean('choose_meals')
            || $request->session()->get(ConsultationResumeDraftController::SESSION_RESUME_KEY) === true
        );

        if ($resumeChooseMeals && $request->session()->has(ConsultationResumeDraftController::SESSION_DRAFT_KEY)) {
            /** @var array<string, mixed> $resumeDraft */
            $resumeDraft = $request->session()->get(ConsultationResumeDraftController::SESSION_DRAFT_KEY);
            $request->session()->put(ConsultationResumeDraftController::SESSION_RESUME_KEY, true);
        }

        // Same customer-experience exits for staff and customers (profile state, not role).
        $customerAppHome = route('app.home', absolute: false);
        $customerOnboardingBack = route('onboarding.show', [
            'step' => OnboardingStep::FoodFilters->value,
        ], absolute: false);
        $customerLanding = $user?->shouldLandOnWelcomeBack()
            ? $customerAppHome
            : $customerOnboardingBack;

        $consultationConfig = [
            'backHref' => $customerLanding,
            'closeHref' => $customerLanding,
            'homeHref' => $customerLanding,
            'summaryHref' => route('app.meal-plan', absolute: false),
            'deliveryUrl' => route('checkout.delivery', absolute: false),
            'loginUrl' => route('login'),
            'signOutUrl' => route('sign-out'),
            'csrfToken' => csrf_token(),
            'isCustomerAccount' => $isCustomer,
            'isAdminPreview' => $isAdminPreview,
            'pageEyebrow' => 'Your plan',
            'adaptedMenuUrl' => route('api.menu.adapted', absolute: false),
            'mealDetailViewUrlTemplate' => '/api/meals/{id}/detail-view',
            'mealLibraryRevision' => Meal::libraryRevisionTimestamp(),
            'planTiers' => UserPlanCalculator::planTiers(),
            'planTier' => $profile?->daily_calorie_target !== null
                ? (int) UserPlanCalculator::snapToPlanTier((float) $profile->daily_calorie_target)
                : null,
            'editDraft' => $editDraft,
            'resumeDraft' => $resumeDraft,
            'dietProtocol' => $profile?->diet_protocol ?? 'balanced',
            'sex' => $profile?->sex?->value,
            'activityLevel' => $profile?->activity_level?->value,
            'dailyCalorieTarget' => $profile?->daily_calorie_target !== null
                ? (int) $profile->daily_calorie_target
                : null,
            'resumeChooseMeals' => $resumeChooseMeals,
            'initialFulfillmentChoice' => $initialFulfillmentChoice,
            'forceReceive' => $forceReceive,
        ];

        return view('pages.consultation.crafted-for-you', [
            'consultationConfig' => $consultationConfig,
        ]);
    }
}
