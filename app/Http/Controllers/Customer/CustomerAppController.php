<?php

namespace App\Http\Controllers\Customer;

use App\Enums\DietProtocol;
use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Services\CustomerCraftPlanPresentationService;
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
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
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
        $calorieRange = $profile !== null ? self::calorieTargetRange($profile) : null;
        $dietProtocol = DietProtocol::tryFromStored($profile?->diet_protocol);

        return Inertia::render('App/Profile', [
            'customerName' => $user?->name ?? '',
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
            'logoutUrl' => route('logout'),
            'profileEditUrl' => route('onboarding.show', [
                'step' => OnboardingStep::entry()->value,
            ]),
            'details' => $profile ? [
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
                'phone' => $profile->phone,
                'address' => self::profileAddress($profile),
                'deliveryAddress' => self::profileDeliveryAddress($profile),
                'cards' => [],
                'sex' => $profile->sex?->value,
                'sexLabel' => $profile->sex?->label() ?? null,
                'dietProtocol' => $dietProtocol->value,
                'dietProtocolLabel' => $dietProtocol->label(),
                'dateOfBirth' => ($profile->birthdate ?? $profile->date_of_birth)?->toDateString(),
                'age' => $profile->age,
                'heightCm' => $profile->height_cm,
                'weightKg' => $profile->weight_kg,
                'targetWeightKg' => $profile->target_weight_kg,
                'activityLevel' => $profile->activity_level?->value,
                'activityLevelLabel' => $profile->activity_level?->label(),
                'dailyCalorieTarget' => $profile->daily_calorie_target,
                'dailyCaloriesMin' => $calorieRange['min'] ?? null,
                'dailyCaloriesMax' => $calorieRange['max'] ?? null,
                'macroSplitStyle' => $profile->macro_split_style?->value,
                'foodFilters' => array_values($profile->food_filters ?? $profile->allergies ?? []),
                'allergies' => array_values($profile->allergies ?? []),
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
            'consultationEditUrl' => route('consultation.crafted-for-you.edit'),
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
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

    public function recipes(CustomerCraftPlanPresentationService $presentation): Response|RedirectResponse
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

        return Inertia::render('App/CookItYourself', [
            'customerName' => $user?->name ?? '',
            'craftPlan' => $presentation->presentSummary($plan, $planTierCalories),
            ...self::customerAppShellUrls(),
            'recipesUrl' => route('meal-plan.recipes'),
            'sex' => $profile->sex?->value,
            'activityLevel' => $profile->activity_level?->value,
            'dailyCalorieTarget' => (int) $profile->daily_calorie_target,
        ]);
    }

    public function favourites(): Response
    {
        return Inertia::render('App/Favourites', [
            'customerName' => request()->user()?->name ?? '',
            ...self::customerAppShellUrls(),
            'recipesUrl' => route('meal-plan.recipes'),
        ]);
    }

    public function tools(): Response
    {
        return Inertia::render('App/MyTools', [
            'customerName' => request()->user()?->name ?? '',
            ...self::customerAppShellUrls(),
        ]);
    }

    /**
     * @return array{
     *     homeUrl: string,
     *     profileUrl: string,
     *     shopUrl: string,
     *     mealPlanSummaryUrl: string,
     *     favouritesUrl: string,
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
            'favouritesUrl' => route('app.favourites'),
            'toolsUrl' => route('app.tools'),
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
