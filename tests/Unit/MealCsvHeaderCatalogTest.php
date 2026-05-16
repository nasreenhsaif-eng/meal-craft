<?php

use App\Services\MealCraftMasterCsvExport;
use App\Support\MealCsvHeaderCatalog;

test('master csv export headers use concise catalog labels', function () {
    expect(MealCraftMasterCsvExport::HEADERS)->toBe(MealCsvHeaderCatalog::MASTER_HEADERS);
});

test('meal csv header catalog resolves short headers to internal keys', function () {
    expect(MealCsvHeaderCatalog::shortCanonicalKey('name'))->toBe('meal_name')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey('meal tags'))->toBe('meal_plan_tags')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey('target cal'))->toBe('target_calories')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey('calc carbs'))->toBe('calculated_carbs')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey('photo url'))->toBe('meal_image_path');
});

test('meal csv header catalog normalizes underscore aliases', function () {
    expect(MealCsvHeaderCatalog::normalizeHeaderToken('target_pro'))->toBe('target pro')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey(
            MealCsvHeaderCatalog::normalizeHeaderToken('target_pro'),
        ))->toBe('target_protein');
});
