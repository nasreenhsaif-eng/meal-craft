<?php

use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Support\MealPlanSlotBasedDayNutrition;

test('all empty slots yield 1,200 kcal core plus 150 kcal soup', function () {
    $resolve = fn (string $type, int $index): ?Meal => null;

    $core = MealPlanSlotBasedDayNutrition::coreBudgetNutrition($resolve);
    $soup = MealPlanSlotBasedDayNutrition::soupSlotNutrition($resolve);
    $full = MealPlanSlotBasedDayNutrition::fullDayNutrition($resolve);

    expect((float) $core['calories'])->toBe(1200.0)
        ->and((float) $soup['calories'])->toBe(150.0)
        ->and((float) $full['calories'])->toBe(1350.0);
});

test('core uses average breakfast and salad groups and doubles average main calories', function () {
    $m350 = new Meal([
        'total_calories' => 350,
        'total_protein' => 20,
        'total_carbs' => 40,
        'total_fat' => 10,
    ]);
    $m350->category = RecipeCategory::Meal;

    $resolve = function (string $type, int $index) use ($m350): ?Meal {
        if ($type === 'main' && in_array($index, [1, 2, 3, 4], true)) {
            return $m350;
        }

        if ($type === 'breakfast' && in_array($index, [1, 2], true)) {
            return $m350;
        }

        if ($type === 'soup' && $index === 1) {
            return $m350;
        }

        if ($type === 'salad' && in_array($index, [1, 2], true)) {
            return $m350;
        }

        if ($type === 'dessert' && in_array($index, [1, 2], true)) {
            return $m350;
        }

        return null;
    };

    $core = MealPlanSlotBasedDayNutrition::coreBudgetNutrition($resolve);

    // avg bf 350 + 2*avg main 350 + avg salad 350 + avg dessert 350 = 350 + 700 + 350 + 350 = 1750
    expect((float) $core['calories'])->toBe(1750.0);
});

test('admin core high and low paths exclude soup and use two-extreme mains', function () {
    $low = new Meal(['total_calories' => 100, 'total_protein' => 5, 'total_carbs' => 10, 'total_fat' => 3]);
    $low->category = RecipeCategory::Breakfast;
    $high = new Meal(['total_calories' => 500, 'total_protein' => 30, 'total_carbs' => 50, 'total_fat' => 20]);
    $high->category = RecipeCategory::Breakfast;

    $main = new Meal(['total_calories' => 200, 'total_protein' => 15, 'total_carbs' => 20, 'total_fat' => 8]);
    $main->category = RecipeCategory::Meal;

    $resolve = function (string $type, int $index) use ($low, $high, $main): ?Meal {
        if ($type === 'breakfast') {
            return $index === 1 ? $low : $high;
        }
        if ($type === 'main') {
            return $main;
        }
        if ($type === 'soup') {
            return new Meal(['total_calories' => 9999, 'total_protein' => 0, 'total_carbs' => 0, 'total_fat' => 0]);
        }
        if ($type === 'salad') {
            return new Meal(['total_calories' => 40, 'total_protein' => 2, 'total_carbs' => 6, 'total_fat' => 1]);
        }
        if ($type === 'dessert') {
            return $index === 1
                ? new Meal(['total_calories' => 80, 'total_protein' => 1, 'total_carbs' => 15, 'total_fat' => 2])
                : new Meal(['total_calories' => 120, 'total_protein' => 2, 'total_carbs' => 18, 'total_fat' => 4]);
        }

        return null;
    };

    $range = MealPlanSlotBasedDayNutrition::adminCorePathCalorieRange($resolve);

    // High: 500 + 400 + 40 + 120 = 1060 (soup ignored)
    expect($range['max'])->toBe(1060.0)
        // Low: 100 + 400 + 40 + 80 = 620
        ->and($range['min'])->toBe(620.0);
});

test('admin high path picks two largest mains when calories differ', function () {
    $bf = new Meal(['total_calories' => 200, 'total_protein' => 0, 'total_carbs' => 0, 'total_fat' => 0]);
    $bf->category = RecipeCategory::Breakfast;

    $resolve = function (string $type, int $index) use ($bf): ?Meal {
        if ($type === 'breakfast') {
            return $bf;
        }
        if ($type === 'main') {
            return match ($index) {
                1 => new Meal(['total_calories' => 100, 'total_protein' => 0, 'total_carbs' => 0, 'total_fat' => 0]),
                2 => new Meal(['total_calories' => 500, 'total_protein' => 0, 'total_carbs' => 0, 'total_fat' => 0]),
                3 => new Meal(['total_calories' => 480, 'total_protein' => 0, 'total_carbs' => 0, 'total_fat' => 0]),
                4 => new Meal(['total_calories' => 90, 'total_protein' => 0, 'total_carbs' => 0, 'total_fat' => 0]),
                default => null,
            };
        }
        if ($type === 'salad') {
            return new Meal(['total_calories' => 150, 'total_protein' => 0, 'total_carbs' => 0, 'total_fat' => 0]);
        }
        if ($type === 'dessert') {
            return new Meal(['total_calories' => 150, 'total_protein' => 0, 'total_carbs' => 0, 'total_fat' => 0]);
        }

        return null;
    };

    $range = MealPlanSlotBasedDayNutrition::adminCorePathCalorieRange($resolve);

    // High: 200 + (500+480) + 150 + 150 = 1480
    expect($range['max'])->toBe(1480.0)
        ->and($range['max'])->toBeGreaterThan(MealPlanSlotBasedDayNutrition::CORE_HIGH_PATH_WARNING_KCAL);

    // Low: 200 + (90+100) + 150 + 150 = 690
    expect($range['min'])->toBe(690.0);
});
