<?php

use App\Services\MealCraftMasterCsvExport;
use App\Support\MealCsvHeaderCatalog;
use App\Support\MenuDevelopmentCsv;

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

test('meal csv header catalog resolves production snake_case headers', function () {
    expect(MealCsvHeaderCatalog::shortCanonicalKey(
        MealCsvHeaderCatalog::normalizeHeaderToken('meal_name'),
    ))->toBe('meal_name')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey(
            MealCsvHeaderCatalog::normalizeHeaderToken('ingredients_string'),
        ))->toBe('ingredient_quantities')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey(
            MealCsvHeaderCatalog::normalizeHeaderToken('target_protein'),
        ))->toBe('target_protein')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey(
            MealCsvHeaderCatalog::normalizeHeaderToken('is_bulk'),
        ))->toBe('is_bulk')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey(
            MealCsvHeaderCatalog::normalizeHeaderToken('short_description'),
        ))->toBe('short_description');
});

test('meal csv header catalog normalizes underscore aliases', function () {
    expect(MealCsvHeaderCatalog::normalizeHeaderToken('target_pro'))->toBe('target pro')
        ->and(MealCsvHeaderCatalog::shortCanonicalKey(
            MealCsvHeaderCatalog::normalizeHeaderToken('target_pro'),
        ))->toBe('target_protein');
});

test('meal csv header catalog detects production meal column order', function () {
    expect(MealCsvHeaderCatalog::matchesProductionMealHeaderRow(MenuDevelopmentCsv::MEAL_HEADERS))->toBeTrue()
        ->and(MenuDevelopmentCsv::MEAL_HEADERS[MenuDevelopmentCsv::MEAL_IS_BULK_COLUMN_INDEX])->toBe('is_bulk')
        ->and(MenuDevelopmentCsv::MEAL_HEADERS[MenuDevelopmentCsv::MEAL_SERVINGS_COUNT_COLUMN_INDEX])->toBe('servings_count');
});
