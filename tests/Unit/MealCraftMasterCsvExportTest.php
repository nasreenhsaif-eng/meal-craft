<?php

use App\Enums\CyclePhase;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Services\MealCraftMasterCsvExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('meal craft csv row uses target_carbs for net carb target when target_net_carbs is unset', function () {
    $meal = Meal::query()->create([
        'name' => 'Target carbs mapping',
        'category' => RecipeCategory::Meal,
        'description' => 'd',
        'highlight' => 'h',
        'cycle_phase' => CyclePhase::Follicular,
        'diet_tags' => [],
        'meal_plan_tag' => null,
        'safety_alert_tags' => [],
        'total_calories' => 100,
        'total_protein' => 5,
        'total_carbs' => 40,
        'total_fat' => 2,
        'total_fiber' => 10,
        'total_sugar' => 0,
        'total_b6' => 0,
        'total_folate' => 0,
        'total_b12' => 0,
        'total_iron' => 0,
        'total_magnesium' => 0,
        'total_calcium' => 0,
        'total_potassium' => 0,
        'total_sodium' => 0,
        'total_zinc' => 0,
        'total_vitamin_c' => 0,
        'total_vitamin_a' => 0,
        'total_vitamin_e' => 0,
        'total_vitamin_d' => 0,
        'total_vitamin_k2' => 0,
        'target_carbs' => 50,
    ]);

    $meal->load('ingredients');

    $row = app(MealCraftMasterCsvExport::class)->rowForMeal($meal);

    expect($row[12])->toBe('50')
        ->and($row[17])->toContain('net_carbs_g: 20');
});

test('variance notes list target minus calculated for each macro with a target', function () {
    $notes = MealCraftMasterCsvExport::formatVarianceNotes(
        [
            'calories' => 600.0,
            'protein' => 45.0,
            'fat' => 20.0,
            'net_carbs' => 35.0,
        ],
        [
            'calories' => 612.0,
            'protein' => 42.5,
            'fat' => 22.0,
            'net_carbs' => 28.4,
        ],
    );

    expect($notes)->toContain('kcal: -12')
        ->and($notes)->toContain('protein_g: 2.5')
        ->and($notes)->toContain('fat_g: -2')
        ->and($notes)->toContain('net_carbs_g: 6.6');
});

test('variance notes omit macros without a target', function () {
    $notes = MealCraftMasterCsvExport::formatVarianceNotes(
        ['calories' => 100.0],
        [
            'calories' => 90.0,
            'protein' => 10.0,
            'fat' => 5.0,
            'net_carbs' => 12.0,
        ],
    );

    expect($notes)->toBe('kcal: 10')
        ->and($notes)->not->toContain('protein_g');
});
