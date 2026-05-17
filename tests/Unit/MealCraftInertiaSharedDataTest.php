<?php

use App\Models\User;
use App\Support\MealCraftInertiaSharedData;
use App\Support\MealImagePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('meal craft inertia shared data is empty for guests', function () {
    $request = Request::create('/admin/meal-library', 'GET');

    expect(MealCraftInertiaSharedData::forRequest($request))->toBe([]);
});

test('meal craft inertia shared data includes urls taxonomy and csv constants for authenticated users', function () {
    $user = User::factory()->create();
    $request = Request::create('/admin/meal-library', 'GET');
    $request->setUserResolver(static fn () => $user);

    $shared = MealCraftInertiaSharedData::forRequest($request);

    expect($shared)->toHaveKeys(['urls', 'constants', 'taxonomy', 'csv', 'notices'])
        ->and($shared['constants']['missingPhotoPlaceholder'])->toBe(MealImagePath::MISSING_PHOTO_PLACEHOLDER)
        ->and($shared['urls']['mealLibrary']['bulkDestroy'])->toContain('bulk-destroy')
        ->and($shared['urls']['ingredientLibrary']['bulkDestroy'])->toContain('ingredient-library')
        ->and($shared['taxonomy']['mealCategories'])->toContain('Side Salad')
        ->and($shared['csv']['masterMealHeaders'])->toContain('photo_url');
});
