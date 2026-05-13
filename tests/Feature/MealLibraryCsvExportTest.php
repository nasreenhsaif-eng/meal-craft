<?php

use App\Enums\CyclePhase;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\User;
use App\Services\MealCraftMasterCsvExport;
use App\Services\MealCsvLibraryImportService;
use App\Services\MealLibrarySynchronizedCsvExport;
use Illuminate\Http\UploadedFile;

test('guest cannot download meal library export csv', function () {
    $this->get(route('meals.library.export-csv'))->assertRedirect();
});

test('meal library export csv uses meal craft master headers and maps meals', function () {
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
        'cycle_phase' => CyclePhase::Follicular,
        'diet_tags' => ['High protein', 'Low GI'],
        'meal_plan_tag' => 'Performance',
        'safety_alert_tags' => ['Soy'],
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
        ->and($csv)->toContain('Meal Name')
        ->and($csv)->toContain('Meal Plan Tags')
        ->and($csv)->toContain('Cycle Phase (comma or pipe separated')
        ->and($csv)->toContain('Target Calories (kcal)')
        ->and($csv)->toContain('Variance Notes')
        ->and($csv)->toContain('Rice Bowl')
        ->and($csv)->toContain('Follicular')
        ->and($csv)->toContain('Comfort carbs.')
        ->and($csv)->toContain('Steam and serve.')
        ->and($csv)->toContain('Performance')
        ->and($csv)->toContain('High protein, Low GI')
        ->and($csv)->toContain('Soy')
        ->and($csv)->toContain('Rice (200g)')
        ->and($csv)->toContain(MealCraftMasterCsvExport::MISSING_PHOTO_PLACEHOLDER);
});

test('bulk import csv headers stay aligned with synchronized export service', function () {
    expect(MealCsvLibraryImportService::LIBRARY_CSV_HEADERS)->toBe([
        'Meal_Name',
        'Category',
        'Ingredient_Quantities',
        'Instructions',
        'Description_Highlight',
        'Meal_Plan_Tags',
        'Cycle_Phase',
        'Total_Calories',
    ]);

    $handle = fopen('php://memory', 'w+');
    app(MealLibrarySynchronizedCsvExport::class)->writeFullLibraryToStream($handle);
    rewind($handle);
    $first = fgetcsv($handle, 0, ',', '"', '\\');
    fclose($handle);

    expect($first)->toBe(MealCsvLibraryImportService::LIBRARY_CSV_HEADERS);
});

test('meal library synchronized export maps main salad category to meal for bulk import', function () {
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

    $handle = fopen('php://memory', 'w+');
    app(MealLibrarySynchronizedCsvExport::class)->writeFullLibraryToStream($handle);
    rewind($handle);
    $csv = str_replace("\r\n", "\n", (string) stream_get_contents($handle));
    fclose($handle);

    expect($csv)->toBeString()
        ->and($csv)->toContain('Big Salad')
        ->and($csv)->toContain(',Meal,')
        ->and($csv)->toContain('Mix greens.')
        ->and($csv)->toContain('Fiber.');
});

test('bulk import library csv round-trips from synchronized export stream', function () {
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

    $handle = fopen('php://memory', 'w+');
    app(MealLibrarySynchronizedCsvExport::class)->writeFullLibraryToStream($handle);
    rewind($handle);
    $csv = str_replace("\r\n", "\n", (string) stream_get_contents($handle));
    fclose($handle);

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

test('bulk import maps delimited meal plan tags and cycle phases from optional csv columns', function () {
    $user = User::factory()->create();

    Ingredient::query()->create([
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

    $csv = "Meal_Name,Category,Ingredient_Quantities,Instructions,Description_Highlight,Meal_Plan_Tags,Cycle_Phase,Total_Calories\n"
        ."Taggy Bowl,Meal,Rice:100g,Hi,Note,\"Balanced | Ketogenic\",\"follicular, ovulatory\",130\n";

    $file = UploadedFile::fake()->createWithContent('library-tags.csv', $csv);

    $this->actingAs($user)
        ->postJson(route('meals.library.import-csv'), ['file' => $file])
        ->assertOk();

    $meal = Meal::query()->where('name', 'Taggy Bowl')->firstOrFail();
    expect($meal->meal_plan_tags)->toBe(['Balanced', 'Ketogenic'])
        ->and($meal->cycle_phases)->toBe(['follicular', 'ovulatory'])
        ->and($meal->meal_plan_tag)->toBe('Balanced')
        ->and($meal->cycle_phase)->toBe(CyclePhase::Follicular);
});
