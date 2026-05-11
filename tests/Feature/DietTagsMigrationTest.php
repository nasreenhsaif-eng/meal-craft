<?php

use App\Enums\DietTag;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use Illuminate\Support\Facades\Schema;

test('meals and ingredients expose diet_tags column and persist arrays', function () {
    expect(Schema::hasColumn('meals', 'diet_tags'))->toBeTrue()
        ->and(Schema::hasColumn('ingredients', 'diet_tags'))->toBeTrue();

    $meal = Meal::query()->create([
        'name' => 'Keto Tagged Meal',
        'category' => RecipeCategory::Meal,
        'description' => null,
        'highlight' => null,
        'image_path' => null,
        'health_score' => 5.0,
        'diet_tags' => [DietTag::Ketogenic->value, DietTag::Balanced->value],
    ]);

    $ingredient = Ingredient::factory()->create([
        'diet_tags' => [DietTag::Balanced->value],
    ]);

    $meal->refresh();
    $ingredient->refresh();

    expect($meal->diet_tags)->toBe([DietTag::Ketogenic->value, DietTag::Balanced->value])
        ->and($ingredient->diet_tags)->toBe([DietTag::Balanced->value]);
});
