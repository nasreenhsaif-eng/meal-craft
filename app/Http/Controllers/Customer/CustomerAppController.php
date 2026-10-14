<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Services\CustomerCraftPlanPresentationService;
use App\Services\MealPlanPublishService;
use App\Services\Nutrition\OnboardingDailyTargetsCalculator;
use App\Services\Nutrition\ProductionWeeklyMenuSchedule;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\AdminConsultationPreviewProfile;
use App\Support\MealPlanDateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
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

        $calorieRange = $profile !== null ? self::calorieTargetRange($profile) : null;
        $publishedPlan = $profile !== null
            ? ProductionWeeklyMenuSchedule::resolveProductionMealPlan($profile)
            : null;

        $planDateRange = MealPlanDateRange::forWeek(
            selectedWeekdays: array_values($latestCraftPlan?->selected_weekdays ?? []),
        );
        $showPlanReady = false;

        if (
            $profile !== null
            && $publishedPlan !== null
            && $publishedPlan->published_starts_on !== null
            && $publishedPlan->published_ends_on !== null
        ) {
            $starts = Carbon::parse($publishedPlan->published_starts_on)->startOfDay();
            $ends = Carbon::parse($publishedPlan->published_ends_on)->startOfDay();
            $remainingWeekdays = MealPlanDateRange::remainingWeekdays($starts, $ends);
            $remainingRange = MealPlanDateRange::forRemainingWeekdays($starts, $ends, $remainingWeekdays);

            if ($remainingRange !== null) {
                $planDateRange = $remainingRange;
            }

            $showPlanReady = $remainingWeekdays !== []
                && ! MealPlanPublishService::customerAlreadyChoseForWeek($profile, $starts, $ends);
        }

        return Inertia::render('App/Home', [
            'customerName' => $user?->name ?? '',
            'consultationUrl' => route('consultation.crafted-for-you'),
            'consultationEditUrl' => route('consultation.crafted-for-you.edit'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
            'profileEditUrl' => route('onboarding.show', [
                'step' => OnboardingStep::entry()->value,
            ]),
            'profile' => $profile ? [
                'dailyCalorieTarget' => $profile->daily_calorie_target,
                'dailyCaloriesMin' => $calorieRange['min'] ?? null,
                'dailyCaloriesMax' => $calorieRange['max'] ?? null,
                'macroSplitStyle' => $profile->macro_split_style?->value,
                'onboardingCompletedAt' => $profile->onboarding_completed_at?->toIso8601String(),
            ] : null,
            'craftPlan' => $latestCraftPlan ? [
                'craftKey' => $latestCraftPlan->craft_key,
                'weekDuration' => $latestCraftPlan->week_duration,
                'submittedAt' => $latestCraftPlan->submitted_at?->toIso8601String(),
            ] : null,
            'planDateRange' => $planDateRange,
            'showPlanReady' => $showPlanReady,
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
            'consultationEditUrl' => route('consultation.crafted-for-you.edit'),
            'homeUrl' => route('app.home'),
            'fulfillmentUrl' => route('checkout.fulfillment'),
            'sex' => $profile->sex?->value,
            'activityLevel' => $profile->activity_level?->value,
            'dailyCalorieTarget' => (int) $profile->daily_calorie_target,
        ]);
    }

    /**
     * @return array{min: int, max: int}|null
     */
    private static function calorieTargetRange(CustomerProfile $profile): ?array
    {
        if ($profile->weight_kg === null || $profile->height_cm === null) {
            return null;
        }

        $targets = OnboardingDailyTargetsCalculator::calculate($profile);

        return [
            'min' => $targets['daily_calories_min'],
            'max' => $targets['daily_calories_max'],
        ];
    }
}
