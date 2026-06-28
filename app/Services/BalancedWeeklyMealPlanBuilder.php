<?php

namespace App\Services;

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSlotType;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Builds the production 7-day Balanced weekly structured meal plan with rotating daily menus.
 *
 * Daily macro split (40% protein / 30% carbs / 30% fat) is applied at the plan tier via
 * {@see referenceDailyMacros()} and customer onboarding — not enforced per individual meal.
 */
final class BalancedWeeklyMealPlanBuilder
{
    public const PLAN_NAME = 'Balanced Weekly Protocol';

    public const PLAN_GOAL = 'Anti-inflammatory whole-food meals with herbs, spices, and colorful produce — seven distinct daily menus.';

    public const REFERENCE_DAILY_CALORIES = 1500.0;

    /**
     * @return array{plan: MealPlan, slots: int, refined_meals: list<string>}
     */
    public function build(bool $refineRecipes = true): array
    {
        return DB::transaction(function () use ($refineRecipes): array {
            $refined = [];

            if ($refineRecipes) {
                $refined = app(BalancedCanonicalMealRecipeRefiner::class)->refine();
                $refined = array_merge($refined, app(BalancedChiaBreakfastRecipeRefiner::class)->refine());
                $refined = array_merge($refined, app(BalancedComplexCarbRecipeRefiner::class)->refine());
                $refined = array_merge($refined, app(BalancedEggBreakfastRecipeRefiner::class)->refine());
                $refined = array_merge($refined, app(BalancedVeganSideSaladRecipeRefiner::class)->refine());
                $refined = array_merge($refined, app(BalancedTandooriMealRecipeRefiner::class)->refine());
                $refined = array_merge($refined, app(SaladDressingMealRefiner::class)->refine());
                $refined = array_merge($refined, app(BalancedRotationMealRecipeRefiner::class)->refine());
                $refined = array_merge($refined, app(BalancedSodiumRecipeRefiner::class)->refine());
                $refined = array_merge($refined, app(BalancedMicronutrientRecipeRefiner::class)->refine());
                $refined = array_merge($refined, app(BalancedMealInstructionRefiner::class)->refine());

                app(MenuDevelopmentCsvSync::class)->syncMealsFromDatabase();
            }

            app(BalancedMealLibraryConfigurator::class)->configure();

            $this->removeExistingPlan();

            [$dailyProtein, $dailyCarbs, $dailyFat] = $this->referenceDailyMacros();

            $slots = $this->buildSlotPayload();

            $plan = app(MealPlanService::class)->createWeeklyStructuredPlanFromScheduler(
                self::PLAN_NAME,
                self::PLAN_GOAL,
                MealPlanLibraryCategory::Balanced,
                null,
                self::REFERENCE_DAILY_CALORIES,
                $dailyProtein,
                $dailyCarbs,
                $dailyFat,
                $slots,
            );

            return [
                'plan' => $plan,
                'slots' => count($slots),
                'refined_meals' => $refined,
            ];
        });
    }

    /**
     * @return list<array{day_number: int, slot_type: string, slot_index: int, meal_id: int}>
     */
    public function buildSlotPayload(): array
    {
        $mealIdsByName = $this->resolveScheduledMealIds();
        $slots = [];

        foreach (range(1, 7) as $dayNumber) {
            foreach (MealPlanSlotType::daySlotTemplate() as [$slotType, $slotIndex]) {
                $mealName = BalancedWeeklyRotationSchedule::mealNameForDay($dayNumber, $slotType, $slotIndex);

                $mealId = $mealIdsByName[$mealName] ?? null;

                if ($mealId === null) {
                    throw new InvalidArgumentException("Scheduled meal not found in library: {$mealName}");
                }

                $slots[] = [
                    'day_number' => $dayNumber,
                    'slot_type' => $slotType->value,
                    'slot_index' => $slotIndex,
                    'meal_id' => $mealId,
                ];
            }
        }

        return $slots;
    }

    /**
     * @return array{0: float, 1: float, 2: float} protein, carbs, fat (g/day)
     */
    public function referenceDailyMacros(): array
    {
        $preset = config('customer_nutrition.diet_protocol_macro_presets.balanced', []);
        $cal = self::REFERENCE_DAILY_CALORIES;
        $proteinPct = (float) ($preset['protein_percentage'] ?? 40.0);
        $carbPct = (float) ($preset['carb_percentage'] ?? 30.0);
        $fatPct = (float) ($preset['fat_percentage'] ?? 30.0);

        return [
            round($cal * $proteinPct / 100.0 / 4.0, 2),
            round($cal * $carbPct / 100.0 / 4.0, 2),
            round($cal * $fatPct / 100.0 / 9.0, 2),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function resolveScheduledMealIds(): array
    {
        $names = BalancedWeeklyRotationSchedule::allScheduledMealNames();

        $meals = Meal::queryForMealLibrary()->whereIn('name', $names)->get(['id', 'name']);

        $map = [];
        foreach ($meals as $meal) {
            $map[$meal->name] = (int) $meal->id;
        }

        return $map;
    }

    private function removeExistingPlan(): void
    {
        $existing = MealPlan::query()->where('name', self::PLAN_NAME)->first();

        if ($existing === null) {
            return;
        }

        MealPlanDayMeal::query()->where('meal_plan_id', $existing->id)->delete();
        $existing->delete();
    }
}
