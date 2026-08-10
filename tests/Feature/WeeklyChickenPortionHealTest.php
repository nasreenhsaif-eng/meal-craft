<?php

use App\Enums\MealPlanSlotType;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\CollapsedPrimaryProteinHealer;
use App\Support\MenuDevelopmentCsv;
use App\Support\StandardMeatPortion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * @return list<string>
 */
function balancedWeeklyChickenMealNames(): array
{
    return array_values(array_unique(array_merge(
        BalancedWeeklyRotationSchedule::CHICKEN_PLATE_MAINS,
        BalancedWeeklyRotationSchedule::CHICKEN_SALAD_MAINS,
    )));
}

test('every balanced weekday chicken plate and salad heals from two-gram collapse', function (): void {
    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
    ]);
    $rosemaryBase = Ingredient::factory()->create([
        'name' => 'Rosemary Garlic Chicken (Base)',
        'calories' => 200,
        'protein' => 24,
        'carbs' => 3,
        'fat' => 10,
    ]);
    $tandooriBase = Ingredient::factory()->create([
        'name' => 'Tandoori Chicken (Base)',
        'calories' => 190,
        'protein' => 26,
        'carbs' => 2,
        'fat' => 8,
    ]);

    foreach (balancedWeeklyChickenMealNames() as $mealName) {
        $meal = Meal::factory()->create([
            'name' => $mealName,
            'library_edited_at' => now(),
        ]);

        $ingredient = match (true) {
            str_contains($mealName, 'Tandoori') => $tandooriBase,
            str_contains($mealName, 'Rosemary') || str_contains($mealName, 'Mediterranean Crunch') => $rosemaryBase,
            default => $chicken,
        };

        $meal->ingredients()->sync([
            $ingredient->id => ['amount_grams' => 2.0, 'amount' => 2.0, 'unit' => 'g'],
        ]);
    }

    $healer = app(CollapsedPrimaryProteinHealer::class);

    $before = $healer->auditWeeklyChickenSlots();
    expect($before)->toHaveCount(14);
    expect(array_filter($before, fn (array $row): bool => $row['ok']))->toBeEmpty();

    $healed = $healer->heal();
    expect($healed)->toHaveCount(14);

    foreach (range(1, 7) as $day) {
        $plate = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 1);
        $salad = BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 2);

        foreach ([$plate, $salad] as $mealName) {
            $meal = Meal::query()->where('name', $mealName)->firstOrFail()->load('ingredients');
            $primary = $meal->ingredients->first(
                fn ($ingredient): bool => StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)
                    && str_contains(strtolower($ingredient->name), 'chicken'),
            );

            expect($primary)->not->toBeNull($mealName)
                ->and((float) $primary->pivot->amount_grams)->toBe(StandardMeatPortion::GRAMS, $mealName);
        }
    }

    foreach ($healer->auditWeeklyChickenSlots() as $row) {
        expect($row['ok'])->toBeTrue("Day {$row['day']} {$row['slot']}: {$row['meal']}")
            ->and($row['grams'])->toBe(StandardMeatPortion::GRAMS);
    }
});

test('heal-collapsed-protein --weekly fails while any weekday chicken slot is two grams', function (): void {
    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
    ]);

    foreach (balancedWeeklyChickenMealNames() as $mealName) {
        $meal = Meal::factory()->create(['name' => $mealName]);
        $meal->ingredients()->sync([
            $chicken->id => ['amount_grams' => 2.0, 'amount' => 2.0, 'unit' => 'g'],
        ]);
    }

    expect(Artisan::call('menu:heal-collapsed-protein', ['--weekly' => true, '--dry-run' => true]))
        ->toBe(1);

    expect(Artisan::call('menu:heal-collapsed-protein', ['--weekly' => true]))
        ->toBe(0);

    foreach (app(CollapsedPrimaryProteinHealer::class)->auditWeeklyChickenSlots() as $row) {
        expect($row['ok'])->toBeTrue()
            ->and($row['grams'])->toBe(StandardMeatPortion::GRAMS);
    }
});

test('master csv keeps 150g chicken on every balanced weekly chicken meal', function (): void {
    $path = MenuDevelopmentCsv::mealsPath();
    expect(File::exists($path))->toBeTrue();

    $handle = fopen($path, 'rb');
    expect($handle)->not->toBeFalse();

    $headers = fgetcsv($handle);
    $nameIndex = array_search('meal_name', $headers, true);
    $ingredientsIndex = array_search('ingredients_string', $headers, true);

    $byMeal = [];

    while (($row = fgetcsv($handle)) !== false) {
        $byMeal[trim((string) $row[$nameIndex])] = trim((string) $row[$ingredientsIndex]);
    }

    fclose($handle);

    foreach (balancedWeeklyChickenMealNames() as $mealName) {
        expect($byMeal)->toHaveKey($mealName);

        $maxChicken = 0.0;

        foreach (explode('|', $byMeal[$mealName]) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || ! str_contains($segment, ':')) {
                continue;
            }

            [$ingredient, $gramsRaw] = array_map('trim', explode(':', $segment, 2));
            $grams = (float) $gramsRaw;

            if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredient, $mealName)) {
                continue;
            }

            if (! str_contains(mb_strtolower($ingredient), 'chicken')) {
                continue;
            }

            $maxChicken = max($maxChicken, $grams);
        }

        expect($maxChicken)->toBe(StandardMeatPortion::GRAMS, $mealName);
    }
});
