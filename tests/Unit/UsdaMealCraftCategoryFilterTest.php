<?php

use App\Support\UsdaMealCraftCategoryFilter;
use Tests\TestCase;

uses(TestCase::class);

test('meal craft search query appends raw meat only for chicken ingredients', function (): void {
    expect(UsdaMealCraftCategoryFilter::mealCraftSearchQueryForIngredient('Chicken'))->toBe('Chicken raw meat only')
        ->and(UsdaMealCraftCategoryFilter::mealCraftSearchQueryForIngredient('Chicken, breast, meat only, raw'))
        ->toBe('Chicken, breast, meat only raw meat only')
        ->and(UsdaMealCraftCategoryFilter::mealCraftSearchQueryForIngredient('Tomato'))->toBe('Tomato');
});

test('meal craft excludes restaurant fast food and branded categories', function (): void {
    expect(UsdaMealCraftCategoryFilter::categoryExcluded('Restaurant Foods'))->toBeTrue()
        ->and(UsdaMealCraftCategoryFilter::categoryExcluded('Fast Foods'))->toBeTrue()
        ->and(UsdaMealCraftCategoryFilter::categoryExcluded('Branded Foods'))->toBeTrue()
        ->and(UsdaMealCraftCategoryFilter::categoryExcluded('Branded Food Products Category'))->toBeTrue()
        ->and(UsdaMealCraftCategoryFilter::categoryExcluded('Vegetables and Vegetable Products'))->toBeFalse();
});

test('meal craft chicken queries require poultry products when category is known', function (): void {
    expect(UsdaMealCraftCategoryFilter::categoryPassesMealCraftRules('Poultry Products', 'Chicken, raw'))->toBeTrue()
        ->and(UsdaMealCraftCategoryFilter::categoryPassesMealCraftRules('Beef Products', 'Chicken, raw'))->toBeFalse()
        ->and(UsdaMealCraftCategoryFilter::categoryPassesMealCraftRules('Beef Products', 'Beef, ground, raw'))->toBeTrue();
});

test('meal craft accepts poultry like category labels for chicken ingredients', function (): void {
    expect(UsdaMealCraftCategoryFilter::categoryIsUsdaPoultryLike('Poultry Products'))->toBeTrue()
        ->and(UsdaMealCraftCategoryFilter::categoryIsUsdaPoultryLike('Frozen poultry cuts'))->toBeTrue()
        ->and(UsdaMealCraftCategoryFilter::categoryPassesMealCraftRules('Frozen poultry cuts', 'Chicken, raw'))->toBeTrue();
});
