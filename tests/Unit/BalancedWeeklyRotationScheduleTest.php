<?php

use App\Enums\MealPlanSlotType;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\NutrientDenseLiverMealRecipeRefiner;

test('balanced weekly rotation assigns one savory egg breakfast per day', function (): void {
    $dayOne = BalancedWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Breakfast, 1);
    $dayTwo = BalancedWeeklyRotationSchedule::mealNameForDay(2, MealPlanSlotType::Breakfast, 1);

    expect($dayOne)->toBe('Gouda & Spinach Scramble')
        ->and($dayTwo)->toBe('Greek Yogurt & Parmesan Frittata')
        ->and($dayOne)->not->toBe($dayTwo);
});

test('balanced weekly rotation rejects a second breakfast slot', function (): void {
    expect(fn () => BalancedWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Breakfast, 2))
        ->toThrow(InvalidArgumentException::class);
});

test('balanced weekly rotation assigns chia desserts in dessert slot one', function (): void {
    $dayOne = BalancedWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Dessert, 1);
    $dayTwo = BalancedWeeklyRotationSchedule::mealNameForDay(2, MealPlanSlotType::Dessert, 1);

    expect($dayOne)->toBe('Blueberry Walnut Chia Pudding')
        ->and($dayTwo)->toBe('Mango Pumpkin Seed Chia Pudding')
        ->and($dayOne)->not->toBe($dayTwo);
});

test('balanced weekly rotation keeps fixed second choices per slot pattern', function (): void {
    foreach (range(1, 7) as $day) {
        expect(BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Salad, 2))
            ->toBe('Classic Garden Salad')
            ->and(BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Dessert, 2))
            ->toBe('Fruit Salad Bowl')
            ->and(BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Soup, 2))
            ->toBe('Bone Broth Cup');
    }
});

test('balanced weekly rotation assigns greek yogurt chia desserts in dessert slot three', function (): void {
    $dayOne = BalancedWeeklyRotationSchedule::mealNameForDay(1, MealPlanSlotType::Dessert, 3);
    $dayTwo = BalancedWeeklyRotationSchedule::mealNameForDay(2, MealPlanSlotType::Dessert, 3);

    expect($dayOne)->toBe('Blueberry Walnut Greek Yogurt Chia Pudding')
        ->and($dayTwo)->toBe('Mango Pumpkin Seed Greek Yogurt Chia Pudding')
        ->and($dayOne)->toBeIn(BalancedWeeklyRotationSchedule::GREEK_YOGURT_CHIA_DESSERTS)
        ->and($dayTwo)->toBeIn(BalancedWeeklyRotationSchedule::GREEK_YOGURT_CHIA_DESSERTS);
});

test('balanced weekly rotation assigns a different rotating soup in slot 1 each weekday', function (): void {
    $soups = [];

    foreach (range(1, 7) as $day) {
        $soups[] = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Soup, 1);
    }

    expect($soups[0])->toBe('Vegan Mushroom Soup')
        ->and($soups[1])->toBe('Butternut Squash Soup')
        ->and($soups[2])->toBe('Tomato Basil Soup')
        ->and($soups[3])->toBe('Red Lentil Turmeric Soup')
        ->and($soups[4])->toBe('Cauliflower Ginger Soup')
        ->and($soups[5])->toBe('Carrot Cumin Soup')
        ->and($soups[6])->toBe('Sweet Potato Fennel Soup')
        ->and(count(array_unique($soups)))->toBe(7);
});

test('balanced weekly rotation assigns fish daily on main slot three', function (): void {
    $fishMains = [];

    foreach (range(1, 7) as $day) {
        $name = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 3);
        $fishMains[] = $name;

        expect($name)->toBeIn(BalancedWeeklyRotationSchedule::FISH_MAINS)
            ->and(str_contains(strtolower($name), 'beef'))->toBeFalse();
    }

    expect($fishMains)->toBe(BalancedWeeklyRotationSchedule::FISH_MAINS)
        ->and(array_unique($fishMains))->toHaveCount(7);
});

test('balanced weekly rotation uses legume-free vegan side salads in slot one', function (): void {
    $salads = [];

    foreach (range(1, 7) as $day) {
        $salads[] = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Salad, 1);
    }

    expect($salads)->not->toContain('Vegan Curry Lentil Salad')
        ->and($salads)->not->toContain('Spiced Cauliflower Chickpea Salad')
        ->and($salads)->not->toContain('Vegan Mushroom Bowl')
        ->and($salads[6])->toBe('Thai Rainbow Peanut Salad');
});

test('balanced weekly rotation assigns a unique liver main in slot five each weekday', function (): void {
    $liverMains = [];

    foreach (range(1, 7) as $day) {
        $liverMains[] = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 5);
    }

    expect($liverMains[0])->toBe('Seared Beef Liver w Roasted Beetroot, Chard & Chimichurri')
        ->and($liverMains[1])->toBe(NutrientDenseLiverMealRecipeRefiner::SAUTEED_CHICKEN_LIVER_NAME)
        ->and($liverMains[5])->toBe(NutrientDenseLiverMealRecipeRefiner::PERI_PERI_CHICKEN_LIVER_NAME)
        ->and($liverMains[6])->toBe('Spiced Beef & Liver Meatballs w Roasted Tomato Couscous')
        ->and(count(array_unique($liverMains)))->toBe(7);
});

test('balanced weekly rotation keeps liver mains out of slots one through four', function (): void {
    foreach (range(1, 7) as $day) {
        foreach ([1, 2, 3, 4] as $slotIndex) {
            $name = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, $slotIndex);

            expect(str_contains(strtolower($name), 'liver'))->toBeFalse();
        }
    }
});

test('balanced weekly rotation keeps plain beef out of main slot three', function (): void {
    foreach (range(1, 7) as $day) {
        $name = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 3);

        expect(str_contains(strtolower($name), 'beef'))->toBeFalse()
            ->and($name)->toBeIn(BalancedWeeklyRotationSchedule::FISH_MAINS);
    }
});

test('balanced weekly rotation assigns a unique plain beef main in slot four each weekday', function (): void {
    $beefMains = [];

    foreach (range(1, 7) as $day) {
        $beefMains[] = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 4);
    }

    expect($beefMains)->toHaveCount(7)
        ->and(array_unique($beefMains))->toHaveCount(7)
        ->and($beefMains)->toBe(BalancedWeeklyRotationSchedule::BEEF_MAINS)
        ->and(str_contains(strtolower(implode(' ', $beefMains)), 'liver'))->toBeFalse();
});

test('balanced weekly rotation assigns a unique vegan main in slot six each weekday', function (): void {
    $veganMains = [];

    foreach (range(1, 7) as $day) {
        $veganMains[] = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 6);
    }

    expect($veganMains)->toBe(BalancedWeeklyRotationSchedule::VEGAN_MAINS)
        ->and(array_unique($veganMains))->toHaveCount(7);
});
