<?php

use App\Enums\RecipeCategory;
use App\Models\Meal;
use App\Support\MealTiersLibraryBrowseTab;

test('browse tabs include protein families and every recipe category', function () {
    expect(array_column(MealTiersLibraryBrowseTab::tabs(), 'id'))->toBe([
        MealTiersLibraryBrowseTab::Chicken,
        MealTiersLibraryBrowseTab::Liver,
        MealTiersLibraryBrowseTab::Salmon,
        MealTiersLibraryBrowseTab::Dessert,
        MealTiersLibraryBrowseTab::SideSalad,
        MealTiersLibraryBrowseTab::Beef,
        MealTiersLibraryBrowseTab::Soup,
        MealTiersLibraryBrowseTab::Breakfast,
        MealTiersLibraryBrowseTab::MainSalad,
        MealTiersLibraryBrowseTab::BaseRecipe,
        MealTiersLibraryBrowseTab::Vegan,
    ]);
});

test('classifies library meals onto the browse tabs', function () {
    expect(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
        'name' => 'Rosemary Garlic Chicken w Mushroom, Spinach & Roasted Sweet Potato',
        'category' => RecipeCategory::Meal,
    ])))->toBe(MealTiersLibraryBrowseTab::Chicken)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Sautéed Chicken Liver with Onions',
            'category' => RecipeCategory::Meal,
        ])))->toBe(MealTiersLibraryBrowseTab::Liver)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Baked Salmon with Fermented Chimichurri',
            'category' => RecipeCategory::Meal,
        ])))->toBe(MealTiersLibraryBrowseTab::Salmon)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Chocolate Orange Brownie',
            'category' => RecipeCategory::Dessert,
        ])))->toBe(MealTiersLibraryBrowseTab::Dessert)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Marinated Pineapple Side Salad',
            'category' => RecipeCategory::SideSalad,
        ])))->toBe(MealTiersLibraryBrowseTab::SideSalad)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Italian Meatballs',
            'category' => RecipeCategory::Meal,
        ])))->toBe(MealTiersLibraryBrowseTab::Beef)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Lentil Soup',
            'category' => RecipeCategory::Soup,
        ])))->toBe(MealTiersLibraryBrowseTab::Soup)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Greek Yogurt Parfait',
            'category' => RecipeCategory::Breakfast,
        ])))->toBe(MealTiersLibraryBrowseTab::Breakfast)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Mediterranean Crunch Salad',
            'category' => RecipeCategory::MainSalad,
        ])))->toBe(MealTiersLibraryBrowseTab::MainSalad)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Vegetable Broth (Base)',
            'category' => RecipeCategory::BaseRecipe,
        ])))->toBe(MealTiersLibraryBrowseTab::BaseRecipe)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Lentil Quinoa Bowl',
            'category' => RecipeCategory::Meal,
            'diet_tags' => ['Vegan'],
        ])))->toBe(MealTiersLibraryBrowseTab::Vegan)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Grilled Mackerel w Roasted Vegetables',
            'category' => RecipeCategory::Meal,
        ])))->toBe(MealTiersLibraryBrowseTab::Salmon);
});

test('breakfast and base recipe stay on their category even when the name mentions chicken', function () {
    expect(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
        'name' => 'Chicken Shakshuka Breakfast',
        'category' => RecipeCategory::Breakfast,
    ])))->toBe(MealTiersLibraryBrowseTab::Breakfast)
        ->and(MealTiersLibraryBrowseTab::forMeal(Meal::factory()->make([
            'name' => 'Rosemary Garlic Chicken (Base)',
            'category' => RecipeCategory::BaseRecipe,
        ])))->toBe(MealTiersLibraryBrowseTab::BaseRecipe);
});

test('tabsForMeals keeps All plus only populated tabs with counts', function () {
    $tabs = MealTiersLibraryBrowseTab::tabsForMeals([
        ['browseTab' => MealTiersLibraryBrowseTab::Chicken],
        ['browseTab' => MealTiersLibraryBrowseTab::Chicken],
        ['browseTab' => MealTiersLibraryBrowseTab::Salmon],
    ]);

    expect(array_column($tabs, 'id'))->toBe([
        MealTiersLibraryBrowseTab::All,
        MealTiersLibraryBrowseTab::Chicken,
        MealTiersLibraryBrowseTab::Salmon,
    ])
        ->and($tabs[0]['count'])->toBe(3)
        ->and($tabs[1]['count'])->toBe(2)
        ->and($tabs[2]['count'])->toBe(1);
});
