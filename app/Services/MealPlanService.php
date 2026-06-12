<?php

namespace App\Services;

use App\Enums\MealCyclePhaseTag;
use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use App\Enums\MealPlanSlotType;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\MealPlanDayMeal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MealPlanService
{
    /**
     * Persist 28-day macro targets (whole-plan totals). Values are divided by 28 for daily planning.
     */
    public function syncMacroTargets(
        MealPlan $plan,
        float $totalCalories,
        ?float $totalProteinG = null,
        ?float $totalCarbsG = null,
        ?float $totalFatG = null,
    ): void {
        $plan->update([
            'target_total_calories' => $totalCalories,
            'target_total_protein_g' => $totalProteinG,
            'target_total_carbs_g' => $totalCarbsG,
            'target_total_fat_g' => $totalFatG,
        ]);
    }

    /**
     * @param  list<array{day_number: int, slot_type: string, slot_index: int, meal_id: int}>  $slots
     */
    public function createWeeklyStructuredPlanFromScheduler(
        string $name,
        string $goal,
        MealPlanLibraryCategory $planCategory,
        ?MealCyclePhaseTag $cyclePhase,
        float $dailyCalories,
        ?float $dailyProteinG,
        ?float $dailyCarbsG,
        ?float $dailyFatG,
        array $slots,
    ): MealPlan {
        return DB::transaction(function () use (
            $name,
            $goal,
            $planCategory,
            $cyclePhase,
            $dailyCalories,
            $dailyProteinG,
            $dailyCarbsG,
            $dailyFatG,
            $slots,
        ): MealPlan {
            $mealPlan = MealPlan::query()->create([
                'name' => $name,
                'goal' => $goal,
                'schema_type' => MealPlanSchemaType::WeeklyStructured,
                'plan_category' => $planCategory,
                'cycle_phase' => $planCategory === MealPlanLibraryCategory::CycleSync ? $cyclePhase : null,
            ]);

            $this->syncMacroTargets(
                $mealPlan,
                $dailyCalories * 7.0,
                $dailyProteinG !== null ? $dailyProteinG * 7.0 : null,
                $dailyCarbsG !== null ? $dailyCarbsG * 7.0 : null,
                $dailyFatG !== null ? $dailyFatG * 7.0 : null,
            );

            foreach ([false, true] as $isOptionB) {
                foreach ($slots as $slot) {
                    $slotType = MealPlanSlotType::from((string) $slot['slot_type']);

                    MealPlanDayMeal::query()->create([
                        'meal_plan_id' => $mealPlan->id,
                        'meal_id' => (int) $slot['meal_id'],
                        'day_number' => (int) $slot['day_number'],
                        'slot_type' => $slotType->value,
                        'slot_index' => (int) $slot['slot_index'],
                        'is_option_b' => $isOptionB,
                    ]);
                }
            }

            return $mealPlan->fresh();
        });
    }

    /**
     * Clears existing day rows and fills 28 days × 11 slots × 2 options from the meal library.
     * Each slot picks a meal whose category matches the slot type and whose macros are closest to
     * the per-slot target derived from plan totals (daily total ÷ 11 slots).
     */
    public function autoFillFourWeekPlan(MealPlan $plan): FourWeekAutoFillResult
    {
        if ($plan->schema_type !== MealPlanSchemaType::FourWeek) {
            throw new InvalidArgumentException('Plan must use the four-week schema.');
        }

        $planTargetCal = $plan->target_total_calories;

        if ($planTargetCal === null || (float) $planTargetCal <= 0.0) {
            return FourWeekAutoFillResult::failure(__('Set a positive total calorie target for the 28 days before auto-fill.'));
        }

        $template = MealPlanSlotType::daySlotTemplate();
        $slotsPerDay = count($template);
        $dailyCalTarget = (float) $planTargetCal / 28.0;
        $perSlotCal = $dailyCalTarget / (float) $slotsPerDay;

        $dailyProtein = $plan->target_total_protein_g !== null ? (float) $plan->target_total_protein_g / 28.0 : null;
        $dailyCarbs = $plan->target_total_carbs_g !== null ? (float) $plan->target_total_carbs_g / 28.0 : null;
        $dailyFat = $plan->target_total_fat_g !== null ? (float) $plan->target_total_fat_g / 28.0 : null;

        $perSlotProtein = $dailyProtein !== null ? $dailyProtein / (float) $slotsPerDay : null;
        $perSlotCarbs = $dailyCarbs !== null ? $dailyCarbs / (float) $slotsPerDay : null;
        $perSlotFat = $dailyFat !== null ? $dailyFat / (float) $slotsPerDay : null;

        $meals = Meal::query()->get([
            'id', 'name', 'meal_type', 'category', 'total_calories', 'total_protein', 'total_carbs', 'total_fat',
        ]);

        /** @var Collection<string, Collection<int, Meal>> $byMealType */
        $byMealType = $meals->groupBy(fn (Meal $m): string => $m->meal_type?->value ?? '');

        MealPlanDayMeal::query()->where('meal_plan_id', $plan->id)->delete();

        foreach ([false, true] as $isOptionB) {
            foreach (range(1, 28) as $dayNumber) {
                foreach ($template as $pairIndex => [$slotType, $slotIndex]) {
                    $mealTypeValue = $slotType->mealType()->value;
                    /** @var Collection<int, Meal> $pool */
                    $pool = $byMealType->get($mealTypeValue, collect());

                    if ($pool->isEmpty()) {
                        return FourWeekAutoFillResult::failure(__(
                            'No meals with type “:type” (day :day, :slot :index, option :opt). Tag meals in the library first.',
                            [
                                'type' => $slotType->mealType()->label(),
                                'day' => $dayNumber,
                                'slot' => $slotType->value,
                                'index' => $slotIndex,
                                'opt' => $isOptionB ? 'B' : 'A',
                            ]
                        ));
                    }

                    $meal = $this->pickMealNearTargets(
                        $pool,
                        $perSlotCal,
                        $perSlotProtein,
                        $perSlotCarbs,
                        $perSlotFat,
                        $dayNumber,
                        $pairIndex,
                        $isOptionB
                    );

                    MealPlanDayMeal::query()->create([
                        'meal_plan_id' => $plan->id,
                        'meal_id' => $meal->id,
                        'day_number' => $dayNumber,
                        'slot_type' => $slotType->value,
                        'slot_index' => $slotIndex,
                        'is_option_b' => $isOptionB,
                    ]);
                }
            }
        }

        $avgA = $this->averageDailyNutritionForOption($plan, false);
        $avgB = $this->averageDailyNutritionForOption($plan, true);

        return FourWeekAutoFillResult::success(
            __('28-day plan auto-filled. Compare daily averages below to your targets (÷28).'),
            $avgA,
            $avgB,
        );
    }

    /**
     * Clears existing rows and fills 7 days × slot template × 2 option paths (same slot layout as four-week).
     */
    public function autoFillWeeklyStructuredPlan(MealPlan $plan): FourWeekAutoFillResult
    {
        if ($plan->schema_type !== MealPlanSchemaType::WeeklyStructured) {
            throw new InvalidArgumentException('Plan must use the weekly structured schema.');
        }

        $planTargetCal = $plan->target_total_calories;

        if ($planTargetCal === null || (float) $planTargetCal <= 0.0) {
            return FourWeekAutoFillResult::failure(__('Set a positive weekly calorie target before auto-fill.'));
        }

        $dayCount = $plan->structuredPlanningDayCount();
        $template = MealPlanSlotType::daySlotTemplate();
        $slotsPerDay = count($template);
        $dailyCalTarget = (float) $planTargetCal / (float) $dayCount;
        $perSlotCal = $dailyCalTarget / (float) $slotsPerDay;

        $dailyProtein = $plan->target_total_protein_g !== null ? (float) $plan->target_total_protein_g / (float) $dayCount : null;
        $dailyCarbs = $plan->target_total_carbs_g !== null ? (float) $plan->target_total_carbs_g / (float) $dayCount : null;
        $dailyFat = $plan->target_total_fat_g !== null ? (float) $plan->target_total_fat_g / (float) $dayCount : null;

        $perSlotProtein = $dailyProtein !== null ? $dailyProtein / (float) $slotsPerDay : null;
        $perSlotCarbs = $dailyCarbs !== null ? $dailyCarbs / (float) $slotsPerDay : null;
        $perSlotFat = $dailyFat !== null ? $dailyFat / (float) $slotsPerDay : null;

        $meals = Meal::query()->get([
            'id', 'name', 'meal_type', 'category', 'total_calories', 'total_protein', 'total_carbs', 'total_fat',
            'total_folate', 'total_iron', 'cycle_phase_tags',
        ]);

        /** @var Collection<string, Collection<int, Meal>> $byMealType */
        $byMealType = $meals->groupBy(fn (Meal $m): string => $m->meal_type?->value ?? '');

        MealPlanDayMeal::query()->where('meal_plan_id', $plan->id)->delete();

        foreach ([false, true] as $isOptionB) {
            foreach (range(1, $dayCount) as $dayNumber) {
                foreach ($template as $pairIndex => [$slotType, $slotIndex]) {
                    $mealTypeValue = $slotType->mealType()->value;
                    /** @var Collection<int, Meal> $pool */
                    $pool = $byMealType->get($mealTypeValue, collect());

                    $pool = $this->filterMealPoolForLibraryCategory($pool, $plan);

                    if ($pool->isEmpty()) {
                        return FourWeekAutoFillResult::failure(__(
                            'No meals with type “:type” (day :day, :slot :index, option :opt). Tag meals in the library first.',
                            [
                                'type' => $slotType->mealType()->label(),
                                'day' => $dayNumber,
                                'slot' => $slotType->value,
                                'index' => $slotIndex,
                                'opt' => $isOptionB ? 'B' : 'A',
                            ]
                        ));
                    }

                    $meal = $this->pickMealNearTargets(
                        $pool,
                        $perSlotCal,
                        $perSlotProtein,
                        $perSlotCarbs,
                        $perSlotFat,
                        $dayNumber,
                        $pairIndex,
                        $isOptionB
                    );

                    MealPlanDayMeal::query()->create([
                        'meal_plan_id' => $plan->id,
                        'meal_id' => $meal->id,
                        'day_number' => $dayNumber,
                        'slot_type' => $slotType->value,
                        'slot_index' => $slotIndex,
                        'is_option_b' => $isOptionB,
                    ]);
                }
            }
        }

        $avgA = $this->averageDailyNutritionForOption($plan, false);
        $avgB = $this->averageDailyNutritionForOption($plan, true);

        return FourWeekAutoFillResult::success(
            __('7-day plan auto-filled from your library. Daily averages below use your weekly targets ÷7.'),
            $avgA,
            $avgB,
        );
    }

    /**
     * @param  Collection<int, Meal>  $pool
     * @return Collection<int, Meal>
     */
    public function filterMealPoolForLibraryCategory(Collection $pool, MealPlan $plan): Collection
    {
        $category = $plan->plan_category;
        if (! $category instanceof MealPlanLibraryCategory || $category === MealPlanLibraryCategory::Balanced) {
            return $pool;
        }

        if ($category === MealPlanLibraryCategory::CycleSync) {
            $phase = $plan->cycle_phase?->value;
            if ($phase === null || $phase === '') {
                return $pool;
            }

            $filtered = $pool->filter(function (Meal $meal) use ($phase): bool {
                $tags = $meal->cycle_phase_tags;

                return is_array($tags) && in_array($phase, $tags, true);
            });

            return $filtered->isNotEmpty() ? $filtered : $pool;
        }

        // Sickle Cell Warrior: prefer meals with stronger folate or iron totals; fall back if none match.
        $filtered = $pool->filter(function (Meal $meal): bool {
            return (float) $meal->total_folate >= 35.0
                || (float) $meal->total_iron >= 1.5;
        });

        return $filtered->isNotEmpty() ? $filtered : $pool;
    }

    /**
     * @return array<string, float>
     */
    public function averageDailyNutritionForOption(MealPlan $plan, bool $isOptionB): array
    {
        $divisor = (float) match ($plan->schema_type) {
            MealPlanSchemaType::WeeklyStructured => 7,
            MealPlanSchemaType::FourWeek => 28,
            default => 28,
        };

        $rows = MealPlanDayMeal::query()
            ->where('meal_plan_id', $plan->id)
            ->where('is_option_b', $isOptionB)
            ->with(['meal:id,total_calories,total_protein,total_carbs,total_fat'])
            ->get();

        $sumCal = 0.0;
        $sumP = 0.0;
        $sumC = 0.0;
        $sumF = 0.0;

        foreach ($rows as $row) {
            $m = $row->meal;
            if ($m === null) {
                continue;
            }
            $sumCal += (float) $m->total_calories;
            $sumP += (float) $m->total_protein;
            $sumC += (float) $m->total_carbs;
            $sumF += (float) $m->total_fat;
        }

        return [
            'calories' => round($sumCal / $divisor, 2),
            'protein' => round($sumP / $divisor, 2),
            'carbs' => round($sumC / $divisor, 2),
            'fat' => round($sumF / $divisor, 2),
        ];
    }

    public function updateSlotMeal(
        MealPlan $plan,
        int $dayNumber,
        MealPlanSlotType $slotType,
        int $slotIndex,
        bool $isOptionB,
        int $mealId,
    ): void {
        if (! $plan->usesStructuredDaySlots()) {
            throw new InvalidArgumentException('Plan must use a structured day schema.');
        }

        $maxDay = $plan->structuredPlanningDayCount();
        if ($dayNumber < 1 || $dayNumber > $maxDay) {
            throw new InvalidArgumentException(__('Invalid day number for this plan.'));
        }

        $meal = Meal::query()->findOrFail($mealId);

        if ($meal->meal_type !== $slotType->mealType()) {
            throw new InvalidArgumentException(
                __('Meal type must match this slot (:expected).', ['expected' => $slotType->mealType()->label()])
            );
        }

        $assignment = MealPlanDayMeal::query()
            ->where('meal_plan_id', $plan->id)
            ->where('day_number', $dayNumber)
            ->where('slot_type', $slotType->value)
            ->where('slot_index', $slotIndex)
            ->where('is_option_b', $isOptionB)
            ->firstOrFail();

        $assignment->update(['meal_id' => $mealId]);
    }

    /**
     * @param  Collection<int, Meal>  $pool
     */
    private function pickMealNearTargets(
        Collection $pool,
        float $targetCal,
        ?float $targetProtein,
        ?float $targetCarbs,
        ?float $targetFat,
        int $dayNumber,
        int $pairIndex,
        bool $isOptionB,
    ): Meal {
        $scored = $pool->map(function (Meal $meal) use ($targetCal, $targetProtein, $targetCarbs, $targetFat): array {
            $score = pow((float) $meal->total_calories - $targetCal, 2);

            if ($targetProtein !== null) {
                $score += pow((float) $meal->total_protein - $targetProtein, 2);
            }
            if ($targetCarbs !== null) {
                $score += pow((float) $meal->total_carbs - $targetCarbs, 2);
            }
            if ($targetFat !== null) {
                $score += pow((float) $meal->total_fat - $targetFat, 2);
            }

            return ['meal' => $meal, 'score' => $score];
        })->sortBy('score')->values();

        $tieWindow = $scored->take(min(5, $scored->count()));
        $seed = ($dayNumber * 97 + $pairIndex * 13 + ($isOptionB ? 7919 : 0)) % max(1, $tieWindow->count());

        return $tieWindow->values()->get($seed)['meal'];
    }
}
