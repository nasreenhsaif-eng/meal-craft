<?php

namespace App\Services;

use App\Enums\CustomerCraftMealSlot;
use App\Models\CustomerCraftPlanDay;
use App\Models\CustomerCraftPlanDayMeal;
use App\Models\Meal;
use App\Services\Nutrition\AdaptedMenuBuilder;
use App\Services\Nutrition\DayMacroReconciliation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class CustomerCraftKitchenSheetService
{
    /**
     * @return list<array{
     *     id: string,
     *     customer_name: string,
     *     plan_tier: int,
     *     craft_key: string,
     *     breakfast: string,
     *     m1: string,
     *     m2: string,
     *     soup: string,
     *     sideSalad: string,
     *     dessert: string,
     *     cutlery: string,
     *     specialRequests: string,
     *     allergies: string,
     *     adapted_meals: list<array<string, mixed>>
     * }>
     */
    public static function kitchenRowsForDate(Carbon $productionDate): array
    {
        $weekday = CustomerCraftPlanService::weekdayFromDate($productionDate);

        /** @var Collection<int, CustomerCraftPlanDay> $latestDays */
        $latestDays = CustomerCraftPlanDay::query()
            ->where('day_of_week', $weekday)
            ->whereHas('craftPlan', fn ($query) => $query->whereNotNull('submitted_at'))
            ->with([
                'craftPlan.customerProfile.user',
                'meals.meal.ingredients',
            ])
            ->get()
            ->groupBy(fn (CustomerCraftPlanDay $day): int => (int) $day->craftPlan->customer_profile_id)
            ->map(function (Collection $days): CustomerCraftPlanDay {
                return $days
                    ->sortByDesc(fn (CustomerCraftPlanDay $day) => $day->craftPlan->submitted_at?->timestamp ?? 0)
                    ->first();
            })
            ->filter()
            ->values();

        $rows = [];

        foreach ($latestDays as $planDay) {
            $profile = $planDay->craftPlan->customerProfile;
            $user = $profile->user;
            $adaptedMeals = [];

            $slotLabels = [
                'breakfast' => '',
                'm1' => '',
                'm2' => '',
                'soup' => '',
                'sideSalad' => '',
                'dessert' => '',
            ];

            $adaptOptions = [
                'include_soup' => $planDay->include_soup,
                'craft_key' => $planDay->craftPlan->craft_key,
                'day_of_week' => (int) $planDay->day_of_week,
            ];

            if ($planDay->include_soup) {
                $soupMeal = $planDay->meals
                    ->first(fn (CustomerCraftPlanDayMeal $row): bool => $row->slot === CustomerCraftMealSlot::Soup)?->meal;

                if ($soupMeal !== null) {
                    $soupCalories = (float) $soupMeal->nutritionForDisplay()['calories'];

                    if ($soupCalories > 0) {
                        $adaptOptions['soup_calories'] = $soupCalories;
                    }
                }
            }

            $sideMeal = $planDay->meals
                ->first(fn (CustomerCraftPlanDayMeal $row): bool => $row->slot === CustomerCraftMealSlot::SideSalad)?->meal;

            if ($sideMeal !== null) {
                $sideCalories = (float) $sideMeal->nutritionForDisplay()['calories'];

                if ($sideCalories > 0) {
                    $adaptOptions['side_salad_calories'] = $sideCalories;
                }
            }

            $dessertMeal = $planDay->meals
                ->first(fn (CustomerCraftPlanDayMeal $row): bool => $row->slot === CustomerCraftMealSlot::Dessert)?->meal;

            if ($dessertMeal !== null) {
                $dessertCalories = (float) $dessertMeal->nutritionForDisplay()['calories'];

                if ($dessertCalories > 0) {
                    $adaptOptions['dessert_calories'] = $dessertCalories;
                }
            }

            /** @var list<Meal> $mainMeals */
            $mainMeals = [];
            /** @var list<int> $mainPositions */
            $mainPositions = [];
            /** @var array{
             *     breakfasts: list<array<string, mixed>>,
             *     meals: list<array<string, mixed>>,
             *     sideSalads: list<array<string, mixed>>,
             *     desserts: list<array<string, mixed>>,
             *     soup: list<array<string, mixed>>
             * } $dayMenu
             */
            $dayMenu = [
                'breakfasts' => [],
                'meals' => [],
                'sideSalads' => [],
                'desserts' => [],
                'soup' => [],
            ];
            /** @var list<string> $fixedSlots */
            $fixedSlots = [];

            foreach ($planDay->meals->sortBy('position') as $dayMeal) {
                if ($dayMeal->meal === null) {
                    continue;
                }

                if ($dayMeal->slot === CustomerCraftMealSlot::Main) {
                    $mainMeals[] = $dayMeal->meal;
                    $mainPositions[] = (int) $dayMeal->position;
                }
            }

            $balancedMains = AdaptedMenuBuilder::adaptMainMealsForProfile($profile, $mainMeals, $adaptOptions);
            $dayMenu['meals'] = $balancedMains;

            foreach ($planDay->meals->sortBy('position') as $dayMeal) {
                $meal = $dayMeal->meal;
                if ($meal === null || $dayMeal->slot === CustomerCraftMealSlot::Main) {
                    continue;
                }

                $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, $adaptOptions);

                if ($adapted === null) {
                    continue;
                }

                match ($dayMeal->slot) {
                    CustomerCraftMealSlot::Breakfast => $dayMenu['breakfasts'][] = $adapted,
                    CustomerCraftMealSlot::SideSalad => $dayMenu['sideSalads'][] = $adapted,
                    CustomerCraftMealSlot::Dessert => $dayMenu['desserts'][] = $adapted,
                    CustomerCraftMealSlot::Soup => $dayMenu['soup'][] = $adapted,
                    default => null,
                };

                $fixedSlot = match ($dayMeal->slot) {
                    CustomerCraftMealSlot::SideSalad => 'side_salad',
                    CustomerCraftMealSlot::Dessert => 'dessert',
                    CustomerCraftMealSlot::Soup => 'soup',
                    default => null,
                };

                if ($fixedSlot !== null) {
                    $fixedSlots[] = $fixedSlot;
                }
            }

            $reconcileOptions = $adaptOptions;

            if ($fixedSlots !== []) {
                $reconcileOptions['selected_fixed_slots'] = array_values(array_unique($fixedSlots));
            }

            foreach (
                [
                    'breakfasts' => 'selected_breakfast_meal_ids',
                    'meals' => 'selected_main_meal_ids',
                    'sideSalads' => 'selected_side_salad_meal_ids',
                    'desserts' => 'selected_dessert_meal_ids',
                    'soup' => 'selected_soup_meal_ids',
                ] as $bucket => $optionKey
            ) {
                $ids = [];

                foreach ($dayMenu[$bucket] as $adapted) {
                    $id = (int) ($adapted['id'] ?? 0);

                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }

                if ($ids !== []) {
                    $reconcileOptions[$optionKey] = array_values(array_unique($ids));
                }
            }

            $reconciled = DayMacroReconciliation::reconcile(
                $profile,
                $dayMenu,
                $mainMeals,
                $reconcileOptions,
            );

            $balancedMainsById = collect($reconciled['dayMenu']['meals'])->keyBy(
                static fn (array $adapted): int => (int) ($adapted['id'] ?? 0),
            );

            foreach ($mainMeals as $index => $mainMeal) {
                $adapted = $balancedMainsById->get((int) $mainMeal->id);

                if (! is_array($adapted)) {
                    continue;
                }

                $adaptedMeals[] = $adapted;
                $label = self::mealLabelWithPortion($adapted);
                $position = $mainPositions[$index] ?? ($index + 1);

                if ($position === 1) {
                    $slotLabels['m1'] = $label;
                } else {
                    $slotLabels['m2'] = $label;
                }
            }

            foreach (
                [
                    'breakfasts' => CustomerCraftMealSlot::Breakfast,
                    'sideSalads' => CustomerCraftMealSlot::SideSalad,
                    'desserts' => CustomerCraftMealSlot::Dessert,
                    'soup' => CustomerCraftMealSlot::Soup,
                ] as $bucket => $slot
            ) {
                foreach ($reconciled['dayMenu'][$bucket] as $adapted) {
                    if (! is_array($adapted)) {
                        continue;
                    }

                    $adaptedMeals[] = $adapted;
                    $label = self::mealLabelWithPortion($adapted);

                    match ($slot) {
                        CustomerCraftMealSlot::Breakfast => $slotLabels['breakfast'] = $label,
                        CustomerCraftMealSlot::Soup => $slotLabels['soup'] = $label,
                        CustomerCraftMealSlot::SideSalad => $slotLabels['sideSalad'] = $label,
                        CustomerCraftMealSlot::Dessert => $slotLabels['dessert'] = $label,
                    };
                }
            }

            $allergies = is_array($profile->allergies) ? implode(', ', $profile->allergies) : '';

            $rows[] = [
                'id' => (string) $planDay->id,
                'customer_name' => $user?->name ?? 'Guest',
                'plan_tier' => (int) $profile->daily_calorie_target,
                'craft_key' => $planDay->craftPlan->craft_key,
                'breakfast' => $slotLabels['breakfast'],
                'm1' => $slotLabels['m1'],
                'm2' => $slotLabels['m2'],
                'soup' => $slotLabels['soup'],
                'sideSalad' => $slotLabels['sideSalad'],
                'dessert' => $slotLabels['dessert'],
                'cutlery' => '',
                'specialRequests' => '',
                'allergies' => $allergies !== '' ? $allergies : '—',
                'adapted_meals' => $adaptedMeals,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $adaptedMeal
     */
    private static function mealLabelWithPortion(array $adaptedMeal): string
    {
        $name = (string) ($adaptedMeal['name'] ?? '');
        $calories = (float) ($adaptedMeal['adapted_nutrition']['calories'] ?? 0);
        $scaled = (bool) ($adaptedMeal['is_scaled'] ?? false);

        if ($calories <= 0) {
            return $name;
        }

        $kcal = (int) round($calories);

        if ($scaled) {
            $multiplier = (float) ($adaptedMeal['scaling_multiplier'] ?? 1);
            $proteinBalanced = (bool) ($adaptedMeal['protein_balanced'] ?? false);
            $suffix = $proteinBalanced ? ', protein-balanced' : '';

            return "{$name} ({$kcal} kcal, ×{$multiplier}{$suffix})";
        }

        return "{$name} ({$kcal} kcal)";
    }

    /**
     * Flatten adapted ingredient grams for scalable meals (kitchen prep).
     *
     * @return list<array{
     *     customer_name: string,
     *     meal_name: string,
     *     slot: string,
     *     ingredient: string,
     *     adapted_amount_grams: float,
     *     is_scaled: bool
     * }>
     */
    public static function ingredientLinesForDate(Carbon $productionDate): array
    {
        $lines = [];

        foreach (self::kitchenRowsForDate($productionDate) as $row) {
            foreach ($row['adapted_meals'] as $adaptedMeal) {
                if (! ($adaptedMeal['is_scaled'] ?? false)) {
                    continue;
                }

                $ingredients = is_array($adaptedMeal['ingredients'] ?? null) ? $adaptedMeal['ingredients'] : [];

                foreach ($ingredients as $ingredient) {
                    $grams = (float) ($ingredient['adapted_amount_grams'] ?? 0);
                    if ($grams <= 0) {
                        continue;
                    }

                    $lines[] = [
                        'customer_name' => $row['customer_name'],
                        'meal_name' => (string) ($adaptedMeal['name'] ?? ''),
                        'slot' => (string) ($adaptedMeal['slot'] ?? ''),
                        'ingredient' => (string) ($ingredient['name'] ?? ''),
                        'adapted_amount_grams' => round($grams, 2),
                        'is_scaled' => true,
                    ];
                }
            }
        }

        return $lines;
    }
}
