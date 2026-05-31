<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\IngredientAllergenCatalog;
use App\Support\MealCraftInertiaSharedData;
use App\Support\MealImagePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('meal craft inertia shared data is empty for guests', function () {
    $request = Request::create('/admin/meal-library', 'GET');

    expect(MealCraftInertiaSharedData::forRequest($request))->toBe([]);
});

test('meal craft inertia shared data includes admin library payload for admin users', function () {
    $user = User::factory()->create();
    $request = Request::create('/admin/meal-library', 'GET');
    $request->setUserResolver(static fn () => $user);

    $shared = MealCraftInertiaSharedData::forRequest($request);

    expect($shared)->toHaveKeys(['urls', 'constants', 'taxonomy', 'csv', 'notices', 'onboarding'])
        ->and($shared['onboarding']['options']['sex'])->not->toBeEmpty()
        ->and($shared['onboarding']['profile'])->toBeNull()
        ->and($shared['onboarding']['currentStep'])->toBe(OnboardingStep::Welcome->value)
        ->and($shared['onboarding']['urls']['activity'])->toContain('onboarding/activity')
        ->and($shared['constants']['missingPhotoPlaceholder'])->toBe(MealImagePath::MISSING_PHOTO_PLACEHOLDER)
        ->and($shared['urls']['mealLibrary']['bulkDestroy'])->toContain('bulk-destroy')
        ->and($shared['urls']['ingredientLibrary']['bulkDestroy'])->toContain('ingredient-library')
        ->and($shared['taxonomy']['mealCategories'])->toContain('Side Salad')
        ->and($shared['csv']['masterMealHeaders'])->toContain('photo_url');
});

test('meal craft inertia shared data includes onboarding payload for customer users', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create([
        'onboarding_step' => OnboardingStep::Activity,
        'weight_kg' => 70,
        'height_cm' => 170,
    ]);

    $request = Request::create('/onboarding/activity', 'GET');
    $request->setUserResolver(static fn () => $customer);

    $shared = MealCraftInertiaSharedData::forRequest($request);

    expect($shared)->toHaveKey('onboarding')
        ->and($shared)->not->toHaveKey('urls')
        ->and($shared['onboarding']['currentStep'])->toBe(OnboardingStep::Activity->value)
        ->and($shared['onboarding']['customerName'])->toBe($customer->name)
        ->and($shared['onboarding']['urls']['activity'])->toContain('onboarding/activity')
        ->and($shared['onboarding']['options']['sex'])->not->toBeEmpty()
        ->and($shared['onboarding']['options']['allergens'])->not->toBeEmpty()
        ->and($shared['onboarding']['options']['dislikes'])->not->toBeEmpty()
        ->and($shared['onboarding']['profile']['weight_kg'])->toBe(70.0)
        ->and($shared['onboarding']['profile']['height_cm'])->toBe(170.0);
});

test('onboarding options include canonical allergen slugs', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $onboarding = MealCraftInertiaSharedData::onboarding($customer);
    $allergenValues = array_column($onboarding['options']['allergens'], 'value');

    expect($allergenValues)->toContain(IngredientAllergenCatalog::PEANUTS)
        ->and($allergenValues)->toContain(IngredientAllergenCatalog::DAIRY);
});
