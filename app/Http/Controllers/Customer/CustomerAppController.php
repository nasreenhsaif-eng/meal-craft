<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerCraftPlanPresentationService;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\AdminConsultationPreviewProfile;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAppController extends Controller
{
    public function home(): Response
    {
        $user = request()->user();
        $profile = $user?->customerProfile;

        $latestCraftPlan = $profile?->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        return Inertia::render('App/Home', [
            'customerName' => $user?->name ?? '',
            'consultationUrl' => route('consultation.crafted-for-you'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
            'profile' => $profile ? [
                'dailyCalorieTarget' => $profile->daily_calorie_target,
                'macroSplitStyle' => $profile->macro_split_style?->value,
                'onboardingCompletedAt' => $profile->onboarding_completed_at?->toIso8601String(),
            ] : null,
            'craftPlan' => $latestCraftPlan ? [
                'craftKey' => $latestCraftPlan->craft_key,
                'weekDuration' => $latestCraftPlan->week_duration,
                'submittedAt' => $latestCraftPlan->submitted_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function mealPlan(CustomerCraftPlanPresentationService $presentation): Response|RedirectResponse
    {
        $user = request()->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;

        $plan = $profile?->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        if ($plan === null || $profile?->daily_calorie_target === null) {
            return redirect()->route('consultation.crafted-for-you');
        }

        $planTierCalories = (int) UserPlanCalculator::snapToPlanTier((float) $profile->daily_calorie_target);

        return Inertia::render('App/MealPlanSummary', [
            'customerName' => $user?->name ?? '',
            'craftPlan' => $presentation->presentSummary($plan, $planTierCalories),
            'consultationUrl' => route('consultation.crafted-for-you'),
            'homeUrl' => route('app.home'),
        ]);
    }
}
