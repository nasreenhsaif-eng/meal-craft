<?php

namespace App\Services;

use App\Enums\DietProtocol;
use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSlotType;
use App\Http\Controllers\Admin\MealLibraryController;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Support\NutrientDenseDessertMeals;
use App\Support\SavoryEggBreakfastMeals;

final class MealPlanLibraryTierPreview
{
    /** @var list<string> */
    private const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function __construct(
        private MealLibraryController $mealLibrary,
    ) {}

    /**
     * @return list<array{dayNumber: int, label: string, categories: array<string, list<array<string, mixed>>>}>
     */
    public function daysForTier(MealPlan $mealPlan, int $planTier, User $user): array
    {
        $mealPlan->loadMissing([
            'dayMeals' => static function ($query): void {
                $query->where('is_option_b', false)
                    ->orderBy('day_number')
                    ->orderBy('slot_type')
                    ->orderBy('slot_index');
            },
            'dayMeals.meal.ingredients',
        ]);

        $profile = $this->previewProfileForTier($mealPlan, $planTier, $user);
        $dayCount = max(1, $mealPlan->structuredPlanningDayCount());
        $categoryKeys = ['breakfasts', 'meals', 'sideSalads', 'desserts', 'soup'];
        $emptyCategories = array_fill_keys($categoryKeys, []);

        /** @var array<int, array{dayNumber: int, label: string, categories: array<string, list<array<string, mixed>>>}> $daysByNumber */
        $daysByNumber = [];
        for ($dayNumber = 1; $dayNumber <= $dayCount; $dayNumber++) {
            $daysByNumber[$dayNumber] = [
                'dayNumber' => $dayNumber,
                'label' => self::WEEKDAY_LABELS[$dayNumber - 1] ?? __('Day :number', ['number' => $dayNumber]),
                'categories' => $emptyCategories,
            ];
        }

        $buildOptions = [
            'plan_tier' => (float) $planTier,
            'craft_key' => 'full',
        ];

        foreach ($mealPlan->dayMeals as $dayMeal) {
            if (! $dayMeal instanceof MealPlanDayMeal || $dayMeal->meal === null) {
                continue;
            }

            $dayNumber = (int) $dayMeal->day_number;
            if (! isset($daysByNumber[$dayNumber])) {
                continue;
            }

            $slotType = $dayMeal->slot_type instanceof MealPlanSlotType
                ? $dayMeal->slot_type
                : MealPlanSlotType::tryFrom((string) $dayMeal->slot_type);

            if (! $slotType instanceof MealPlanSlotType) {
                continue;
            }

            $meal = $this->resolveMealForProfile($dayMeal->meal, $slotType, $profile);
            $categoryKey = $this->slotTypeToCategoryKey($slotType);

            $adaptOptions = array_merge($buildOptions, [
                'schedule_slot' => AdaptedMenuBuilder::adaptationSlotForMealPlanSlot($slotType),
            ]);

            $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, $adaptOptions);

            if ($adapted === null) {
                continue;
            }

            $baseRow = $this->mealLibrary->presentMealRowForUi($meal);
            $daysByNumber[$dayNumber]['categories'][$categoryKey][] = $this->mealLibrary->applyAdaptedToMealRow(
                $baseRow,
                $adapted,
                $meal,
            );
        }

        return array_values($daysByNumber);
    }

    private function previewProfileForTier(MealPlan $mealPlan, int $planTier, User $user): CustomerProfile
    {
        $user->loadMissing('customerProfile');

        $profile = $user->customerProfile ?? new CustomerProfile([
            'user_id' => $user->id,
        ]);

        $profile->forceFill([
            'daily_calorie_target' => $planTier,
            'diet_protocol' => $this->dietProtocolForPlan($mealPlan)->value,
            'protein_percentage' => $profile->protein_percentage ?? 35,
            'carb_percentage' => $profile->carb_percentage ?? 35,
            'fat_percentage' => $profile->fat_percentage ?? 30,
        ]);

        return $profile;
    }

    private function dietProtocolForPlan(MealPlan $mealPlan): DietProtocol
    {
        return match ($mealPlan->plan_category) {
            MealPlanLibraryCategory::NutrientDense => DietProtocol::NutrientDense,
            default => DietProtocol::Balanced,
        };
    }

    private function resolveMealForProfile(Meal $meal, MealPlanSlotType $slotType, CustomerProfile $profile): Meal
    {
        if ($slotType === MealPlanSlotType::Breakfast) {
            return SavoryEggBreakfastMeals::resolveMealForProfile($meal, $profile);
        }

        if (
            $slotType === MealPlanSlotType::Dessert
            && DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::NutrientDense
        ) {
            return NutrientDenseDessertMeals::resolveMealForProfile($meal, $profile);
        }

        return $meal;
    }

    private function slotTypeToCategoryKey(MealPlanSlotType $slotType): string
    {
        return match ($slotType) {
            MealPlanSlotType::Breakfast => 'breakfasts',
            MealPlanSlotType::Main => 'meals',
            MealPlanSlotType::Salad => 'sideSalads',
            MealPlanSlotType::Dessert => 'desserts',
            MealPlanSlotType::Soup => 'soup',
        };
    }
}
