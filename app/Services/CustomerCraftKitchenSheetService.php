<?php

namespace App\Services;

use App\Enums\CustomerCraftMealSlot;
use App\Models\CustomerCraftPlanDay;
use App\Services\Nutrition\AdaptedMenuBuilder;
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

            foreach ($planDay->meals->sortBy('position') as $dayMeal) {
                $meal = $dayMeal->meal;
                if ($meal === null) {
                    continue;
                }

                $adapted = AdaptedMenuBuilder::adaptMealForProfile($profile, $meal, [
                    'include_soup' => $planDay->include_soup,
                    'craft_key' => $planDay->craftPlan->craft_key,
                ]);

                if ($adapted === null) {
                    continue;
                }

                $adaptedMeals[] = $adapted;
                $label = self::mealLabelWithPortion($adapted);

                match ($dayMeal->slot) {
                    CustomerCraftMealSlot::Breakfast => $slotLabels['breakfast'] = $label,
                    CustomerCraftMealSlot::Main => $dayMeal->position === 1
                        ? $slotLabels['m1'] = $label
                        : $slotLabels['m2'] = $label,
                    CustomerCraftMealSlot::Soup => $slotLabels['soup'] = $label,
                    CustomerCraftMealSlot::SideSalad => $slotLabels['sideSalad'] = $label,
                    CustomerCraftMealSlot::Dessert => $slotLabels['dessert'] = $label,
                };
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

            return "{$name} ({$kcal} kcal, ×{$multiplier})";
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
