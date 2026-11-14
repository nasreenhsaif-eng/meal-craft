<?php

namespace App\Http\Controllers\Customer;

use App\Enums\DietProtocol;
use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Services\CustomerCraftPlanPresentationService;
use App\Services\Nutrition\OnboardingDailyTargetsCalculator;
use App\Services\Nutrition\ProductionWeeklyMenuSchedule;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\AdminConsultationPreviewProfile;
use App\Support\CustomerSubscriptionRecorder;
use App\Support\MealPlanDateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAppController extends Controller
{
    public function home(): Response|RedirectResponse
    {
        $user = request()->user();

        if ($user?->shouldLandOnCraftedForYou()) {
            return redirect()->route('consultation.crafted-for-you');
        }

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
        // Welcome-back plan ready always offers Choose your meals after a craft plan exists.
        $showPlanReady = $latestCraftPlan !== null;

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
        }

        $chooseByFriday = Carbon::now()->startOfDay();
        if (! $chooseByFriday->isFriday()) {
            $chooseByFriday = $chooseByFriday->next(Carbon::FRIDAY);
        }

        return Inertia::render('App/Home', [
            'customerName' => $user?->name ?? '',
            'consultationUrl' => route('consultation.crafted-for-you'),
            'consultationEditUrl' => route('consultation.crafted-for-you.edit'),
            ...self::customerAppShellUrls(),
            'fulfillmentUrl' => route('checkout.fulfillment'),
            'chooseByFridayLabel' => MealPlanDateRange::dayLabel($chooseByFriday),
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

    public function profile(): Response
    {
        $user = request()->user();
        $profile = $user?->customerProfile;

        return Inertia::render('App/Profile', [
            'customerName' => $user?->name ?? '',
            ...self::customerAppShellUrls(),
            'logoutUrl' => route('logout'),
            'contactEditUrl' => route('checkout.details'),
            'details' => $profile ? [
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
                'phone' => $profile->phone,
                'address' => self::profileAddress($profile),
                'deliveryAddress' => self::profileDeliveryAddress($profile),
                'cards' => [],
            ] : null,
            'subscription' => self::subscriptionDetails($profile),
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
            ...self::customerAppShellUrls(),
            'sex' => $profile->sex?->value,
            'activityLevel' => $profile->activity_level?->value,
            'dailyCalorieTarget' => (int) $profile->daily_calorie_target,
        ]);
    }

    public function shop(): Response
    {
        return Inertia::render('App/Shop', [
            'customerName' => request()->user()?->name ?? '',
            ...self::customerAppShellUrls(),
        ]);
    }

    public function cart(): Response
    {
        return Inertia::render('App/Cart', [
            'customerName' => request()->user()?->name ?? '',
            ...self::customerAppShellUrls(),
        ]);
    }

    public function recipes(CustomerCraftPlanPresentationService $presentation): Response|RedirectResponse
    {
        $user = request()->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;
        $craftPlan = $this->submittedCraftPlanSummary($presentation);

        if ($craftPlan === null || $profile === null) {
            return redirect()->route('consultation.crafted-for-you');
        }

        return Inertia::render('App/CookItYourself', [
            'customerName' => $user?->name ?? '',
            'craftPlan' => $craftPlan,
            ...self::customerAppShellUrls(),
            'recipesUrl' => route('meal-plan.recipes'),
            'sex' => $profile->sex?->value,
            'activityLevel' => $profile->activity_level?->value,
            'dailyCalorieTarget' => (int) $profile->daily_calorie_target,
        ]);
    }

    public function favourites(): RedirectResponse
    {
        return redirect()->route('app.tools');
    }

    public function tools(CustomerCraftPlanPresentationService $presentation): Response
    {
        return Inertia::render('App/MyTools', [
            'customerName' => request()->user()?->name ?? '',
            'craftPlan' => $this->submittedCraftPlanSummary($presentation),
            'dailyTargets' => $this->dailyTargetsSummary(),
            'bodyPlan' => $this->bodyPlanDetails(),
            'profileEditUrl' => route('onboarding.show', [
                'step' => OnboardingStep::entry()->value,
            ]),
            'recipesUrl' => route('meal-plan.recipes'),
            ...self::customerAppShellUrls(),
        ]);
    }

    /**
     * CamelCase daily targets for the Tools tab. Tools is behind completed onboarding.
     *
     * @return array{
     *     bmr: int,
     *     tdee: int,
     *     dailyCalories: int,
     *     dailyCaloriesMin: int,
     *     dailyCaloriesMax: int,
     *     dailyKjMin: int,
     *     dailyKjMax: int,
     *     proteinGrams: int,
     *     carbGrams: int,
     *     fatGrams: int,
     *     proteinPercentage: float,
     *     carbPercentage: float,
     *     fatPercentage: float,
     *     goal: string,
     *     weightGoal: string
     * }
     */
    private function dailyTargetsSummary(): array
    {
        /** @var CustomerProfile $profile */
        $profile = request()->user()->customerProfile;

        $targets = OnboardingDailyTargetsCalculator::calculate($profile);

        return [
            'bmr' => $targets['bmr'],
            'tdee' => $targets['tdee'],
            'dailyCalories' => $targets['daily_calories'],
            'dailyCaloriesMin' => $targets['daily_calories_min'],
            'dailyCaloriesMax' => $targets['daily_calories_max'],
            'dailyKjMin' => $targets['daily_kj_min'],
            'dailyKjMax' => $targets['daily_kj_max'],
            'proteinGrams' => $targets['protein_grams'],
            'carbGrams' => $targets['carb_grams'],
            'fatGrams' => $targets['fat_grams'],
            'proteinPercentage' => $targets['protein_percentage'],
            'carbPercentage' => $targets['carb_percentage'],
            'fatPercentage' => $targets['fat_percentage'],
            'goal' => $targets['goal'],
            'weightGoal' => $targets['weight_goal'],
        ];
    }

    /**
     * Body & plan rows for the Tools tab.
     *
     * @return array{
     *     sexLabel: string|null,
     *     dietProtocolLabel: string,
     *     dateOfBirth: string|null,
     *     age: int|null,
     *     heightCm: float|null,
     *     weightKg: float|null,
     *     targetWeightKg: float|null,
     *     activityLevelLabel: string|null,
     *     dailyCalorieTarget: int|null,
     *     dailyCaloriesMin: int|null,
     *     dailyCaloriesMax: int|null,
     *     macroSplitStyle: string|null,
     *     foodFilters: list<string>,
     *     allergies: list<string>
     * }
     */
    private function bodyPlanDetails(): array
    {
        /** @var CustomerProfile $profile */
        $profile = request()->user()->customerProfile;
        $calorieRange = self::calorieTargetRange($profile);
        $dietProtocol = DietProtocol::tryFromStored($profile->diet_protocol);

        return [
            'sexLabel' => $profile->sex?->label() ?? null,
            'dietProtocolLabel' => $dietProtocol->label(),
            'dateOfBirth' => ($profile->birthdate ?? $profile->date_of_birth)?->toDateString(),
            'age' => $profile->age,
            'heightCm' => $profile->height_cm,
            'weightKg' => $profile->weight_kg,
            'targetWeightKg' => $profile->target_weight_kg,
            'activityLevelLabel' => $profile->activity_level?->label(),
            'dailyCalorieTarget' => $profile->daily_calorie_target,
            'dailyCaloriesMin' => $calorieRange['min'] ?? null,
            'dailyCaloriesMax' => $calorieRange['max'] ?? null,
            'macroSplitStyle' => $profile->macro_split_style?->value,
            'foodFilters' => array_values($profile->food_filters ?? $profile->allergies ?? []),
            'allergies' => array_values($profile->allergies ?? []),
        ];
    }

    /**
     * Latest submitted craft plan for Saved cards, or null when the customer has not crafted yet.
     *
     * @return array<string, mixed>|null
     */
    private function submittedCraftPlanSummary(CustomerCraftPlanPresentationService $presentation): ?array
    {
        $user = request()->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;

        if ($profile?->daily_calorie_target === null) {
            return null;
        }

        $plan = $profile->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        if ($plan === null) {
            return null;
        }

        $planTierCalories = (int) UserPlanCalculator::snapToPlanTier((float) $profile->daily_calorie_target);

        return $presentation->presentSummary($plan, $planTierCalories);
    }

    /**
     * @return array{
     *     homeUrl: string,
     *     profileUrl: string,
     *     shopUrl: string,
     *     mealPlanSummaryUrl: string,
     *     toolsUrl: string
     * }
     */
    private static function customerAppShellUrls(): array
    {
        return [
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
            'toolsUrl' => route('app.tools'),
            'cartUrl' => route('app.cart'),
        ];
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

    /**
     * Active subscription summary from the latest submitted craft plan, or null when none.
     *
     * @return array{
     *     mealPlanName: string,
     *     days: int,
     *     startDate: string,
     *     expiryDate: string,
     *     deliveries: int
     * }|null
     */
    private static function subscriptionDetails(?CustomerProfile $profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        $paid = $profile->latestPaidSubscription();

        if ($paid !== null) {
            return [
                'mealPlanName' => (string) ($paid->meal_plan_name ?? 'Meal Craft'),
                'days' => (int) $paid->delivery_days_count,
                'startDate' => $paid->starts_on?->toDateString() ?? '',
                'expiryDate' => $paid->expires_on?->toDateString() ?? '',
                'deliveries' => (int) $paid->delivery_days_count,
                'status' => $paid->status->value,
                'paidAt' => $paid->paid_at?->toIso8601String(),
            ];
        }

        $plan = $profile->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        if (! $plan instanceof CustomerCraftPlan) {
            return null;
        }

        $details = CustomerSubscriptionRecorder::detailsFromCraftPlan($profile, $plan);

        if ($details === null) {
            return null;
        }

        return [
            'mealPlanName' => $details['mealPlanName'],
            'days' => $details['days'],
            'startDate' => $details['startDate'],
            'expiryDate' => $details['expiryDate'],
            'deliveries' => $details['deliveries'],
            'status' => null,
            'paidAt' => null,
        ];
    }

    private static function profileAddress(CustomerProfile $profile): ?string
    {
        $area = trim((string) ($profile->area ?? ''));
        $country = trim((string) ($profile->country ?? ''));

        $parts = array_values(array_filter([
            $area !== '' ? $area : null,
            $country !== '' ? $country : null,
        ]));

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    private static function profileDeliveryAddress(CustomerProfile $profile): ?string
    {
        $area = trim((string) ($profile->area ?? ''));
        $block = trim((string) ($profile->block ?? ''));
        $road = trim((string) ($profile->road ?? ''));
        $houseNumber = trim((string) ($profile->house_number ?? ''));
        $gateFlat = trim((string) ($profile->gate_flat_number ?? ''));
        $country = trim((string) ($profile->country ?? ''));

        $parts = array_values(array_filter([
            $houseNumber !== '' ? 'House '.$houseNumber : null,
            $gateFlat !== '' ? 'Gate/Flat '.$gateFlat : null,
            $road !== '' ? 'Road '.$road : null,
            $block !== '' ? 'Block '.$block : null,
            $area !== '' ? $area : null,
            $country !== '' ? $country : null,
        ]));

        return $parts !== [] ? implode(', ', $parts) : null;
    }
}
