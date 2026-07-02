<?php

namespace App\Services;

use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSlotType;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use App\Services\Nutrition\DayMicronutrientCoverageAnalyzer;
use App\Support\NutrientDailyRdi;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Builds the production 7-day Nutrient Density weekly structured meal plan.
 */
final class NutrientDenseWeeklyMealPlanBuilder
{
    public const PLAN_NAME = 'TBD Weekly Protocol';

    public const PLAN_GOAL = 'TBD — micronutrient-optimized whole foods within your calorie tier.';

    public const PROTOCOL_SLUG = 'nutrient_dense';

    public const REFERENCE_DAILY_CALORIES = 1500.0;

    /**
     * @return array{plan: MealPlan, slots: int, refined_meals: list<string>, audit_passed: bool, audit_failures: int}
     */
    public function build(bool $refineRecipes = true, bool $auditOnly = false): array
    {
        $refined = [];

        if ($refineRecipes) {
            $refined = DB::transaction(fn (): array => $this->runRefiners());
            app(MenuDevelopmentCsvSync::class)->syncMealsFromDatabase();
        }

        if ($auditOnly) {
            return [
                'plan' => MealPlan::query()->where('name', self::PLAN_NAME)->first() ?? new MealPlan,
                'slots' => 0,
                'refined_meals' => $refined,
                'audit_passed' => $this->runAuditGate(),
                'audit_failures' => $this->countAuditFailures(),
            ];
        }

        return DB::transaction(function () use ($refineRecipes, $refined): array {
            app(NutrientDenseMealLibraryConfigurator::class)->configure();

            [$dailyProtein, $dailyCarbs, $dailyFat] = $this->referenceDailyMacros();

            $slots = $this->buildSlotPayload();

            $auditPassed = $refineRecipes ? $this->runAuditGate() : true;

            if ($refineRecipes && ! $auditPassed) {
                throw new InvalidArgumentException(
                    'Nutrient-dense plan failed micronutrient audit at 1500+ kcal. Run nutrition:audit-day-coverage for details.',
                );
            }

            $plan = app(MealPlanService::class)->upsertWeeklyStructuredPlanFromScheduler(
                self::PLAN_NAME,
                self::PLAN_GOAL,
                MealPlanLibraryCategory::NutrientDense,
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
                'audit_passed' => $auditPassed,
                'audit_failures' => $this->countAuditFailures(),
            ];
        });
    }

    /**
     * @return list<string>
     */
    private function runRefiners(): array
    {
        $refined = app(NutrientDenseLiverMealRecipeRefiner::class)->refine();
        $refined = array_merge($refined, app(BalancedCanonicalMealRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(NutrientDenseFermentedRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(BalancedChiaDessertRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(NutrientDenseDessertRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(BalancedComplexCarbRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(NutrientDenseEggBreakfastRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(NutrientDenseSideSaladRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(BalancedTandooriMealRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(SaladDressingMealRefiner::class)->refine());
        $refined = array_merge($refined, $this->refineScheduledRotationMeals());
        $refined = array_merge($refined, app(BalancedSodiumRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(NutrientDenseMicronutrientRecipeRefiner::class)->refine());
        $refined = array_merge($refined, app(BalancedMealInstructionRefiner::class)->refine());

        return array_values(array_unique($refined));
    }

    /**
     * @return list<string>
     */
    private function refineScheduledRotationMeals(): array
    {
        $refined = [];
        $scheduled = array_flip(NutrientDenseWeeklyRotationSchedule::allScheduledMealNames());
        $refiner = app(BalancedRotationMealRecipeRefiner::class);

        foreach (BalancedRotationMealRecipeRefiner::refinedMealNames() as $mealName) {
            if (! isset($scheduled[$mealName])) {
                continue;
            }

            $refined = array_merge($refined, $refiner->refine($mealName));
        }

        return $refined;
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
                $mealName = NutrientDenseWeeklyRotationSchedule::mealNameForDay($dayNumber, $slotType, $slotIndex);

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
     * @return array{0: float, 1: float, 2: float}
     */
    public function referenceDailyMacros(): array
    {
        $preset = config('customer_nutrition.diet_protocol_macro_presets.nutrient_dense', []);
        $cal = self::REFERENCE_DAILY_CALORIES;
        $proteinPct = (float) ($preset['protein_percentage'] ?? 32.0);
        $carbPct = (float) ($preset['carb_percentage'] ?? 28.0);
        $fatPct = (float) ($preset['fat_percentage'] ?? 40.0);

        return [
            round($cal * $proteinPct / 100.0 / 4.0, 2),
            round($cal * $carbPct / 100.0 / 4.0, 2),
            round($cal * $fatPct / 100.0 / 9.0, 2),
        ];
    }

    public function runAuditGate(): bool
    {
        return $this->countAuditFailures() === 0;
    }

    public function countAuditFailures(): int
    {
        $auditProfile = self::auditReferenceProfile();

        $failures = 0;

        foreach (NutrientDailyRdi::enforcedTiers() as $tier) {
            foreach (NutrientDailyRdi::fixedSlotCombinations() as $combination) {
                $slots = NutrientDailyRdi::parseFixedSlotCombination($combination);

                foreach (range(1, 7) as $dayNumber) {
                    $report = DayMicronutrientCoverageAnalyzer::simulateFullCraftDay(
                        $auditProfile,
                        $dayNumber,
                        (float) $tier,
                        $slots,
                    );

                    if (! $report['passes']) {
                        $failures++;
                    }
                }
            }
        }

        return $failures;
    }

    public static function auditReferenceProfile(): CustomerProfile
    {
        $existing = CustomerProfile::query()
            ->where('diet_protocol', self::PROTOCOL_SLUG)
            ->whereNotNull('daily_calorie_target')
            ->orderBy('id')
            ->first();

        if ($existing instanceof CustomerProfile) {
            return $existing;
        }

        $user = User::factory()->create();

        return CustomerProfile::factory()->for($user)->create([
            'daily_calorie_target' => 1500,
            'diet_protocol' => self::PROTOCOL_SLUG,
            'protein_percentage' => 32.0,
            'carb_percentage' => 28.0,
            'fat_percentage' => 40.0,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function resolveScheduledMealIds(): array
    {
        $names = NutrientDenseWeeklyRotationSchedule::allScheduledMealNames();

        $meals = Meal::queryForMealLibrary()->whereIn('name', $names)->get(['id', 'name']);

        $map = [];
        foreach ($meals as $meal) {
            $map[$meal->name] = (int) $meal->id;
        }

        return $map;
    }
}
