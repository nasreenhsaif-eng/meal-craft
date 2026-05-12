<?php

use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\MealCsvLibraryImportService;
use App\Services\MealLibrarySynchronizedCsvExport;
use Illuminate\Http\UploadedFile;

test('guest cannot download meal library export csv', function () {
    $this->get(route('meals.library.export-csv'))->assertRedirect();
});

test('meal library export csv uses bulk import headers and one row per meal', function () {
    $user = User::factory()->create();

    $rice = Ingredient::query()->create([
        'name' => 'Rice',
        'usda_food_category' => 'Other',
        'calories' => 130,
        'protein' => 2.7,
        'carbs' => 28,
        'fat' => 0.3,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Rice Bowl',
        'category' => RecipeCategory::Meal,
        'description' => 'Steam and serve.',
        'highlight' => 'Comfort carbs.',
        'total_calories' => 260,
        'total_protein' => 5,
        'total_carbs' => 56,
        'total_fat' => 0.6,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $meal->ingredients()->attach($rice->id, [
        'amount_grams' => 200,
        'amount' => 200,
        'unit' => 'g',
    ]);

    $response = $this->actingAs($user)->get(route('meals.library.export-csv'));

    $response->assertOk();

    $csv = $response->streamedContent();
    expect($csv)->not->toBe('')
        ->and($csv)->toContain('Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight,Total_Calories')
        ->and($csv)->toContain('Rice Bowl')
        ->and($csv)->toContain(',Meal,')
        ->and($csv)->toContain('Rice:200')
        ->and($csv)->toContain('Steam and serve.')
        ->and($csv)->toContain('Comfort carbs.')
        ->and($csv)->toContain(',260');
});

test('meal library export service headers constant matches import template', function () {
    expect(MealLibrarySynchronizedCsvExport::HEADERS)->toBe(MealCsvLibraryImportService::LIBRARY_CSV_HEADERS)
        ->and(MealLibrarySynchronizedCsvExport::HEADERS)->toBe([
            'Meal_Name',
            'Category',
            'Ingredient_Quantities',
            'Instructions',
            'Description_Highlight',
            'Total_Calories',
        ]);
});

test('meal library export maps main salad category to meal for bulk import', function () {
    $user = User::factory()->create();

    $meal = Meal::query()->create([
        'name' => 'Big Salad',
        'category' => RecipeCategory::MainSalad,
        'description' => 'Mix greens.',
        'highlight' => 'Fiber.',
        'total_calories' => 100,
        'total_protein' => 5,
        'total_carbs' => 10,
        'total_fat' => 2,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('meals.library.export-csv'));

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toBeString()
        ->and($csv)->toContain('Big Salad')
        ->and($csv)->toContain(',Meal,')
        ->and($csv)->toContain('Mix greens.')
        ->and($csv)->toContain('Fiber.');
});

test('meal library exported csv round-trips through bulk import', function () {
    $user = User::factory()->create();

    $rice = Ingredient::query()->create([
        'name' => 'Rice',
        'usda_food_category' => 'Other',
        'calories' => 130,
        'protein' => 2.7,
        'carbs' => 28,
        'fat' => 0.3,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'micronutrients' => [],
        'is_verified' => true,
    ]);

    $meal = Meal::query()->create([
        'name' => 'Rice Bowl',
        'category' => RecipeCategory::Breakfast,
        'description' => 'Steam and serve.',
        'highlight' => 'Comfort carbs.',
        'total_calories' => 260,
        'total_protein' => 5,
        'total_carbs' => 56,
        'total_fat' => 0.6,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_fiber' => 0,
        'total_sugar' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k' => 0,
    ]);
    $meal->ingredients()->attach($rice->id, [
        'amount_grams' => 200,
        'amount' => 200,
        'unit' => 'g',
    ]);

    $export = $this->actingAs($user)->get(route('meals.library.export-csv'));
    $export->assertOk();
    $csv = str_replace("\r\n", "\n", $export->streamedContent());

    Meal::query()->delete();

    expect(Meal::query()->count())->toBe(0);

    $file = UploadedFile::fake()->createWithContent('library-export.csv', $csv);

    $response = $this->actingAs($user)
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk();

    $summary = $response->json('summary');
    expect($summary['imported'])->toBe(1)
        ->and($summary['updated'])->toBe(0)
        ->and($summary['errors'])->toBe(0)
        ->and($summary['pending_ingredient_input'])->toBe(0)
        ->and($summary['duplicates_created'])->toBe(0);

    $meal = Meal::query()->where('name', 'Rice Bowl')->firstOrFail();
    expect($meal->category)->toBe(RecipeCategory::Breakfast)
        ->and($meal->description)->toBe('Steam and serve.')
        ->and($meal->highlight)->toBe('Comfort carbs.')
        ->and($meal->ingredients)->toHaveCount(1)
        ->and((float) $meal->ingredients->first()->pivot->amount_grams)->toBe(200.0);
});
