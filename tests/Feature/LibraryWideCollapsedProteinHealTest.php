<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\CollapsedPrimaryProteinHealer;
use App\Support\MealLibraryEditGuard;
use App\Support\MenuDevelopmentCsv;
use App\Support\StandardMeatPortion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * @return list<string>
 */
function chickenMealTitlesFromMasterCsv(): array
{
    $path = MenuDevelopmentCsv::mealsPath();
    expect(File::exists($path))->toBeTrue();

    $handle = fopen($path, 'rb');
    expect($handle)->not->toBeFalse();

    $headers = fgetcsv($handle);
    expect($headers)->not->toBeFalse();

    $nameIndex = array_search('meal_name', $headers, true);
    $ingredientsIndex = array_search('ingredients_string', $headers, true);
    expect($nameIndex)->not->toBeFalse()
        ->and($ingredientsIndex)->not->toBeFalse();

    $titles = [];

    while (($row = fgetcsv($handle)) !== false) {
        if (! isset($row[$nameIndex], $row[$ingredientsIndex])) {
            continue;
        }

        $meal = trim((string) $row[$nameIndex]);
        $ingredientsString = trim((string) $row[$ingredientsIndex]);

        if ($meal === '' || $ingredientsString === '') {
            continue;
        }

        foreach (explode('|', $ingredientsString) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || ! str_contains($segment, ':')) {
                continue;
            }

            [$ingredient, $gramsRaw] = array_map('trim', explode(':', $segment, 2));
            $grams = (float) $gramsRaw;

            if (! str_contains(mb_strtolower($ingredient), 'chicken')) {
                continue;
            }

            if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredient, $meal)) {
                continue;
            }

            if ($grams >= 50.0) {
                $titles[$meal] = true;
            }
        }
    }

    fclose($handle);

    return array_keys($titles);
}

test('audits every master-csv chicken meal and heals all crushed portions library-wide', function (): void {
    $titles = chickenMealTitlesFromMasterCsv();
    expect($titles)->not->toBeEmpty()
        ->and(count($titles))->toBeGreaterThan(10);

    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
    ]);

    foreach ($titles as $index => $title) {
        $meal = Meal::factory()->create([
            'name' => $title,
            'library_edited_at' => now(),
        ]);

        $meal->ingredients()->sync([
            $chicken->id => [
                'amount_grams' => $index % 2 === 0 ? 1.0 : 2.0,
                'amount' => $index % 2 === 0 ? 1.0 : 2.0,
                'unit' => 'g',
            ],
        ]);
    }

    $healer = app(CollapsedPrimaryProteinHealer::class);

    expect($healer->audit())->toHaveCount(count($titles));

    $healed = $healer->heal();
    expect($healed)->toHaveCount(count($titles));

    foreach ($titles as $title) {
        $meal = Meal::query()->where('name', $title)->firstOrFail();
        $meal->load('ingredients');

        expect((float) $meal->ingredients->firstWhere('name', 'Chicken Breast')->pivot->amount_grams)
            ->toBe(StandardMeatPortion::GRAMS)
            ->and(MealLibraryEditGuard::mealHasCollapsedPrimaryMeat($meal))
            ->toBeFalse()
            ->and(MealLibraryEditGuard::mealHasCollapsedOrMissingPrimaryMeat($meal))
            ->toBeFalse();
    }

    expect($healer->audit())->toBe([]);
});

test('heal-collapsed-protein artisan command restores crushed chicken across the library', function (): void {
    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
    ]);

    foreach (['Alpha Chicken Bowl', 'Beta Chicken Salad', 'Gamma Chicken Plate'] as $title) {
        $meal = Meal::factory()->create(['name' => $title]);
        $meal->ingredients()->sync([
            $chicken->id => ['amount_grams' => 1.0, 'amount' => 1.0, 'unit' => 'g'],
        ]);
    }

    Artisan::call('menu:heal-collapsed-protein');

    foreach (['Alpha Chicken Bowl', 'Beta Chicken Salad', 'Gamma Chicken Plate'] as $title) {
        $meal = Meal::query()->where('name', $title)->firstOrFail()->load('ingredients');

        expect((float) $meal->ingredients->firstWhere('name', 'Chicken Breast')->pivot->amount_grams)
            ->toBe(StandardMeatPortion::GRAMS);
    }
});

test('edit guard distinguishes collapsed meat from missing meat on chicken-named meals', function (): void {
    $chicken = Ingredient::factory()->create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fat' => 3.6,
    ]);

    $healthy = Meal::factory()->create(['name' => 'Healthy Chicken Plate']);
    $healthy->ingredients()->sync([
        $chicken->id => ['amount_grams' => 150.0, 'amount' => 150.0, 'unit' => 'g'],
    ]);

    $collapsed = Meal::factory()->create(['name' => 'Collapsed Chicken Plate']);
    $collapsed->ingredients()->sync([
        $chicken->id => ['amount_grams' => 2.0, 'amount' => 2.0, 'unit' => 'g'],
    ]);

    $missing = Meal::factory()->create(['name' => 'Turmeric Chicken Kale Salad']);
    $broccoli = Ingredient::factory()->create([
        'name' => 'Broccoli',
        'calories' => 34,
        'protein' => 2.8,
        'carbs' => 7,
        'fat' => 0.4,
    ]);
    $missing->ingredients()->sync([
        $broccoli->id => ['amount_grams' => 60.0, 'amount' => 60.0, 'unit' => 'g'],
    ]);

    expect(MealLibraryEditGuard::mealHasCollapsedPrimaryMeat($healthy->fresh(['ingredients'])))
        ->toBeFalse()
        ->and(MealLibraryEditGuard::mealHasCollapsedOrMissingPrimaryMeat($healthy->fresh(['ingredients'])))
        ->toBeFalse()
        ->and(MealLibraryEditGuard::mealHasCollapsedPrimaryMeat($collapsed->fresh(['ingredients'])))
        ->toBeTrue()
        ->and(MealLibraryEditGuard::mealHasCollapsedOrMissingPrimaryMeat($collapsed->fresh(['ingredients'])))
        ->toBeTrue()
        ->and(MealLibraryEditGuard::mealHasCollapsedPrimaryMeat($missing->fresh(['ingredients'])))
        ->toBeFalse()
        ->and(MealLibraryEditGuard::mealHasCollapsedOrMissingPrimaryMeat($missing->fresh(['ingredients'])))
        ->toBeTrue();
});

test('master meals csv primary chicken portions are not collapsed', function (): void {
    $path = MenuDevelopmentCsv::mealsPath();
    expect(File::exists($path))->toBeTrue();

    $handle = fopen($path, 'rb');
    expect($handle)->not->toBeFalse();

    $headers = fgetcsv($handle);
    expect($headers)->not->toBeFalse();

    $nameIndex = array_search('meal_name', $headers, true);
    $ingredientsIndex = array_search('ingredients_string', $headers, true);

    $collapsed = [];
    $chickenMeals = [];

    while (($row = fgetcsv($handle)) !== false) {
        if (! isset($row[$nameIndex], $row[$ingredientsIndex])) {
            continue;
        }

        $meal = trim((string) $row[$nameIndex]);
        $ingredientsString = trim((string) $row[$ingredientsIndex]);

        foreach (explode('|', $ingredientsString) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || ! str_contains($segment, ':')) {
                continue;
            }

            [$ingredient, $gramsRaw] = array_map('trim', explode(':', $segment, 2));
            $grams = (float) $gramsRaw;

            if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredient, $meal)) {
                continue;
            }

            if (! str_contains(mb_strtolower($ingredient), 'chicken')) {
                continue;
            }

            $chickenMeals[$meal] = max($chickenMeals[$meal] ?? 0.0, $grams);

            if ($grams > 0.0 && $grams < 50.0) {
                $collapsed[] = "{$meal} => {$ingredient} {$grams}g";
            }
        }
    }

    fclose($handle);

    expect($collapsed)->toBe([])
        ->and($chickenMeals)->not->toBeEmpty();

    foreach ($chickenMeals as $meal => $grams) {
        expect($grams)->toBeGreaterThanOrEqual(50.0, $meal);
    }
});
