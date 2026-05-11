<?php

use App\Enums\CyclePhase;
use App\Enums\DietType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use Illuminate\Support\Facades\Schema;

test('meals table has diet_type, cycle_phase, and macro_focus columns', function () {
    expect(Schema::hasColumn('meals', 'diet_type'))->toBeTrue()
        ->and(Schema::hasColumn('meals', 'cycle_phase'))->toBeTrue()
        ->and(Schema::hasColumn('meals', 'macro_focus'))->toBeTrue();
});

test('meals persist diet_type, cycle_phase, macro_focus and cast enums', function () {
    $meal = Meal::query()->create([
        'name' => 'Phase Meal',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
        'diet_type' => DietType::Keto,
        'cycle_phase' => CyclePhase::Follicular,
        'macro_focus' => 'protein',
    ]);

    $meal->refresh();

    expect($meal->diet_type)->toBe(DietType::Keto)
        ->and($meal->cycle_phase)->toBe(CyclePhase::Follicular)
        ->and($meal->macro_focus)->toBe('protein');
});

test('cycle phase scopes filter meals', function () {
    Meal::query()->create([
        'name' => 'M',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
        'cycle_phase' => CyclePhase::Menstrual,
    ]);
    Meal::query()->create([
        'name' => 'F',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
        'cycle_phase' => CyclePhase::Follicular,
    ]);
    Meal::query()->create([
        'name' => 'O',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
        'cycle_phase' => CyclePhase::Ovulatory,
    ]);
    Meal::query()->create([
        'name' => 'L',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
        'cycle_phase' => CyclePhase::Luteal,
    ]);

    expect(Meal::query()->menstrual()->pluck('name')->all())->toBe(['M'])
        ->and(Meal::query()->follicular()->pluck('name')->all())->toBe(['F'])
        ->and(Meal::query()->ovulatory()->pluck('name')->all())->toBe(['O'])
        ->and(Meal::query()->luteal()->pluck('name')->all())->toBe(['L']);
});
